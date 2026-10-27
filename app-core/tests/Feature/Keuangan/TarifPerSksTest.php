<?php

declare(strict_types=1);

use App\DTOs\Akademik\KeputusanWaliData;
use App\Enums\InvoiceStatus;
use App\Enums\JenisBeasiswa;
use App\Enums\JenisDokumenAkuntansi;
use App\Enums\JenisItemTagihan;
use App\Enums\KomponenTarif;
use App\Enums\KrsStatus;
use App\Enums\PaymentStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Akuntansi\DokumenAkuntansi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\Pembayaran;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Keuangan\Tarif;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Notifications\Keuangan\TagihanDisesuaikan;
use App\Notifications\Keuangan\TagihanDiterbitkan;
use App\Services\Akademik\KrsService;
use App\Services\Keuangan\BeasiswaService;
use App\Services\Keuangan\PembayaranService;
use App\Services\Keuangan\PenerbitanTagihanService;
use App\Services\Keuangan\TagihanKrsService;
use App\Services\Keuangan\TarifResolver;
use App\Support\Akuntansi;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Roadmap #41 — charges per credit and per course, billed from the approved
 * KRS. See ADR-030.
 *
 * The factory's running term sits inside the change window: regular KRS closed
 * a week before term, revisions open for two more weeks. So by default a class
 * dropped "today" is past `krs_selesai` and does NOT reduce the bill; tests that
 * want the opposite move the deadline.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    config([
        'academic.krs.requires_payment' => true,
        'academic.krs.min_payment_percent' => 50,
        'payment.krs.batas_pengurangan' => 'krs_selesai',
        'payment.krs.jatuh_tempo_hari' => 14,
        'payment.invoice.due_days' => 30,
    ]);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    Portal::lupakanTerm();

    $this->prodi = Prodi::factory()->create();
    $this->kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id]);
    $this->wali = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'kurikulum_id' => $this->kurikulum->id,
        'dosen_wali_id' => $this->wali->id,
        'angkatan' => 2026,
        'jalur_masuk' => 'Reguler',
        'golongan_ukt' => 'III',
        'status' => StudentStatus::Aktif,
    ]);
    $this->mahasiswa->assignRole('mahasiswa');

    $this->krs = app(KrsService::class);
    $this->tagihanKrs = app(TagihanKrsService::class);
    $this->resolver = app(TarifResolver::class);
});

/** A course in the student's curriculum and one class of it, without a timetable (no clashes). */
function kelasBerbayar(int $sks = 3, ?MataKuliah $mk = null, string $kode = 'A'): KelasKuliah
{
    $test = test();

    if ($mk === null) {
        $mk = MataKuliah::factory()->create(['prodi_id' => $test->prodi->id, 'sks' => $sks]);
        $test->kurikulum->mataKuliah()->attach($mk->id, ['semester' => 1, 'jenis' => 'wajib']);
    }

    return KelasKuliah::factory()->create([
        'tahun_akademik_id' => $test->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $test->prodi->id,
        'sks' => $mk->sks,
        'kode' => $kode,
        'kuota' => 40,
    ]);
}

function tarifKrs(array $atribut = []): Tarif
{
    return Tarif::create([
        'komponen' => KomponenTarif::PerSks,
        'nama' => 'Biaya per SKS',
        'nominal' => 150_000,
        'is_active' => true,
        ...$atribut,
    ]);
}

/** Fills, submits and approves the plan through the real service. */
function setujuiKrs(array $kelas): Krs
{
    $test = test();
    $krs = $test->krs->bukaAtauAmbil($test->mahasiswa, $test->term);

    foreach ($kelas as $k) {
        $test->krs->tambahKelas($krs, $k);
    }

    $test->krs->ajukan($krs->refresh());

    return $test->krs->putuskan($krs->refresh(), $test->wali, new KeputusanWaliData(disetujui: true));
}

