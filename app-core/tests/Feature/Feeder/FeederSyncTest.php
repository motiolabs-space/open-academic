<?php

declare(strict_types=1);

use App\Enums\FeederSyncStatus;
use App\Enums\Gender;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\FeederException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use Database\Seeders\Demo\IntegrasiSeeder;

beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'a1b2c3d4']);

    $this->sync = app(FeederSyncService::class);
});

function mahasiswaSiapSinkron(array $atribut = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'nik' => '3201234567890123',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2004-05-12',
        'jenis_kelamin' => Gender::LakiLaki,
        'nama_ibu' => 'Siti Aminah',
        'agama_kode' => '1',
        'term_masuk' => test()->term->kode,
    ], $atribut));
}

describe('idempotensi', function () {
    it('mengirim baris baru lalu melewatinya pada sinkronisasi berikutnya', function () {
        mahasiswaSiapSinkron();

        $pertama = $this->sync->sinkronkan('mahasiswa', $this->term);
        expect($pertama['terkirim'])->toBe(1)->and($pertama['dilewati'])->toBe(0);

        // Menjalankan ulang tidak boleh menduplikasi apa pun di Feeder.
        $kedua = $this->sync->sinkronkan('mahasiswa', $this->term);
        expect($kedua['terkirim'])->toBe(0)->and($kedua['dilewati'])->toBe(1)
            ->and($this->fake->jumlahPanggilan('InsertBiodataMahasiswa'))->toBe(1);
    });

    it('mengirim ulang begitu datanya benar-benar berubah', function () {
        $mahasiswa = mahasiswaSiapSinkron();
        $this->sync->sinkronkan('mahasiswa', $this->term);

        $mahasiswa->update(['telepon' => '081200000001']);

        $hasil = $this->sync->sinkronkan('mahasiswa', $this->term);

        expect($hasil['terkirim'])->toBe(1)
            ->and($this->fake->dipanggil('UpdateBiodataMahasiswa'))->toBeTrue();
    });

    it('menyimpan identitas yang diberikan Feeder', function () {
        $mahasiswa = mahasiswaSiapSinkron();

        $this->sync->sinkronkan('mahasiswa', $this->term);

        expect($mahasiswa->fresh()->feeder_id)->not->toBeNull()
            ->and($mahasiswa->fresh()->feeder_synced_at)->not->toBeNull();
    });
});

describe('buku besar', function () {
    it('menulis satu baris ledger untuk setiap baris yang diproses', function () {
        mahasiswaSiapSinkron();
        mahasiswaSiapSinkron(['nim' => '2299999999']);

        $this->sync->sinkronkan('mahasiswa', $this->term);

        expect(FeederSyncLog::entity('mahasiswa')->count())->toBe(2)
            ->and(FeederSyncLog::entity('mahasiswa')->where('status', 'success')->count())->toBe(2);
    });

    it('mencatat kode dan pesan kesalahan dari Feeder', function () {
        mahasiswaSiapSinkron();
        $this->fake->tolak('InsertBiodataMahasiswa', 4, 'NIK sudah terdaftar pada mahasiswa lain');

        $hasil = $this->sync->sinkronkan('mahasiswa', $this->term);

        $log = FeederSyncLog::entity('mahasiswa')->latest('id')->firstOrFail();

        expect($hasil['gagal'])->toBe(1)
            ->and($log->status)->toBe(FeederSyncStatus::Failed)
            ->and($log->error_code)->toBe('4')
            ->and($log->error_message)->toContain('NIK sudah terdaftar');
    });

    it('menyimpan payload yang dikirim untuk keperluan rekonsiliasi', function () {
        mahasiswaSiapSinkron(['nama' => 'Rahmat Hidayat']);

        $this->sync->sinkronkan('mahasiswa', $this->term);

        expect(FeederSyncLog::entity('mahasiswa')->first()->payload)
            ->toHaveKey('nama_mahasiswa', 'Rahmat Hidayat');
    });

    it('mengulang hanya baris yang gagal', function () {
        mahasiswaSiapSinkron();
        $this->fake->tolak('InsertBiodataMahasiswa');
        $this->sync->sinkronkan('mahasiswa', $this->term);

        // Masalahnya diperbaiki di sisi Feeder, lalu operator menekan "ulangi".
        $this->fake = new FakeFeederClient;
        $this->app->instance(FeederClientInterface::class, $this->fake);

        $hasil = app(FeederSyncService::class)->ulangiYangGagal('mahasiswa', $this->term);

        expect($hasil['diulang'])->toBe(1)->and($hasil['berhasil'])->toBe(1);
    });
});

