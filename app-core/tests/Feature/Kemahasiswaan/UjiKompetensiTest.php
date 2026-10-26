<?php

declare(strict_types=1);

use App\DTOs\Kemahasiswaan\HasilUjiKompetensiData;
use App\Enums\HasilEvaluasi;
use App\Enums\HasilUjiKompetensi;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\Alumni;
use App\Models\Kemahasiswaan\EvaluasiStudi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\UjiKompetensi;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Staff;
use App\Notifications\Kemahasiswaan\KelulusanDitetapkan;
use App\Notifications\Kemahasiswaan\UjiKompetensiBelumLulus;
use App\Notifications\Kemahasiswaan\YudisiumMenungguUjiKompetensi;
use App\Services\Akademik\PenutupanSemesterService;
use App\Services\Feeder\FeederValidator;
use App\Services\Feeder\Mappers\AktivitasKuliahMapper;
use App\Services\Feeder\Mappers\LulusDoMapper;
use App\Services\Kemahasiswaan\EvaluasiStudiService;
use App\Services\Kemahasiswaan\SyaratWisudaService;
use App\Services\Kemahasiswaan\UjiKompetensiService;
use App\Services\Kemahasiswaan\YudisiumService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * "Menunggu Uji Kompetensi" (#31): health programmes graduate nobody on
 * coursework alone. Yudisium → waiting → Lulus once a passing result is
 * recorded, reported to PDDIKTI under a status the campus maps itself.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->lalu = TahunAkademik::factory()->term(2025, SemesterType::Genap)->create([
        'tanggal_mulai' => '2026-02-01',
        'tanggal_selesai' => '2026-07-31',
    ]);
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2027-01-31',
    ]);

    $this->prodi = Prodi::factory()->create([
        'sks_lulus' => 12,
        'wajib_tugas_akhir' => false,
        'wajib_ukom' => true,
    ]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->ukom = app(UjiKompetensiService::class);
    $this->yudisiumService = app(YudisiumService::class);
});

/** A student of the given programme who meets every graduation requirement. */
function calonUkom(?Prodi $prodi = null): Mahasiswa
{
    $test = test();
    $prodi ??= $test->prodi;

    $m = Mahasiswa::factory()->create(['prodi_id' => $prodi->id, 'status' => StudentStatus::Aktif]);

    $mk = MataKuliah::factory()->create(['prodi_id' => $prodi->id, 'sks' => 12]);
    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $test->lalu->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $prodi->id,
        'sks' => 12,
    ]);
    $krs = Krs::create([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => $test->lalu->id,
        'semester_ke' => 6,
        'batas_sks' => 24,
        'status' => 'disetujui',
    ]);
    $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 12]);
    Nilai::create([
        'krs_detail_id' => $detail->id,
        'kelas_kuliah_id' => $kelas->id,
        'mahasiswa_id' => $m->id,
        'nilai_angka' => 85,
        'nilai_huruf' => 'A',
        'bobot' => 4,
        'is_final' => true,
    ]);

    StatusMahasiswa::create([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => $test->lalu->id,
        'status' => StudentStatus::Aktif,
        'semester_ke' => 6,
        'sks_semester' => 12,
        'sks_kumulatif' => 108,
        'ips' => 3.5,
        'ipk' => 3.42,
        'is_final' => true,
    ]);

    return $m->fresh();
}

/** Confirms the student's yudisium through the real service, in the earlier term. */
function tetapkanUkom(Mahasiswa $m): Yudisium
{
    $y = test()->yudisiumService->ajukan($m, test()->lalu);
    $y = test()->yudisiumService->tetapkan($y, test()->staf, 'SK/YUD/001');

    // Ditetapkan pada akhir Agustus, supaya ujian bulan September jatuh
    // sesudahnya tanpa harus bermain-main dengan jam.
    $y->update(['tanggal_yudisium' => '2026-08-20']);

    return $y->fresh();
}

function hasilUkom(HasilUjiKompetensi $hasil, string $tanggal = '2026-09-10', ?string $sertifikat = null): HasilUjiKompetensiData
{
    return HasilUjiKompetensiData::fromArray([
        'tanggal_ujian' => $tanggal,
        'hasil' => $hasil->value,
        'nomor_sertifikat' => $sertifikat,
    ]);
}

describe('StudentStatus::MenungguUkom', function () {
    it('adalah kode lokal yang tidak dapat mengisi KRS dan bukan status akhir', function () {
        $s = StudentStatus::MenungguUkom;

        expect($s->kodeLokal())->toBeTrue()
            ->and(StudentStatus::Aktif->kodeLokal())->toBeFalse()
            ->and($s->canEnroll())->toBeFalse()
            ->and($s->isTerminal())->toBeFalse()
            ->and($s->label())->toBe('Menunggu Uji Kompetensi')
            ->and(StudentStatus::tidakDihitungMasaStudi())->toContain($s, StudentStatus::Cuti);
    });
});

