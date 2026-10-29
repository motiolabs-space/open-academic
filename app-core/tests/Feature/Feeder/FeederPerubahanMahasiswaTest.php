<?php

declare(strict_types=1);

use App\Enums\FeederSyncStatus;
use App\Enums\Gender;
use App\Enums\JalurPerubahanFeeder;
use App\Enums\JenisPerubahanFeeder;
use App\Enums\SemesterType;
use App\Enums\StatusPerubahanFeeder;
use App\Enums\StudentStatus;
use App\Exceptions\FeederException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederChangeRequest;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederPerubahanService;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use Database\Seeders\Demo\IntegrasiSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * Roadmap #27: PDDIKTI locks six identity columns once the biodata is
 * accepted and seven exit columns once Lulus/DO is. A change to one of them
 * here is recorded as a PDM/PJK request the moment it happens, the biodata
 * is not re-sent while the request is open, and only what the web service
 * accepts is sent through it.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);

    $this->seed(IntegrasiSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => 'IF', 'kode_pddikti' => 'a1b2c3d4']);

    $this->sync = app(FeederSyncService::class);
    $this->validator = app(FeederValidator::class);
    $this->perubahan = app(FeederPerubahanService::class);
    $this->staf = Staff::factory()->create();
});

function mhsTersinkron(array $atribut = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'nim' => '2201001',
        'nama' => 'Rani Puspita',
        'nama_ibu' => 'Siti Aminah',
        'nik' => '3201234567890123',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2004-05-12',
        'jenis_kelamin' => Gender::Perempuan,
        'agama_kode' => '1',
        // Entered this term: the biodata mapper only re-reads students of the
        // term being reported (or never-sent ones), so this is the row set
        // the validator actually sees.
        'term_masuk' => test()->term->kode,
        'feeder_id' => 'mhs-1',
        'feeder_registrasi_id' => 'reg-1',
    ], $atribut));
}

function periksaMahasiswa(): array
{
    return test()->validator->periksa('mahasiswa', test()->term->kode, app(FeederSyncService::MAPPERS['mahasiswa']));
}

describe('mencatat perubahan kolom terkunci', function () {
    it('mencatat PDM lewat PDDIKTI admin begitu nama mahasiswa yang sudah tersinkron berubah', function () {
        $m = mhsTersinkron();

        $m->update(['nama' => 'Rani Puspitasari', 'telepon' => '0812']);

        $p = FeederChangeRequest::sole();

        expect($p->jenis)->toBe(JenisPerubahanFeeder::Pdm)
            ->and($p->jalur)->toBe(JalurPerubahanFeeder::PddiktiAdmin)
            ->and($p->status)->toBe(StatusPerubahanFeeder::Menunggu)
            ->and($p->kolom)->toBe('nama_mahasiswa')
            ->and($p->nilai_lama)->toBe('Rani Puspita')
            ->and($p->nilai_baru)->toBe('Rani Puspitasari')
            ->and($p->mahasiswa_id)->toBe($m->id)
            ->and(LogAktivitas::where('subject_type', $p->getMorphClass())->where('subject_id', $p->id)->exists())->toBeTrue();
    });

    it('mencatat satu permintaan per kolom, dengan tanggal dalam Y-m-d', function () {
        $m = mhsTersinkron();

        $m->update(['tempat_lahir' => 'Cimahi', 'tanggal_lahir' => '2004-05-21']);

        expect(FeederChangeRequest::pluck('nilai_baru', 'kolom')->all())->toBe([
            'tempat_lahir' => 'Cimahi',
            'tanggal_lahir' => '2004-05-21',
        ])->and(FeederChangeRequest::where('kolom', 'tanggal_lahir')->sole()->nilai_lama)->toBe('2004-05-12');
    });

    it('tidak mencatat apa pun untuk mahasiswa yang belum pernah terkirim', function () {
        $m = mhsTersinkron(['feeder_id' => null, 'feeder_registrasi_id' => null]);

        $m->update(['nama' => 'Nama Baru', 'nim' => '2201099']);

        expect(FeederChangeRequest::count())->toBe(0);
    });

    it('mencatat NIM dan periode masuk sebagai PDM yang dapat lewat web service', function () {
        $m = mhsTersinkron();

        $m->update(['nim' => '2201002', 'term_masuk' => '20222']);

        expect(FeederChangeRequest::jalur(JalurPerubahanFeeder::WebService)->pluck('kolom')->sort()->values()->all())
            ->toBe(['id_periode_masuk', 'nim']);
    });

    it('menyunting permintaan yang masih terbuka alih-alih menggandakannya, dan mencabutnya bila nilainya dikembalikan', function () {
        $m = mhsTersinkron();

        $m->update(['nama' => 'Rani P.']);
        $m->update(['nama' => 'Rani Puspitasari']);

        expect(FeederChangeRequest::count())->toBe(1)
            ->and(FeederChangeRequest::sole()->nilai_baru)->toBe('Rani Puspitasari');

        $m->update(['nama' => 'Rani Puspita']);

        expect(FeederChangeRequest::count())->toBe(0)
            ->and(FeederChangeRequest::withTrashed()->count())->toBe(1);
    });

    it('mencatat PJK begitu IPK atau periode keluar pada yudisium mahasiswa yang Lulus/DO-nya sudah terkirim berubah', function () {
        $m = mhsTersinkron(['status' => StudentStatus::Lulus, 'feeder_keluar_id' => 'reg-1']);
        $genap = TahunAkademik::factory()->term(2026, SemesterType::Genap)->create();
        $y = Yudisium::create([
            'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'total_sks' => 144, 'ipk' => 3.45,
            'tanggal_lulus' => '2026-12-10', 'status' => 'ditetapkan',
        ]);

        $y->update(['ipk' => 3.54, 'tahun_akademik_id' => $genap->id]);

        $p = FeederChangeRequest::where('jenis', JenisPerubahanFeeder::Pjk->value)->get()->keyBy('kolom');

        expect($p->keys()->sort()->values()->all())->toBe(['id_periode_keluar', 'ipk'])
            ->and($p['ipk']->nilai_lama)->toBe('3.45')
            ->and($p['ipk']->nilai_baru)->toBe('3.54')
            ->and($p['id_periode_keluar']->nilai_lama)->toBe('20261')
            ->and($p['id_periode_keluar']->nilai_baru)->toBe('20262');
    });

    it('tidak mencatat PJK bila Lulus/DO belum pernah terkirim', function () {
        $m = mhsTersinkron(['status' => StudentStatus::Lulus]);
        $y = Yudisium::create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'total_sks' => 144, 'ipk' => 3.45, 'status' => 'ditetapkan']);

        $y->update(['ipk' => 3.60]);

        expect(FeederChangeRequest::count())->toBe(0);
    });
});