/** Reopens, applies the change, resubmits and re-approves. */
function revisiKrs(Krs $krs, array $tambah = [], array $hapus = []): Krs
{
    $test = test();
    $krs = $test->krs->bukaRevisi($krs);

    foreach ($hapus as $kelas) {
        $test->krs->hapusKelas($krs, $krs->detail()->where('kelas_kuliah_id', $kelas->id)->sole());
        $krs->refresh();
    }

    foreach ($tambah as $kelas) {
        $test->krs->tambahKelas($krs, $kelas);
        $krs->refresh();
    }

    $test->krs->ajukan($krs);

    return $test->krs->putuskan($krs->refresh(), $test->wali, new KeputusanWaliData(disetujui: true));
}

function tagihanSemester(): ?Tagihan
{
    $test = test();

    return Tagihan::where('mahasiswa_id', $test->mahasiswa->id)
        ->where('tahun_akademik_id', $test->term->id)
        ->first();
}

describe('resolusi tarif dari KRS', function () {
    it('tidak memasukkan komponen per SKS dan per mata kuliah ke penerbitan massal', function () {
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP', 'nominal' => 1_000_000, 'is_active' => true]);
        tarifKrs();
        tarifKrs([
            'komponen' => KomponenTarif::MataKuliah,
            'nama' => 'Praktikum',
            'nominal' => 300_000,
            'mata_kuliah_id' => kelasBerbayar()->mata_kuliah_id,
        ]);

        expect($this->resolver->untuk($this->mahasiswa, $this->term)->pluck('nama')->all())->toBe(['SPP'])
            ->and($this->resolver->total($this->mahasiswa, $this->term))->toBe(1_000_000)
            ->and(app(PenerbitanTagihanService::class)->pratinjau($this->term)['total_rupiah'])->toBe(1_000_000);
    });

    it('memilih tarif per SKS yang paling spesifik', function () {
        tarifKrs(['nominal' => 100_000]);
        tarifKrs(['nominal' => 150_000, 'prodi_id' => $this->prodi->id]);
        $menang = tarifKrs(['nominal' => 175_000, 'prodi_id' => $this->prodi->id, 'angkatan' => 2026]);
        tarifKrs(['nominal' => 999_000, 'prodi_id' => Prodi::factory()->create()->id, 'angkatan' => 2026]);

        expect($this->resolver->perSks($this->mahasiswa, $this->term)?->id)->toBe($menang->id);
    });

    it('tidak memiliki tarif per SKS bila matriks tidak memuatnya', function () {
        expect($this->resolver->perSks($this->mahasiswa, $this->term))->toBeNull();
    });

    it('menentukan pemenang per mata kuliah, bukan per kategori', function () {
        $basisData = kelasBerbayar()->mataKuliah;
        $jaringan = kelasBerbayar()->mataKuliah;

        tarifKrs(['komponen' => KomponenTarif::MataKuliah, 'nominal' => 200_000, 'mata_kuliah_id' => $basisData->id]);
        $khusus = tarifKrs([
            'komponen' => KomponenTarif::MataKuliah,
            'nominal' => 250_000,
            'mata_kuliah_id' => $basisData->id,
            'prodi_id' => $this->prodi->id,
        ]);
        $lain = tarifKrs(['komponen' => KomponenTarif::MataKuliah, 'nominal' => 100_000, 'mata_kuliah_id' => $jaringan->id]);

        $hasil = $this->resolver->perMataKuliah($this->mahasiswa, $this->term);

        expect($hasil)->toHaveCount(2)
            ->and($hasil->get($basisData->id)->id)->toBe($khusus->id)
            ->and($hasil->get($jaringan->id)->id)->toBe($lain->id);
    });
});