describe('transisi status', function () {
    it('menahan lulusan prodi wajib ukom di Menunggu Uji Kompetensi saat yudisium ditetapkan', function () {
        Notification::fake();
        $m = calonUkom();

        $y = tetapkanUkom($m);

        expect($y->status)->toBe('ditetapkan')
            ->and($y->nomor_sk)->toBe('SK/YUD/001')
            ->and($y->tanggal_lulus)->toBeNull()
            ->and($m->fresh()->status)->toBe(StudentStatus::MenungguUkom)
            ->and(Alumni::where('mahasiswa_id', $m->id)->exists())->toBeFalse();

        Notification::assertSentTo($m, YudisiumMenungguUjiKompetensi::class);
        Notification::assertNotSentTo($m, KelulusanDitetapkan::class);
    });

    it('tetap meluluskan langsung pada prodi yang tidak mewajibkan ukom', function () {
        $biasa = Prodi::factory()->create(['sks_lulus' => 12, 'wajib_tugas_akhir' => false]);
        $m = calonUkom($biasa);

        $y = tetapkanUkom($m);

        expect($m->fresh()->status)->toBe(StudentStatus::Lulus)
            ->and($y->tanggal_lulus)->not->toBeNull();
    });

    it('meluluskan sesudah hasil ukom yang lulus dicatat, pada tanggal ujian', function () {
        Notification::fake();
        $m = calonUkom();
        tetapkanUkom($m);

        $uji = $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::Lulus, '2026-09-10', 'SK-UKOM-01'), $this->staf);

        $m->refresh();

        expect($uji->hasil)->toBe(HasilUjiKompetensi::Lulus)
            ->and($uji->nomor_sertifikat)->toBe('SK-UKOM-01')
            ->and($uji->tahun_akademik_id)->toBe($this->term->id)
            ->and($m->status)->toBe(StudentStatus::Lulus)
            ->and($m->yudisium->tanggal_lulus->toDateString())->toBe('2026-09-10')
            ->and(Alumni::where('mahasiswa_id', $m->id)->value('tahun_lulus'))->toBe(2026);

        Notification::assertSentTo($m, KelulusanDitetapkan::class);
    });

    it('mencatat hasil belum lulus tanpa mengubah status, dan memberi tahu', function () {
        Notification::fake();
        $m = calonUkom();
        tetapkanUkom($m);

        $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::TidakLulus), $this->staf);
        $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::TidakLulus, '2026-10-01'), $this->staf);

        expect($m->fresh()->status)->toBe(StudentStatus::MenungguUkom)
            ->and(UjiKompetensi::where('mahasiswa_id', $m->id)->count())->toBe(2);

        Notification::assertSentToTimes($m, UjiKompetensiBelumLulus::class, 2);
    });

    it('tidak memundurkan tanggal lulus ke sebelum yudisium', function () {
        $m = calonUkom();
        $y = tetapkanUkom($m);

        // Ujian diikuti sebelum yudisium; hasilnya baru dicatat sesudahnya.
        $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::Lulus, '2026-01-15'), $this->staf);

        expect($m->fresh()->yudisium->tanggal_lulus->toDateString())->toBe($y->tanggal_yudisium->toDateString());
    });

    it('menolak hasil untuk mahasiswa yang tidak sedang menunggu', function () {
        $m = calonUkom();
        tetapkanUkom($m);
        $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::Lulus), $this->staf);

        expect(fn () => $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::Lulus), $this->staf))
            ->toThrow(AturanAkademikException::class, 'tidak sedang menunggu');
    });

    it('menolak hasil untuk prodi yang tidak mewajibkan ukom', function () {
        $m = Mahasiswa::factory()->create(['status' => StudentStatus::MenungguUkom]);

        expect(fn () => $this->ukom->catatHasil($m, hasilUkom(HasilUjiKompetensi::Lulus), $this->staf))
            ->toThrow(AturanAkademikException::class, 'tidak mewajibkan');
    });

    it('menolak tanggal ujian di masa depan', function () {
        $m = calonUkom();
        tetapkanUkom($m);

        expect(fn () => $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::Lulus, now()->addDay()->toDateString()), $this->staf))
            ->toThrow(AturanAkademikException::class, 'masa depan');
    });

    it('menolak lulusSetelahUjiKompetensi bagi yang tidak menunggu', function () {
        $biasa = Prodi::factory()->create(['sks_lulus' => 12, 'wajib_tugas_akhir' => false]);
        $y = tetapkanUkom(calonUkom($biasa));

        expect(fn () => $this->yudisiumService->lulusSetelahUjiKompetensi($y, $this->staf, now()))
            ->toThrow(AturanAkademikException::class, 'tidak sedang menunggu');
    });

    it('mendaftar mahasiswa yang menunggu di layar yudisium', function () {
        $m = calonUkom();
        tetapkanUkom($m);
        $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::TidakLulus), $this->staf);

        $menunggu = $this->ukom->menunggu();

        expect($menunggu->pluck('id')->all())->toBe([$m->id])
            ->and($menunggu->first()->ujiKompetensi)->toHaveCount(1);

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.yudisium'))
            ->assertOk()
            ->assertSee('data-ukom="'.$m->nim.'"', false)
            ->assertSee('Belum Lulus');
    });

    it('mencatat hasil lewat rute staf', function () {
        $m = calonUkom();
        tetapkanUkom($m);

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.yudisium.ukom', $m), [
                'tanggal_ujian' => '2026-09-10',
                'hasil' => 'lulus',
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($m->fresh()->status)->toBe(StudentStatus::Lulus);
    });

    it('menolak pencatatan dari staf tanpa izin wisuda', function () {
        $m = calonUkom();
        tetapkanUkom($m);

        $this->actingAs(Staff::factory()->create(), 'staff')
            ->post(route('admin.yudisium.ukom', $m), ['tanggal_ujian' => '2026-09-10', 'hasil' => 'lulus'])
            ->assertForbidden();

        expect($m->fresh()->status)->toBe(StudentStatus::MenungguUkom);
    });
});