describe('validator menahan pengiriman ulang', function () {
    it('menolak biodata mahasiswa selama ada PDM lewat PDDIKTI admin yang terbuka, menyebut kolomnya', function () {
        $m = mhsTersinkron();
        $m->update(['nama' => 'Rani Puspitasari', 'nama_ibu' => 'Siti Aminah Lubis']);

        $hasil = periksaMahasiswa();
        $isu = FeederValidationIssue::where('rule', 'kolom_terkunci_berubah')->sole();

        expect($hasil['error'])->toBe(1)
            ->and($isu->message)->toContain('Nama')->toContain('Nama ibu kandung')->toContain('PDDIKTI admin');

        expect(fn () => $this->sync->sinkronkan('mahasiswa', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        expect($this->fake->dipanggil('UpdateBiodataMahasiswa'))->toBeFalse();
    });

    it('melepas biodata begitu permintaannya ditandai selesai', function () {
        $m = mhsTersinkron();
        $m->update(['nama' => 'Rani Puspitasari']);

        $this->perubahan->selesaikan(FeederChangeRequest::sole(), $this->staf);

        expect(periksaMahasiswa()['error'])->toBe(0)
            ->and(FeederChangeRequest::sole()->selesai_at)->not->toBeNull();
    });

    it('tidak menahan biodata untuk kolom yang dapat lewat web service', function () {
        $m = mhsTersinkron();
        $m->update(['nim' => '2201002']);

        $hasil = periksaMahasiswa();

        expect($hasil['diperiksa'])->toBe(1)
            ->and($hasil['error'])->toBe(0);
    });

    it('menolak catatan Lulus/DO selama ada PJK yang terbuka', function () {
        $m = mhsTersinkron(['status' => StudentStatus::Lulus, 'feeder_keluar_id' => 'reg-1']);
        StatusMahasiswa::create([
            'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'status' => 'A', 'semester_ke' => 8,
        ]);
        $y = Yudisium::create([
            'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'total_sks' => 144, 'ipk' => 3.45,
            'tanggal_lulus' => '2026-12-10', 'nomor_sk' => 'SK/1', 'status' => 'ditetapkan',
        ]);
        $y->update(['tanggal_lulus' => '2026-12-12']);

        $hasil = $this->validator->periksa('lulus_do', $this->term->kode, app(FeederSyncService::MAPPERS['lulus_do']));

        expect($hasil['error'])->toBe(1)
            ->and(FeederValidationIssue::where('rule', 'kolom_terkunci_berubah')->sole()->message)->toContain('Tanggal keluar')->toContain('PJK');
    });
});

describe('web service untuk yang diizinkan', function () {
    it('mengirim UpdateRiwayatPendidikanMahasiswa berkunci id_registrasi_mahasiswa dan menutup permintaannya', function () {
        $m = mhsTersinkron();
        $m->update(['nim' => '2201002']);
        $p = FeederChangeRequest::sole();

        $this->perubahan->kirim($p, $this->staf, $this->term);

        $panggilan = collect($this->fake->riwayat)->firstWhere('act', 'UpdateRiwayatPendidikanMahasiswa');
        $log = FeederSyncLog::where('action', 'UpdateRiwayatPendidikanMahasiswa')->sole();

        expect($panggilan['payload']['key'])->toBe(['id_registrasi_mahasiswa' => 'reg-1'])
            ->and($panggilan['payload']['record'])->toMatchArray(['nim' => '2201002', 'id_prodi' => 'a1b2c3d4', 'id_periode_masuk' => '20261'])
            ->and($log->status)->toBe(FeederSyncStatus::Success)
            ->and($log->entity)->toBe('riwayat_pendidikan')
            ->and($p->fresh()->status)->toBe(StatusPerubahanFeeder::Selesai)
            ->and($p->fresh()->dikirim_at)->not->toBeNull();
    });

    it('menyimpan penolakan Feeder pada permintaan dan membiarkannya terbuka untuk diajukan lewat PDDIKTI admin', function () {
        $this->fake->tolak('UpdateRiwayatPendidikanMahasiswa', 4, 'Periode pelaporan sudah ditutup');
        $m = mhsTersinkron();
        $m->update(['nim' => '2201002']);
        $p = FeederChangeRequest::sole();

        $this->perubahan->kirim($p, $this->staf, $this->term);

        expect($p->fresh()->status)->toBe(StatusPerubahanFeeder::Menunggu)
            ->and($p->fresh()->error_message)->toBe('Periode pelaporan sudah ditutup')
            ->and(FeederSyncLog::where('action', 'UpdateRiwayatPendidikanMahasiswa')->sole()->status)->toBe(FeederSyncStatus::Failed);
    });

    it('menolak mengirim kolom yang hanya diterima PDDIKTI admin', function () {
        $m = mhsTersinkron();
        $m->update(['nama' => 'Rani Puspitasari']);

        expect(fn () => $this->perubahan->kirim(FeederChangeRequest::sole(), $this->staf, $this->term))
            ->toThrow(FeederException::class, 'PDDIKTI admin');

        expect($this->fake->riwayat)->toBe([]);
    });
});

describe('konsol /admin/feeder', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        TahunAkademik::query()->update(['tanggal_mulai' => now()->subWeeks(8), 'tanggal_selesai' => now()->addWeeks(8)]);
        $this->operator = Staff::factory()->create();
        $this->operator->assignRole('operator-pddikti');
    });

    it('menampilkan permintaan sebagai harus diajukan lewat PDDIKTI admin, lalu mengikuti diajukan → selesai', function () {
        $m = mhsTersinkron();
        $m->update(['nama' => 'Rani Puspitasari']);
        $p = FeederChangeRequest::sole();

        $html = $this->actingAs($this->operator, 'staff')->get('/admin/feeder')->assertOk()->getContent();

        expect($html)->toContain('Harus diajukan lewat PDDIKTI admin')
            ->toContain('Rani Puspitasari')
            ->toContain('Belum diajukan')
            ->toContain('2201001');

        $this->actingAs($this->operator, 'staff')
            ->post("/admin/feeder/perubahan/{$p->uuid}/ajukan", ['nomor_surat' => '123/PDM/2026'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($p->fresh()->status)->toBe(StatusPerubahanFeeder::Diajukan)
            ->and($p->fresh()->nomor_surat)->toBe('123/PDM/2026')
            ->and($p->fresh()->diproses_by_staff_id)->toBe($this->operator->id);

        $this->actingAs($this->operator, 'staff')
            ->post("/admin/feeder/perubahan/{$p->uuid}/selesai")
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($p->fresh()->status)->toBe(StatusPerubahanFeeder::Selesai);

        $html = $this->actingAs($this->operator, 'staff')->get('/admin/feeder')->assertOk()->getContent();
        expect($html)->toContain('1 selesai');
    });

    it('mengirim lewat web service dari konsol untuk kolom yang diizinkan', function () {
        $m = mhsTersinkron();
        $m->update(['nim' => '2201002']);
        $p = FeederChangeRequest::sole();

        $html = $this->actingAs($this->operator, 'staff')->get('/admin/feeder')->assertOk()->getContent();
        expect($html)->toContain('Dapat dikirim lewat web service');

        $this->actingAs($this->operator, 'staff')
            ->post("/admin/feeder/perubahan/{$p->uuid}/kirim")
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($p->fresh()->status)->toBe(StatusPerubahanFeeder::Selesai)
            ->and($this->fake->dipanggil('UpdateRiwayatPendidikanMahasiswa'))->toBeTrue();
    });

    it('menolak staf yang hanya boleh melihat', function () {
        $m = mhsTersinkron();
        $m->update(['nama' => 'Rani Puspitasari']);
        $p = FeederChangeRequest::sole();

        $pimpinan = Staff::factory()->create();
        $pimpinan->assignRole('pimpinan');

        $this->actingAs($pimpinan, 'staff')->get('/admin/feeder')->assertOk();
        $this->actingAs($pimpinan, 'staff')->post("/admin/feeder/perubahan/{$p->uuid}/selesai")->assertForbidden();

        expect($p->fresh()->status)->toBe(StatusPerubahanFeeder::Menunggu);
    });
});