describe('penerbitan dari KRS yang disetujui', function () {
    it('tidak menerbitkan apa pun bagi kampus tanpa tarif per SKS', function () {
        Tarif::create(['komponen' => 'ukt', 'nama' => 'UKT', 'nominal' => 3_000_000, 'is_active' => true]);

        setujuiKrs([kelasBerbayar()]);

        expect(tagihanSemester())->toBeNull();
    });

    it('menagih SKS yang disetujui ke tagihan semester beserta komponen tetapnya', function () {
        Notification::fake();
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP Tetap', 'nominal' => 1_000_000, 'is_active' => true]);
        tarifKrs(['nominal' => 150_000]);

        setujuiKrs([kelasBerbayar(3), kelasBerbayar(2)]);

        $tagihan = tagihanSemester();
        $baris = $tagihan->item()->dariKrs()->orderBy('id')->get();

        expect($baris->pluck('nominal')->all())->toBe([450_000, 300_000])
            ->and($baris->every(fn (TagihanItem $i): bool => $i->komponen === KomponenTarif::PerSks))->toBeTrue()
            ->and($baris->first()->nama)->toContain('3 SKS × Rp150.000')
            ->and($tagihan->nominal_krs)->toBe(750_000)
            ->and($tagihan->total)->toBe(1_750_000)
            ->and($tagihan->status)->toBe(InvoiceStatus::BelumBayar)
            ->and($tagihan->jatuh_tempo->gte(Carbon::today()->addDays(14)))->toBeTrue();

        Notification::assertSentTo($this->mahasiswa, TagihanDiterbitkan::class);
    });

    it('menambahkan biaya per mata kuliah di samping biaya per SKS', function () {
        tarifKrs(['nominal' => 100_000]);
        $praktikum = kelasBerbayar(3);
        tarifKrs([
            'komponen' => KomponenTarif::MataKuliah,
            'nama' => 'Praktikum Basis Data',
            'nominal' => 350_000,
            'mata_kuliah_id' => $praktikum->mata_kuliah_id,
        ]);

        setujuiKrs([$praktikum, kelasBerbayar(2)]);

        $tagihan = tagihanSemester();

        expect($tagihan->item()->where('komponen', KomponenTarif::MataKuliah->value)->pluck('nama')->all())
            ->toBe(['Praktikum Basis Data'])
            ->and($tagihan->total)->toBe(300_000 + 200_000 + 350_000);
    });

    it('menambah baris ke tagihan yang sudah terbit massal tanpa memajukan jatuh temponya', function () {
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP Tetap', 'nominal' => 1_000_000, 'is_active' => true]);
        tarifKrs(['nominal' => 100_000]);

        // The gate is not under test here; an unpaid fixed invoice would block approval.
        config(['academic.krs.requires_payment' => false]);
        app(PenerbitanTagihanService::class)->terbitkan($this->term);
        $lambat = Carbon::today()->addDays(60);
        tagihanSemester()->update(['jatuh_tempo' => $lambat]);

        setujuiKrs([kelasBerbayar(3)]);

        $tagihan = tagihanSemester();

        expect(Tagihan::count())->toBe(1)
            ->and($tagihan->total)->toBe(1_300_000)
            ->and($tagihan->jatuh_tempo->toDateString())->toBe($lambat->toDateString());
    });

    it('memundurkan jatuh tempo yang lebih awal ke sesudah persetujuan', function () {
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP Tetap', 'nominal' => 1_000_000, 'is_active' => true]);
        tarifKrs();

        // The gate is not under test here; an unpaid fixed invoice would block approval.
        config(['academic.krs.requires_payment' => false]);
        app(PenerbitanTagihanService::class)->terbitkan($this->term);
        tagihanSemester()->update(['jatuh_tempo' => Carbon::today()->subDays(3)]);

        setujuiKrs([kelasBerbayar(3)]);

        expect(tagihanSemester()->jatuh_tempo->toDateString())
            ->toBe(Carbon::today()->addDays(14)->toDateString());
    });

    it('tidak menulis apa pun saat disinkronkan ulang', function () {
        tarifKrs();
        $krs = setujuiKrs([kelasBerbayar(3)]);
        $jumlah = TagihanItem::count();

        $this->tagihanKrs->sinkronkan($krs);
        $this->tagihanKrs->sinkronkan($krs);

        expect(TagihanItem::count())->toBe($jumlah);
    });

    it('tidak menagih rencana yang belum disetujui', function () {
        tarifKrs();
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $this->krs->tambahKelas($krs, kelasBerbayar());

        expect($this->tagihanKrs->sinkronkan($krs->refresh()))->toBeNull()
            ->and(Tagihan::count())->toBe(0);
    });

    it('tidak menagih ulang tarif yang berubah sesudah ditagih', function () {
        $tarif = tarifKrs(['nominal' => 100_000]);
        $krs = setujuiKrs([kelasBerbayar(3)]);

        $tarif->update(['nominal' => 200_000]);
        $this->tagihanKrs->sinkronkan($krs);

        expect(tagihanSemester()->total)->toBe(300_000);
    });
});