describe('catatan semester menunggu', function () {
    it('menulis satu catatan beku berstatus menunggu ukom untuk semester sesudah yudisium', function () {
        $m = calonUkom();
        tetapkanUkom($m);

        expect($this->ukom->catatSemesterMenunggu($this->term))->toBe(1)
            ->and($this->ukom->catatSemesterMenunggu($this->term))->toBe(0);

        $baris = StatusMahasiswa::where('mahasiswa_id', $m->id)->where('tahun_akademik_id', $this->term->id)->first();

        expect($baris->status)->toBe(StudentStatus::MenungguUkom)
            ->and($baris->is_final)->toBeTrue()
            ->and($baris->sks_semester)->toBe(0)
            ->and((float) $baris->ips)->toBe(0.0)
            ->and((float) $baris->ipk)->toBe(3.42)
            ->and($baris->sks_kumulatif)->toBe(108)
            ->and($baris->semester_ke)->toBe(7);
    });

    it('tidak menulis catatan untuk semester yudisium itu sendiri', function () {
        $m = calonUkom();
        tetapkanUkom($m);

        expect($this->ukom->catatSemesterMenunggu($this->lalu))->toBe(0);
    });

    it('ditulis saat semester ditutup', function () {
        $m = calonUkom();
        tetapkanUkom($m);

        $hasil = app(PenutupanSemesterService::class)->tutup($this->term, $this->staf);

        expect($hasil['menunggu_ukom'])->toBe(1)
            ->and(StatusMahasiswa::where('mahasiswa_id', $m->id)->where('tahun_akademik_id', $this->term->id)->value('status'))
            ->toBe(StudentStatus::MenungguUkom);
    });

    it('tidak dihitung sebagai semester tempuh oleh evaluasi studi', function () {
        // Batas masa studi 7 semester: tanpa pengecualian, semester menunggu
        // ini akan menjadi semester ke-7 dan memicu temuan "Batas Masa Studi".
        config([
            'academic.evaluasi.tahap' => [],
            'academic.evaluasi.masa_studi_maksimum' => 2,
        ]);

        $m = calonUkom();
        tetapkanUkom($m);
        $this->ukom->catatSemesterMenunggu($this->term);

        $hasil = app(EvaluasiStudiService::class)->jalankan($this->term);

        expect($hasil['temuan'])->toBe(0)
            ->and(EvaluasiStudi::where('mahasiswa_id', $m->id)->where('temuan', HasilEvaluasi::TidakMemenuhi->value)->exists())->toBeFalse();
    });
});

