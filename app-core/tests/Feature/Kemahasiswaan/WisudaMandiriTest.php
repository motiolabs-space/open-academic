<?php

declare(strict_types=1);

use App\DTOs\Keuangan\AturanDenda;
use App\Enums\BentukDenda;
use App\Enums\InvoiceStatus;
use App\Enums\JenisItemTagihan;
use App\Enums\PeriodeDenda;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Notifications\Kemahasiswaan\PendaftaranWisudaDibatalkan;
use App\Notifications\Kemahasiswaan\PendaftaranWisudaDiterima;
use App\Services\Kemahasiswaan\SyaratWisudaService;
use App\Services\Kemahasiswaan\WisudaService;
use App\Services\Keuangan\DendaService;
use App\Services\Keuangan\PembayaranService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Graduates registering themselves for a ceremony (#30), and the single
 * checklist both they and the office are held to.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create();

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->periode = WisudaPeriode::create([
        'nama' => 'Wisuda Periode II 2026',
        'tanggal' => now()->addMonth()->toDateString(),
        'lokasi' => 'Gedung Serbaguna',
        'is_pendaftaran_dibuka' => true,
    ]);

    $this->wisuda = app(WisudaService::class);
    $this->syarat = app(SyaratWisudaService::class);
});

/** A student with a yudisium in the given state — confirmed unless told otherwise. */
function wisudawan(string $status = 'ditetapkan'): Mahasiswa
{
    $m = Mahasiswa::factory()->create(['prodi_id' => test()->prodi->id]);

    Yudisium::create([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'total_sks' => 144,
        'ipk' => 3.40,
        'status' => $status,
        'tanggal_yudisium' => now()->subWeek(),
        'tanggal_lulus' => now()->subWeek(),
    ]);

    return $m->fresh();
}

/** An invoice for the student; fully paid unless told otherwise. */
function tagihanWisudawan(Mahasiswa $m, int $total = 5_000_000, ?int $terbayar = null, ?string $jatuhTempo = null): Tagihan
{
    $terbayar ??= $total;

    $tagihan = Tagihan::create([
        'nomor' => 'INV/'.uniqid(),
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'keterangan' => 'Biaya kuliah',
        'total' => $total,
        'terbayar' => $terbayar,
        'status' => InvoiceStatus::fromAmounts($total, $terbayar),
        'jatuh_tempo' => $jatuhTempo ?? now()->addMonth()->toDateString(),
    ]);

    TagihanItem::create([
        'tagihan_id' => $tagihan->id,
        'jenis' => JenisItemTagihan::Tagihan,
        'nama' => 'UKT',
        'nominal' => $total,
    ]);

    return $tagihan->fresh();
}