describe('revisi KRS', function () {
    it('menambah tagihan untuk kelas yang ditambahkan saat revisi', function () {
        Notification::fake();
        tarifKrs(['nominal' => 100_000]);
        $krs = setujuiKrs([kelasBerbayar(3)]);

        revisiKrs($krs, tambah: [kelasBerbayar(2)]);

        $tagihan = tagihanSemester();

        expect($tagihan->item()->dariKrs()->orderBy('id')->pluck('nominal')->all())->toBe([300_000, 200_000])
            ->and($tagihan->total)->toBe(500_000)
            ->and($tagihan->nominal_krs)->toBe(500_000);

        Notification::assertSentTo(
            $this->mahasiswa,
            TagihanDisesuaikan::class,
            fn (TagihanDisesuaikan $n): bool => $n->selisih === 200_000,
        );
    });

    it('mengurangi tagihan dengan baris koreksi bila kelas dibatalkan sebelum batas', function () {
        config(['payment.krs.batas_pengurangan' => 'krs_perubahan_selesai']);
        tarifKrs(['nominal' => 100_000]);
        $tetap = kelasBerbayar(3);
        $batal = kelasBerbayar(2);
        $krs = setujuiKrs([$tetap, $batal]);

        revisiKrs($krs, hapus: [$batal]);

        $tagihan = tagihanSemester();
        $asli = $tagihan->item()->dariKrs()->where('mata_kuliah_id', $batal->mata_kuliah_id)->whereNull('koreksi_atas_item_id')->sole();
        $koreksi = $tagihan->item()->where('koreksi_atas_item_id', $asli->id)->sole();

        // The original charge is still there; the correction answers it.
        expect($asli->nominal)->toBe(200_000)
            ->and($koreksi->nominal)->toBe(-200_000)
            ->and($koreksi->jenis)->toBe(JenisItemTagihan::Tagihan)
            ->and($koreksi->nama)->toBe($asli->nama)
            ->and($koreksi->alasan)->toContain('sebelum batas')
            ->and($tagihan->total)->toBe(300_000)
            ->and($tagihan->nominal_krs)->toBe(300_000);
    });

    it('tidak mengurangi tagihan bila kelas dibatalkan sesudah batas, tetapi mencatatnya', function () {
        tarifKrs(['nominal' => 100_000]);
        $batal = kelasBerbayar(2);
        $krs = setujuiKrs([kelasBerbayar(3), $batal]);

        revisiKrs($krs, hapus: [$batal]);

        $tagihan = tagihanSemester();
        $koreksi = $tagihan->item()->whereNotNull('koreksi_atas_item_id')->sole();

        expect($koreksi->nominal)->toBe(0)
            ->and($koreksi->alasan)->toContain('tidak berkurang')
            ->and($tagihan->total)->toBe(500_000);
    });

    it('tidak menagih dua kali mata kuliah yang dibatalkan sesudah batas lalu diambil lagi', function () {
        tarifKrs(['nominal' => 100_000]);
        $batal = kelasBerbayar(2);
        $krs = setujuiKrs([kelasBerbayar(3), $batal]);

        $krs = revisiKrs($krs, hapus: [$batal]);
        revisiKrs($krs, tambah: [kelasBerbayar(mk: $batal->mataKuliah, kode: 'B')]);

        expect(tagihanSemester()->total)->toBe(500_000)
            ->and(TagihanItem::whereNotNull('koreksi_atas_item_id')->count())->toBe(1);
    });

    it('tidak menagih ulang pindah kelas pada mata kuliah yang sama', function () {
        config(['payment.krs.batas_pengurangan' => 'krs_perubahan_selesai']);
        tarifKrs(['nominal' => 100_000]);
        $kelasA = kelasBerbayar(3);
        $kelasB = kelasBerbayar(mk: $kelasA->mataKuliah, kode: 'B');
        $krs = setujuiKrs([$kelasA]);

        revisiKrs($krs, tambah: [$kelasB], hapus: [$kelasA]);

        expect(tagihanSemester()->total)->toBe(300_000)
            ->and(TagihanItem::count())->toBe(1);
    });

    it('menagih lagi mata kuliah yang dulu dikurangkan lalu diambil kembali', function () {
        config(['payment.krs.batas_pengurangan' => 'krs_perubahan_selesai']);
        tarifKrs(['nominal' => 100_000]);
        $batal = kelasBerbayar(2);
        $krs = setujuiKrs([kelasBerbayar(3), $batal]);

        $krs = revisiKrs($krs, hapus: [$batal]);
        revisiKrs($krs, tambah: [kelasBerbayar(mk: $batal->mataKuliah, kode: 'B')]);

        expect(tagihanSemester()->total)->toBe(500_000)
            ->and(tagihanSemester()->item()->dariKrs()->orderBy('id')->pluck('nominal')->all())
            ->toBe([300_000, 200_000, -200_000, 200_000]);
    });

    it('menahan tagihan selama KRS dalam revisi sampai disetujui ulang', function () {
        tarifKrs(['nominal' => 100_000]);
        $krs = setujuiKrs([kelasBerbayar(3)]);

        $krs = $this->krs->bukaRevisi($krs);
        $this->krs->tambahKelas($krs, kelasBerbayar(2));

        expect($krs->status)->toBe(KrsStatus::Draft)
            ->and($this->tagihanKrs->sinkronkan($krs->refresh())->total)->toBe(300_000);
    });

    it('menolak revisi di luar jendela KRS', function () {
        tarifKrs();
        $krs = setujuiKrs([kelasBerbayar(3)]);
        $this->term->update(['krs_perubahan_selesai' => Carbon::yesterday(), 'krs_selesai' => Carbon::yesterday()->subWeek()]);

        $this->krs->bukaRevisi($krs->refresh());
    })->throws(AturanAkademikException::class);

    it('menolak revisi rencana yang belum disetujui', function () {
        $this->krs->bukaRevisi($this->krs->bukaAtauAmbil($this->mahasiswa, $this->term));
    })->throws(AturanAkademikException::class);

    it('membuka revisi lewat layar mahasiswa', function () {
        $krs = setujuiKrs([kelasBerbayar(3)]);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get('/mahasiswa/krs')
            ->assertOk()
            ->assertSee(__('krs.revisi_tombol'));

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post('/mahasiswa/krs/revisi')
            ->assertRedirect()
            ->assertSessionHas('sukses', __('krs.revisi_dibuka'));

        expect($krs->refresh()->status)->toBe(KrsStatus::Draft);
    });
});