describe('Feeder', function () {
    it('mengirim AKM menunggu ukom dengan kode dari feeder_mappings, bukan kode lokal', function () {
        FeederMapping::create(['group' => 'status_mahasiswa', 'local_value' => 'U', 'feeder_code' => 'X9', 'feeder_label' => 'Menunggu Uji Kompetensi']);

        $m = calonUkom();
        tetapkanUkom($m);
        $this->ukom->catatSemesterMenunggu($this->term);

        $baris = StatusMahasiswa::with(['mahasiswa', 'tahunAkademik'])
            ->where('mahasiswa_id', $m->id)->where('tahun_akademik_id', $this->term->id)->first();

        $payload = app(AktivitasKuliahMapper::class)->payload($baris);

        expect($payload['id_status_mahasiswa'])->toBe('X9')
            ->and($payload['id_semester'])->toBe($this->term->kode)
            ->and($payload['sks_semester'])->toBe(0);
    });

    it('tidak mengirim kode lokal apa adanya bila belum dipetakan, dan validator menolaknya', function () {
        $m = calonUkom();
        $m->update(['feeder_registrasi_id' => 'reg-1']);
        tetapkanUkom($m);
        $this->ukom->catatSemesterMenunggu($this->term);

        $mapper = app(AktivitasKuliahMapper::class);
        $baris = $mapper->rows($this->term->kode)->firstWhere('mahasiswa_id', $m->id);

        expect($mapper->payload($baris))->not->toHaveKey('id_status_mahasiswa');

        $hasil = app(FeederValidator::class)->periksa('aktivitas_kuliah', $this->term->kode, $mapper);

        expect($hasil['error'])->toBe(1)
            ->and(FeederValidationIssue::where('batch_id', $hasil['batch'])->value('rule'))->toBe('status_belum_dipetakan');
    });

    it('menjadikan Lulus/DO terutang pada semester ujian yang lulus, dengan periode keluar semester itu', function () {
        FeederMapping::create(['group' => 'jenis_keluar', 'local_value' => 'L', 'feeder_code' => '1', 'feeder_label' => 'Lulus']);

        $m = calonUkom();
        $m->update(['feeder_registrasi_id' => 'reg-ukom']);
        tetapkanUkom($m);

        $mapper = app(LulusDoMapper::class);

        // Menunggu: belum Lulus, tidak terutang di semester mana pun.
        expect($mapper->rows($this->lalu->kode)->pluck('id'))->not->toContain($m->id);

        $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::Lulus, '2026-09-10'), $this->staf);

        $baris = $mapper->rows($this->term->kode)->firstWhere('id', $m->id);
        $payload = $mapper->payload($baris);

        expect($baris)->not->toBeNull()
            ->and($payload['id_periode_keluar'])->toBe($this->term->kode)
            ->and($payload['tanggal_keluar'])->toBe('2026-09-10')
            ->and($payload['nomor_sk_yudisium'])->toBe('SK/YUD/001');

        $hasil = app(FeederValidator::class)->periksa('lulus_do', $this->term->kode, $mapper);

        expect($hasil['error'])->toBe(0);
    });

    it('menolak Lulus untuk prodi wajib ukom tanpa hasil ukom yang lulus', function () {
        FeederMapping::create(['group' => 'jenis_keluar', 'local_value' => 'L', 'feeder_code' => '1', 'feeder_label' => 'Lulus']);

        // Prodi baru ditandai wajib ukom sesudah lulusannya ditetapkan Lulus.
        $biasa = Prodi::factory()->create(['sks_lulus' => 12, 'wajib_tugas_akhir' => false]);
        $m = calonUkom($biasa);
        $m->update(['feeder_registrasi_id' => 'reg-2']);
        tetapkanUkom($m);
        $biasa->update(['wajib_ukom' => true]);

        $hasil = app(FeederValidator::class)->periksa('lulus_do', $this->lalu->kode, app(LulusDoMapper::class));

        expect($hasil['error'])->toBe(1)
            ->and(FeederValidationIssue::where('batch_id', $hasil['batch'])->value('rule'))->toBe('ukom_belum_lulus');
    });
});

describe('syarat wisuda', function () {
    it('mewajibkan lulus ukom pada prodi wajib ukom saja', function () {
        $m = calonUkom();
        tetapkanUkom($m);

        $syarat = app(SyaratWisudaService::class);

        expect($syarat->periksa($m->fresh())->memenuhi())->toBeFalse()
            ->and($syarat->periksa($m->fresh())->belumTerpenuhi())->toBe(['Lulus uji kompetensi']);

        $this->ukom->catatHasil($m->fresh(), hasilUkom(HasilUjiKompetensi::Lulus), $this->staf);

        expect($syarat->periksa($m->fresh())->memenuhi())->toBeTrue()
            ->and($syarat->periksa($m->fresh())->baris('ukom')['keterangan'])->toContain('Lulus');

        $biasa = Prodi::factory()->create(['sks_lulus' => 12, 'wajib_tugas_akhir' => false]);
        $lain = calonUkom($biasa);
        tetapkanUkom($lain);

        expect($syarat->periksa($lain->fresh())->baris('ukom'))->toBeNull();
    });
});