describe('SyaratWisudaService', function () {
    it('meloloskan lulusan yang yudisiumnya ditetapkan dan bebas tagihan', function () {
        $m = wisudawan();
        tagihanWisudawan($m);

        $syarat = $this->syarat->periksa($m);

        expect($syarat->memenuhi())->toBeTrue()
            ->and($syarat->baris('yudisium')['terpenuhi'])->toBeTrue()
            ->and($syarat->baris('keuangan')['terpenuhi'])->toBeTrue();
    });

    it('menolak yudisium yang masih diajukan dan yang belum ada', function () {
        $diajukan = $this->syarat->periksa(wisudawan('diajukan'));
        $tanpa = $this->syarat->periksa(Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]));

        expect($diajukan->memenuhi())->toBeFalse()
            ->and($diajukan->baris('yudisium')['keterangan'])->toContain('belum ditetapkan')
            ->and($tanpa->memenuhi())->toBeFalse()
            ->and($tanpa->baris('yudisium')['keterangan'])->toBe('Belum ada yudisium');
    });

    it('menghitung tunggakan sebagai syarat wajib dengan nominal sisanya', function () {
        $m = wisudawan();
        tagihanWisudawan($m, 5_000_000, 3_500_000);

        $syarat = $this->syarat->periksa($m);

        expect($syarat->memenuhi())->toBeFalse()
            ->and($syarat->belumTerpenuhi())->toBe(['Bebas tanggungan keuangan'])
            ->and($syarat->baris('keuangan')['keterangan'])->toContain('Rp1.500.000');
    });

    it('menghitung denda yang belum dibebaskan sebagai tunggakan, dan yang dibebaskan tidak', function () {
        // Pokok lunas, tetapi dibayar sesudah jatuh tempo: denda tiga hari
        // tersisa di tagihan. Itu tunggakan sungguhan — sampai bagian keuangan
        // membebaskannya, dan sesudah itu tidak lagi.
        $m = wisudawan();
        $tagihan = tagihanWisudawan($m, 5_000_000, 0, now()->subDays(3)->toDateString());

        app(DendaService::class)->terapkan($tagihan, new AturanDenda(
            aktif: true,
            bentuk: BentukDenda::Nominal,
            periode: PeriodeDenda::Hari,
            nominal: 10_000,
            persen: 1,
            maksimum: 0,
            tenggangHari: 0,
        ), now());

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');
        app(PembayaranService::class)->catatManual($tagihan->fresh(), 5_000_000, $keuangan);

        $tagihan->refresh();
        $sebelum = $this->syarat->periksa($m);

        expect((int) $tagihan->denda)->toBe(30_000)
            ->and($sebelum->memenuhi())->toBeFalse()
            ->and($sebelum->baris('keuangan')['keterangan'])->toContain('Rp30.000');

        app(DendaService::class)->bebaskan($tagihan, $keuangan, 'Gangguan sistem bank pada hari jatuh tempo.');

        expect($this->syarat->periksa($m->fresh())->memenuhi())->toBeTrue();
    });

    it('menampilkan kesiapan PIN sebagai informasi yang tidak menghalangi', function () {
        // Mahasiswa pabrik tidak punya NIK, registrasi Feeder, maupun SK:
        // daftar periksa PIN pasti belum siap.
        $syarat = $this->syarat->periksa(wisudawan());
        $pin = $syarat->baris('pin');

        expect($pin['wajib'])->toBeFalse()
            ->and($pin['terpenuhi'])->toBeFalse()
            ->and($syarat->memenuhi())->toBeTrue()
            ->and($syarat->belumTerpenuhi())->toBe([]);
    });

    it('memberi hasil yang sama untuk satu dan banyak mahasiswa', function () {
        $a = wisudawan();
        $b = wisudawan();
        tagihanWisudawan($b, 1_000_000, 0);

        $banyak = $this->syarat->periksaBanyak(collect([$a, $b]));

        expect($banyak[$a->id]->rincian)->toEqual($this->syarat->periksa($a)->rincian)
            ->and($banyak[$b->id]->rincian)->toEqual($this->syarat->periksa($b)->rincian)
            ->and($banyak[$b->id]->memenuhi())->toBeFalse();
    });

    it('mengembalikan koleksi kosong untuk masukan kosong', function () {
        expect($this->syarat->periksaBanyak(collect()))->toBeEmpty();
    });
});