describe('batas pengurangan', function () {
    it('mengikuti tanggal yang dipilih konfigurasi', function () {
        $term = $this->term;
        $sebelum = $term->krs_selesai->copy()->subDay();
        $diAntara = $term->krs_selesai->copy()->addDay();

        config(['payment.krs.batas_pengurangan' => 'krs_selesai']);
        expect($this->tagihanKrs->mengurangiTagihan($term, $sebelum))->toBeTrue()
            ->and($this->tagihanKrs->mengurangiTagihan($term, $term->krs_selesai))->toBeTrue()
            ->and($this->tagihanKrs->mengurangiTagihan($term, $diAntara))->toBeFalse();

        config(['payment.krs.batas_pengurangan' => 'krs_perubahan_selesai']);
        expect($this->tagihanKrs->mengurangiTagihan($term, $diAntara))->toBeTrue()
            ->and($this->tagihanKrs->mengurangiTagihan($term, $term->krs_perubahan_selesai->copy()->addDay()))->toBeFalse();

        config(['payment.krs.batas_pengurangan' => 'tidak_pernah']);
        expect($this->tagihanKrs->mengurangiTagihan($term, $sebelum))->toBeFalse();
    });

    it('selalu mengurangi bila semester tidak menetapkan tanggalnya', function () {
        $this->term->update(['krs_selesai' => null, 'krs_perubahan_selesai' => null]);

        expect($this->tagihanKrs->mengurangiTagihan($this->term->refresh(), Carbon::today()))->toBeTrue();
    });
});