describe('urutan dependensi', function () {
    it('menolak KRS sebelum kelas kuliah disinkronkan', function () {
        expect(fn () => $this->sync->sinkronkan('krs', $this->term))
            ->toThrow(FeederException::class, 'tidak dapat dikirim sebelum');
    });

    it('menyebutkan dependensi mana yang belum selesai', function () {
        try {
            $this->sync->sinkronkan('nilai', $this->term);
            $this->fail('Seharusnya menolak');
        } catch (FeederException $e) {
            expect($e->getMessage())->toContain('krs');
        }
    });
});

describe('validasi pra-kirim', function () {
    it('membatalkan sinkronisasi ketika ada baris yang pasti ditolak Feeder', function () {
        mahasiswaSiapSinkron(['nik' => null]);

        expect(fn () => $this->sync->sinkronkan('mahasiswa', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi pra-kirim');

        // Tidak satu pun baris dikirim — pembatalan terjadi sebelum panggilan.
        expect($this->fake->dipanggil('InsertBiodataMahasiswa'))->toBeFalse();
    });

    it('meloloskan sinkronisasi ketika hanya ada peringatan', function () {
        mahasiswaSiapSinkron(['nama_ibu' => null]);

        expect($this->sync->sinkronkan('mahasiswa', $this->term)['terkirim'])->toBe(1);
    });
});

describe('pemetaan payload', function () {
    it('menerjemahkan status mahasiswa lewat tabel pemetaan, bukan menebak', function () {
        $this->seed(IntegrasiSeeder::class);

        $mahasiswa = mahasiswaSiapSinkron();
        $this->sync->sinkronkan('mahasiswa', $this->term);
        $this->sync->sinkronkan('riwayat_pendidikan', $this->term);

        StatusMahasiswa::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'status' => StudentStatus::Cuti,
            'semester_ke' => 3,
            'ips' => 3.1,
            'ipk' => 3.2,
            'is_final' => true,
        ]);

        $this->sync->sinkronkan('aktivitas_kuliah', $this->term);

        $payload = FeederSyncLog::entity('aktivitas_kuliah')->first()->payload;

        expect($payload['id_status_mahasiswa'])->toBe('C')
            ->and($payload['ips'])->toBe('3.10')
            ->and($payload['id_registrasi_mahasiswa'])->toBe($mahasiswa->fresh()->feeder_registrasi_id);
    });

    it('membawa flag IKU 7 pada payload kelas kuliah', function () {
        $dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id]);

        $kelas = KelasKuliah::factory()->kolaboratif()->create([
            'tahun_akademik_id' => $this->term->id,
            'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id,
            'sks' => 3,
            'is_case_method' => true,
            'is_team_based_project' => true,
        ]);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        $this->sync->sinkronkan('kelas_kuliah', $this->term);

        $payload = FeederSyncLog::entity('kelas_kuliah')->first()->payload;

        expect($payload['metode_case_method'])->toBe(1)
            ->and($payload['metode_team_based_project'])->toBe(1)
            ->and($payload['nidn'])->toBe($dosen->nidn);
    });

    it('menolak kelas yang pengampunya tanpa NIDN sebelum dikirim', function () {
        $praktisi = Dosen::factory()->praktisi()->create(['prodi_id' => $this->prodi->id]);
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id]);

        $kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->term->id,
            'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id,
            'sks' => 3,
        ]);
        $kelas->dosen()->attach($praktisi->id, ['peran' => 'pengampu']);

        expect(fn () => $this->sync->sinkronkan('kelas_kuliah', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');
    });
});

describe('penjaga', function () {
    it('menolak berjalan ketika integrasi dinonaktifkan', function () {
        config(['feeder.enabled' => false]);

        expect(fn () => $this->sync->sinkronkan('mahasiswa', $this->term))
            ->toThrow(FeederException::class, 'dinonaktifkan');
    });

    it('menolak entitas yang tidak terdaftar', function () {
        expect(fn () => $this->sync->sinkronkan('entitas_karangan', $this->term))
            ->toThrow(FeederException::class, 'tidak terdaftar');
    });
});