describe('WisudaService — jalur mandiri', function () {
    it('mendaftarkan mahasiswa yang memenuhi syarat dan memberi tahu', function () {
        Notification::fake();
        $m = wisudawan();

        $peserta = $this->wisuda->daftarSendiri($this->periode, $m);

        expect($peserta->nomor_urut)->toBe(1)
            ->and($peserta->yudisium->mahasiswa_id)->toBe($m->id);

        Notification::assertSentTo($m, PendaftaranWisudaDiterima::class);
    });

    it('menolak mahasiswa tanpa yudisium', function () {
        $m = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        expect(fn () => $this->wisuda->daftarSendiri($this->periode, $m))
            ->toThrow(AturanAkademikException::class, 'belum memiliki yudisium');
    });

    it('menolak mahasiswa yang masih menunggak — di jalur staf juga', function () {
        $m = wisudawan();
        tagihanWisudawan($m, 5_000_000, 0);

        expect(fn () => $this->wisuda->daftarSendiri($this->periode, $m))
            ->toThrow(AturanAkademikException::class, 'Bebas tanggungan keuangan')
            ->and(fn () => $this->wisuda->daftarkan($this->periode, $m->yudisium))
            ->toThrow(AturanAkademikException::class, 'Bebas tanggungan keuangan');

        expect(WisudaPeserta::count())->toBe(0);
    });

    it('melewati yang menunggak pada pendaftaran massal dan melaporkannya', function () {
        $lunas = wisudawan();
        $menunggak = wisudawan();
        tagihanWisudawan($menunggak, 2_000_000, 0);

        $hasil = $this->wisuda->daftarkanMassal($this->periode);

        expect($hasil['didaftarkan'])->toBe(1)
            ->and($hasil['gagal'])->toHaveCount(1)
            ->and($hasil['gagal']->first())->toContain($menunggak->nama)
            ->and($this->wisuda->pesertaDari($this->periode, $lunas))->not->toBeNull();
    });

    it('membatalkan pendaftaran sendiri selama periode dibuka, dan memberi tahu', function () {
        Notification::fake();
        $m = wisudawan();
        $this->wisuda->daftarSendiri($this->periode, $m);

        $this->wisuda->batalkanSendiri($this->periode, $m);

        expect($this->wisuda->pesertaDari($this->periode, $m))->toBeNull();
        Notification::assertSentTo($m, PendaftaranWisudaDibatalkan::class);
    });

    it('menolak pembatalan sendiri sesudah periode ditutup', function () {
        $m = wisudawan();
        $this->wisuda->daftarSendiri($this->periode, $m);
        $this->wisuda->tutupPendaftaran($this->periode);

        expect(fn () => $this->wisuda->batalkanSendiri($this->periode->fresh(), $m))
            ->toThrow(AturanAkademikException::class, 'sudah ditutup');

        expect($this->wisuda->pesertaDari($this->periode, $m))->not->toBeNull();
    });

    it('menolak membatalkan pendaftaran yang tidak ada', function () {
        expect(fn () => $this->wisuda->batalkanSendiri($this->periode, wisudawan()))
            ->toThrow(AturanAkademikException::class, 'tidak terdaftar');
    });

    it('tidak memberi nomor urut yang sudah dipakai sesudah ada yang mundur', function () {
        $a = wisudawan();
        $b = wisudawan();
        $c = wisudawan();

        $this->wisuda->daftarSendiri($this->periode, $a);
        $this->wisuda->daftarSendiri($this->periode, $b);
        $this->wisuda->batalkanSendiri($this->periode, $a);

        // Hitungan peserta kini 1, tetapi nomor 2 sudah dipegang $b.
        $baru = $this->wisuda->daftarSendiri($this->periode, $c);

        expect($baru->nomor_urut)->toBe(3);
    });

    it('mencatat keluarnya peserta di jejak audit periode', function () {
        $m = wisudawan();
        $this->wisuda->daftarSendiri($this->periode, $m);
        $this->wisuda->batalkanSendiri($this->periode, $m);

        expect(LogAktivitas::query()
            ->where('subject_type', $this->periode->getMorphClass())
            ->where('subject_id', $this->periode->id)
            ->where('event', 'participant_removed')
            ->exists())->toBeTrue();
    });

    it('hanya menawarkan periode yang dibuka dan belum lewat', function () {
        WisudaPeriode::create(['nama' => 'Ditutup', 'tanggal' => now()->addMonth(), 'is_pendaftaran_dibuka' => false]);
        WisudaPeriode::create(['nama' => 'Sudah lewat', 'tanggal' => now()->subDay(), 'is_pendaftaran_dibuka' => true]);

        expect($this->wisuda->periodeTerbuka()->pluck('nama')->all())->toBe(['Wisuda Periode II 2026']);
    });
});