describe('gerbang KRS', function () {
    it('mengukur ambang hanya terhadap komponen tetap', function () {
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP Tetap', 'nominal' => 1_000_000, 'is_active' => true]);
        tarifKrs(['nominal' => 200_000]);
        app(PenerbitanTagihanService::class)->terbitkan($this->term);
        bayarTagihan(tagihanSemester(), 500_000);

        setujuiKrs([kelasBerbayar(3), kelasBerbayar(3)]);
        $tagihan = tagihanSemester();

        // 500.000 of 2.200.000 is 22% — but exactly half of what is owed for
        // being enrolled, and that is what the gate asks.
        expect($tagihan->total)->toBe(2_200_000)
            ->and($tagihan->pokokGerbangKrs())->toBe(1_000_000)
            ->and($tagihan->persenTerbayarPokok())->toBeLessThan(50.0)
            ->and($tagihan->memenuhiSyaratKrs())->toBeTrue();
    });

    it('tetap mengunci revisi bila komponen tetap belum mencapai ambang', function () {
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP Tetap', 'nominal' => 1_000_000, 'is_active' => true]);
        tarifKrs();
        $krs = setujuiKrs([kelasBerbayar(3)]);

        $krs = $this->krs->bukaRevisi($krs);

        expect(tagihanSemester()->memenuhiSyaratKrs())->toBeFalse()
            ->and(fn () => $this->krs->ajukan($krs))->toThrow(AturanAkademikException::class);
    });

    it('tidak mengunci revisi kampus yang hanya menagih per SKS', function () {
        tarifKrs();
        $krs = setujuiKrs([kelasBerbayar(3)]);

        $krs = $this->krs->bukaRevisi($krs);

        expect(tagihanSemester()->pokokGerbangKrs())->toBe(0)
            ->and($this->krs->ajukan($krs)->status)->toBe(KrsStatus::Diajukan);
    });

    it('membagi potongan secara proporsional antara komponen tetap dan KRS', function () {
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP Tetap', 'nominal' => 1_000_000, 'is_active' => true]);
        tarifKrs(['nominal' => 100_000]);
        app(PenerbitanTagihanService::class)->terbitkan($this->term);
        app(BeasiswaService::class)->tetapkan(skemaKrs(['persen' => 50]), $this->mahasiswa, $this->term);

        config(['academic.krs.requires_payment' => false]);
        setujuiKrs([kelasBerbayar(5), kelasBerbayar(5)]);
        config(['academic.krs.requires_payment' => true]);
        $tagihan = tagihanSemester();

        expect($tagihan->total)->toBe(1_000_000)
            ->and($tagihan->pokokGerbangKrs())->toBe(500_000);

        bayarTagihan($tagihan, 200_000);
        expect($tagihan->refresh()->memenuhiSyaratKrs())->toBeFalse();

        bayarTagihan($tagihan, 50_000);
        expect($tagihan->refresh()->memenuhiSyaratKrs())->toBeTrue();
    });

    it('membiarkan gerbang tagihan tanpa baris KRS tepat seperti sebelumnya', function () {
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP Tetap', 'nominal' => 1_000_000, 'is_active' => true]);
        app(PenerbitanTagihanService::class)->terbitkan($this->term);

        $tagihan = tagihanSemester();

        expect($tagihan->pokokGerbangKrs())->toBe($tagihan->pokok())
            ->and($tagihan->persenTerbayarGerbangKrs())->toBe($tagihan->persenTerbayarPokok());
    });
});

describe('potongan, beasiswa, dan denda', function () {
    it('menerapkan beasiswa persentase pada baris per SKS yang baru ditagih', function () {
        tarifKrs(['nominal' => 100_000]);
        app(BeasiswaService::class)->tetapkan(skemaKrs(['persen' => 50, 'komponen' => ['SKS']]), $this->mahasiswa, $this->term);

        $krs = setujuiKrs([kelasBerbayar(4)]);
        expect(tagihanSemester()->total)->toBe(200_000);

        revisiKrs($krs, tambah: [kelasBerbayar(2)]);
        expect(tagihanSemester()->total)->toBe(300_000)
            ->and(tagihanSemester()->totalPotongan())->toBe(300_000);
    });

    it('menghitung ulang beasiswa ketika koreksi mengurangi baris yang dicakupnya', function () {
        config(['payment.krs.batas_pengurangan' => 'krs_perubahan_selesai']);
        tarifKrs(['nominal' => 100_000]);
        app(BeasiswaService::class)->tetapkan(skemaKrs(['persen' => 100]), $this->mahasiswa, $this->term);
        $batal = kelasBerbayar(2);
        $krs = setujuiKrs([kelasBerbayar(3), $batal]);

        revisiKrs($krs, hapus: [$batal]);
        $tagihan = tagihanSemester();

        expect($tagihan->total)->toBe(0)
            ->and($tagihan->totalPotongan())->toBe(300_000)
            ->and($tagihan->status)->toBe(InvoiceStatus::Lunas);
    });

    it('tidak menelan pembayaran ketika koreksi menurunkan tagihan di bawah yang sudah dibayar', function () {
        config(['payment.krs.batas_pengurangan' => 'krs_perubahan_selesai']);
        tarifKrs(['nominal' => 100_000]);
        $batal = kelasBerbayar(2);
        $krs = setujuiKrs([kelasBerbayar(3), $batal]);
        bayarTagihan(tagihanSemester(), 500_000);

        revisiKrs($krs, hapus: [$batal]);
        $tagihan = tagihanSemester();

        expect($tagihan->total)->toBe(300_000)
            ->and($tagihan->terbayar)->toBe(500_000)
            ->and($tagihan->kelebihanBayar())->toBe(200_000)
            ->and($tagihan->status)->toBe(InvoiceStatus::Lunas)
            ->and(Pembayaran::where('tagihan_id', $tagihan->id)->sum('nominal'))->toBe(500_000);
    });

    it('mempertahankan denda yang sudah berjalan saat baris KRS ditambahkan', function () {
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP Tetap', 'nominal' => 1_000_000, 'is_active' => true]);
        tarifKrs(['nominal' => 100_000]);
        // The gate is not under test here; an unpaid fixed invoice would block approval.
        config(['academic.krs.requires_payment' => false]);
        app(PenerbitanTagihanService::class)->terbitkan($this->term);

        $tagihan = tagihanSemester();
        TagihanItem::create([
            'tagihan_id' => $tagihan->id,
            'jenis' => JenisItemTagihan::Denda,
            'nama' => __('keuangan.denda.nama_baris'),
            'nominal' => 50_000,
        ]);
        $tagihan->update(['denda' => 50_000, 'total' => 1_050_000]);

        setujuiKrs([kelasBerbayar(3)]);
        $tagihan = tagihanSemester();

        expect($tagihan->total)->toBe(1_350_000)
            ->and($tagihan->denda)->toBe(50_000)
            ->and($tagihan->pokok())->toBe(1_300_000)
            ->and($tagihan->pokokGerbangKrs())->toBe(1_000_000);
    });
});