describe('WisudaPesertaPolicy', function () {
    it('mengizinkan mahasiswa mendaftarkan dirinya sendiri saja', function () {
        $saya = wisudawan();
        $lain = wisudawan();

        expect(Gate::forUser($saya)->allows('create', [WisudaPeserta::class, $saya]))->toBeTrue()
            ->and(Gate::forUser($saya)->allows('create', [WisudaPeserta::class, $lain]))->toBeFalse()
            ->and(Gate::forUser($this->staf)->allows('create', [WisudaPeserta::class, $lain]))->toBeTrue()
            ->and(Gate::forUser(Dosen::factory()->create())->allows('create', [WisudaPeserta::class, $lain]))->toBeFalse();
    });

    it('mengizinkan mahasiswa membatalkan miliknya saja, selama periode dibuka', function () {
        $saya = wisudawan();
        $lain = wisudawan();
        $peserta = $this->wisuda->daftarSendiri($this->periode, $saya);

        expect(Gate::forUser($saya)->allows('delete', $peserta))->toBeTrue()
            ->and(Gate::forUser($lain)->allows('delete', $peserta))->toBeFalse()
            ->and(Gate::forUser($this->staf)->allows('delete', $peserta))->toBeTrue();

        $this->wisuda->tutupPendaftaran($this->periode);

        expect(Gate::forUser($saya)->allows('delete', $peserta->fresh()))->toBeFalse()
            ->and(Gate::forUser($this->staf)->allows('delete', $peserta->fresh()))->toBeTrue();
    });

    it('tidak mengizinkan siapa pun mengeluarkan peserta bernomor ijazah', function () {
        $saya = wisudawan();
        $peserta = $this->wisuda->daftarSendiri($this->periode, $saya);
        $peserta->update(['nomor_ijazah' => '0012345678901234']);

        expect(Gate::forUser($saya)->allows('delete', $peserta))->toBeFalse()
            ->and(Gate::forUser($this->staf)->allows('delete', $peserta))->toBeFalse();
    });

    it('menolak staf tanpa izin wisuda', function () {
        $tanpaIzin = Staff::factory()->create();

        expect(Gate::forUser($tanpaIzin)->allows('viewAny', WisudaPeserta::class))->toBeFalse()
            ->and(Gate::forUser($tanpaIzin)->allows('create', [WisudaPeserta::class, wisudawan()]))->toBeFalse();
    });
});

describe('layar mahasiswa', function () {
    it('menampilkan daftar periksa dan tombol daftar yang nonaktif bila menunggak', function () {
        $m = wisudawan();
        tagihanWisudawan($m, 5_000_000, 0);

        $this->actingAs($m, 'mahasiswa')
            ->get(route('mahasiswa.wisuda'))
            ->assertOk()
            ->assertSee('Wisuda Periode II 2026')
            ->assertSee('data-syarat="keuangan" data-terpenuhi="tidak"', false)
            ->assertSee('data-wisuda="belum-memenuhi"', false)
            ->assertSee('disabled', false);
    });

    it('mendaftarkan dan membatalkan lewat rute mahasiswa', function () {
        $m = wisudawan();

        $this->actingAs($m, 'mahasiswa')
            ->post(route('mahasiswa.wisuda.daftar', $this->periode))
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($this->wisuda->pesertaDari($this->periode, $m))->not->toBeNull();

        $this->actingAs($m, 'mahasiswa')
            ->get(route('mahasiswa.wisuda'))
            ->assertSee('data-wisuda="terdaftar"', false)
            ->assertSee('Batalkan Pendaftaran');

        $this->actingAs($m, 'mahasiswa')
            ->delete(route('mahasiswa.wisuda.batal', $this->periode))
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($this->wisuda->pesertaDari($this->periode, $m))->toBeNull();
    });

    it('memantulkan penolakan syarat sebagai pesan, bukan galat', function () {
        $m = wisudawan('diajukan');

        $this->actingAs($m, 'mahasiswa')
            ->from(route('mahasiswa.wisuda'))
            ->post(route('mahasiswa.wisuda.daftar', $this->periode))
            ->assertRedirect(route('mahasiswa.wisuda'))
            ->assertSessionHas('galat');

        expect(WisudaPeserta::count())->toBe(0);
    });

    it('membalas 404 bila mahasiswa membatalkan periode yang tidak diikutinya', function () {
        $saya = wisudawan();
        $this->wisuda->daftarSendiri($this->periode, wisudawan());

        $this->actingAs($saya, 'mahasiswa')
            ->delete(route('mahasiswa.wisuda.batal', $this->periode))
            ->assertNotFound();

        expect(WisudaPeserta::count())->toBe(1);
    });

    it('menolak pembatalan lewat rute sesudah periode ditutup', function () {
        $m = wisudawan();
        $this->wisuda->daftarSendiri($this->periode, $m);
        $this->wisuda->tutupPendaftaran($this->periode);

        $this->actingAs($m, 'mahasiswa')
            ->delete(route('mahasiswa.wisuda.batal', $this->periode))
            ->assertForbidden();
    });

    it('menandai syarat yang kurang di antrean staf', function () {
        $m = wisudawan();
        tagihanWisudawan($m, 5_000_000, 0);

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.wisuda'))
            ->assertOk()
            ->assertSee('data-syarat-wisuda="'.$m->nim.'"', false)
            ->assertSee('1 belum');
    });
});