describe('pembukuan', function () {
    it('membukukan penambahan dan koreksi per baris ke antrean akuntansi', function () {
        config([
            'akuntansi.driver' => Akuntansi::PALSU,
            'payment.krs.batas_pengurangan' => 'krs_perubahan_selesai',
        ]);
        Tarif::create(['komponen' => 'spp', 'nama' => 'SPP Tetap', 'nominal' => 1_000_000, 'is_active' => true]);
        tarifKrs(['nominal' => 100_000]);
        // The gate is not under test here; an unpaid fixed invoice would block approval.
        config(['academic.krs.requires_payment' => false]);
        app(PenerbitanTagihanService::class)->terbitkan($this->term);

        $batal = kelasBerbayar(2);
        $krs = setujuiKrs([kelasBerbayar(3), $batal]);
        revisiKrs($krs, hapus: [$batal]);

        $jurnal = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Jurnal->value)
            ->where('kunci_idempotensi', 'like', 'oa-krs-%')
            ->orderBy('id')
            ->get();
        $akun = config('akuntansi.akun');

        // Two charges (Dr Piutang), then the correction of one (Dr Pendapatan).
        expect($jurnal)->toHaveCount(3)
            ->and($jurnal->pluck('nominal')->all())->toBe([300_000, 200_000, 200_000])
            ->and($jurnal[0]->payload['entries'][0]['account_code'])->toBe($akun['piutang'])
            ->and($jurnal[2]->payload['entries'][0]['account_code'])->toBe($akun['pendapatan'])
            ->and($jurnal[2]->payload['entries'][1]['account_code'])->toBe($akun['piutang']);
    });
});

describe('layar matriks tarif', function () {
    beforeEach(function () {
        $this->staf = Staff::factory()->create();
        $this->staf->assignRole('keuangan');
    });

    it('menyimpan baris per SKS dan per mata kuliah', function () {
        $mk = kelasBerbayar()->mataKuliah;

        $this->actingAs($this->staf, 'staff')->post('/admin/tarif', [
            'komponen' => 'per_sks', 'nama' => 'Per SKS', 'nominal' => 150_000,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->staf, 'staff')->post('/admin/tarif', [
            'komponen' => 'mata_kuliah', 'nama' => 'Praktikum', 'nominal' => 300_000, 'mata_kuliah_id' => $mk->id,
        ])->assertSessionHasNoErrors();

        expect(Tarif::where('komponen', 'mata_kuliah')->sole()->mata_kuliah_id)->toBe($mk->id);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/tarif?simulasi_nim='.$this->mahasiswa->nim)
            ->assertOk()
            ->assertSee(__('keuangan.komponen.per_sks'))
            ->assertSee(__('keuangan.tarif.per_sks_satuan'))
            ->assertSee($mk->nama);
    });

    it('mewajibkan mata kuliah hanya pada komponen per mata kuliah', function () {
        $mk = kelasBerbayar()->mataKuliah;

        $this->actingAs($this->staf, 'staff')->post('/admin/tarif', [
            'komponen' => 'mata_kuliah', 'nama' => 'Praktikum', 'nominal' => 300_000,
        ])->assertSessionHasErrors('mata_kuliah_id');

        $this->actingAs($this->staf, 'staff')->post('/admin/tarif', [
            'komponen' => 'ukt', 'nama' => 'UKT', 'nominal' => 300_000, 'mata_kuliah_id' => $mk->id,
        ])->assertSessionHasErrors('mata_kuliah_id');

        $this->actingAs($this->staf, 'staff')->post('/admin/tarif', [
            'komponen' => 'bukan_komponen', 'nama' => 'X', 'nominal' => 1,
        ])->assertSessionHasErrors('komponen');

        expect(Tarif::count())->toBe(0);
    });
});

function skemaKrs(array $atribut = []): Beasiswa
{
    return Beasiswa::create([
        'kode' => 'BS-'.uniqid(),
        'nama' => 'Beasiswa Uji',
        'jenis' => JenisBeasiswa::Internal,
        'persen' => 100,
        'is_active' => true,
        ...$atribut,
    ]);
}

function bayarTagihan(Tagihan $tagihan, int $nominal): void
{
    Pembayaran::create([
        'tagihan_id' => $tagihan->id,
        'mahasiswa_id' => $tagihan->mahasiswa_id,
        'nomor_transaksi' => 'UJI-'.uniqid(),
        'gateway' => 'manual',
        'channel' => 'tunai',
        'nominal' => $nominal,
        'status' => PaymentStatus::Settlement,
        'paid_at' => now(),
    ]);

    app(PembayaranService::class)->hitungUlang($tagihan);
}
