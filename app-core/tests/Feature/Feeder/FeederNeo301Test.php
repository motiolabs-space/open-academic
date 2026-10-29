<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\JenisKonversi;
use App\Enums\SemesterType;
use App\Enums\StatusKonversi;
use App\Enums\StudentStatus;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Services\Akademik\IndeksPrestasiCalculator;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederValidator;
use App\Services\Feeder\Mappers\AktivitasKuliahMapper;
use App\Services\Feeder\Mappers\MahasiswaMapper;
use Database\Seeders\Demo\IntegrasiSeeder;

/*
 * Neo Feeder 3.0.1 and 3.1 (ADR-036 §3): the pre-flight rules that changed
 * with them, and the enrolment figures PDDIKTI now expects to include credit
 * earned outside the classroom.
 */

beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);
    $this->app->instance(FeederClientInterface::class, new FakeFeederClient);
    $this->seed(IntegrasiSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => 'IF', 'kode_pddikti' => 'a1b2c3d4']);
    $this->validator = app(FeederValidator::class);
});

function mhs301(array $atribut = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'nik' => fake()->unique()->numerify('3201############'),
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2004-05-12',
        'jenis_kelamin' => Gender::Perempuan,
        'agama_kode' => '1',
        'term_masuk' => test()->term->kode,
        'telepon' => '0812'.fake()->unique()->numerify('########'),
        'email' => fake()->unique()->safeEmail(),
    ], $atribut));
}

/** @return array<int, string> rule names the validator raises for a student's biodata */
function aturanBiodata(Mahasiswa $m): array
{
    return test()->validator->isuUntuk('mahasiswa', $m)->pluck('rule')->all();
}

describe('kontak pada biodata mahasiswa', function () {
    it('menolak biodata tanpa nomor HP, dan menyebut versinya', function () {
        $isu = $this->validator->isuUntuk('mahasiswa', mhs301(['telepon' => null]));
        $hp = $isu->firstWhere('rule', 'telepon_kosong');

        expect($hp['severity'])->toBe('error')
            ->and($hp['message'])->toContain('3.0.1');
    });

    it('meloloskan biodata lengkap tanpa peringatan kontak', function () {
        expect(aturanBiodata(mhs301()))->not->toContain('telepon_kosong', 'telepon_tidak_valid', 'telepon_ganda', 'email_tidak_valid');
    });

    it('memperingatkan nomor yang bukan nomor telepon, surel yang tidak sah, dan nomor yang dipakai dua mahasiswa', function () {
        $pendek = mhs301(['telepon' => '0812']);
        $surel = mhs301(['email' => 'bukan-surel']);
        $a = mhs301(['telepon' => '081211112222']);
        mhs301(['telepon' => '081211112222']);

        $validator = app(FeederValidator::class);

        expect($validator->isuUntuk('mahasiswa', $pendek)->firstWhere('rule', 'telepon_tidak_valid')['severity'])->toBe('warning')
            ->and($validator->isuUntuk('mahasiswa', $surel)->firstWhere('rule', 'email_tidak_valid')['severity'])->toBe('warning')
            ->and($validator->isuUntuk('mahasiswa', $a)->firstWhere('rule', 'telepon_ganda')['severity'])->toBe('warning');
    });

    it('mengirim nomor HP dan surel pada payload biodata', function () {
        $m = mhs301(['telepon' => '081234567890', 'email' => 'rani@contoh.test']);

        $payload = app(MahasiswaMapper::class)->payload($m);

        expect($payload)->toHaveKey('email', 'rani@contoh.test')
            ->and(array_values(array_filter($payload, fn ($v): bool => $v === '081234567890')))->toHaveCount(1);
    });
});

describe('Lulus/DO tanpa riwayat AKM', function () {
    it('menolak meluluskan mahasiswa yang tidak punya satu pun catatan AKM', function () {
        $m = mhs301(['status' => StudentStatus::Lulus, 'feeder_id' => 'mhs-1', 'feeder_registrasi_id' => 'reg-1']);
        Yudisium::create([
            'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'total_sks' => 144, 'ipk' => 3.2,
            'status' => 'ditetapkan', 'tanggal_lulus' => '2026-10-01', 'nomor_sk' => 'SK/1',
        ]);

        expect($this->validator->isuUntuk('lulus_do', $m->fresh())->pluck('rule')->all())->toContain('akm_kosong');

        StatusMahasiswa::create([
            'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'status' => 'A', 'semester_ke' => 8,
        ]);

        expect($this->validator->isuUntuk('lulus_do', $m->fresh())->pluck('rule')->all())->not->toContain('akm_kosong');
    });
});

describe('SKS pada AKM', function () {
    it('memuat SKS konversi aktivitas di semester itu dan SKS transfer pada total', function () {
        $m = mhs301(['feeder_id' => 'mhs-1', 'feeder_registrasi_id' => 'reg-1']);
        $kuliah = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'kode' => 'IF101', 'sks' => 3]);
        $magang = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'kode' => 'IF390', 'sks' => 6]);
        $transfer = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'kode' => 'IF100', 'sks' => 4]);

        $krs = Krs::create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'semester_ke' => 1, 'batas_sks' => 24, 'status' => 'disetujui']);
        $aktivitas = AktivitasMahasiswa::create([
            'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'jenis' => 'magang', 'judul' => 'Magang',
            'tanggal_mulai' => '2026-09-01', 'sks_konversi' => 6, 'is_verified' => true,
        ]);

        foreach ([[$kuliah, 3, null], [$magang, 6, $aktivitas->id]] as [$mk, $sks, $aktivitasId]) {
            $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id, 'mata_kuliah_id' => $mk->id, 'prodi_id' => $this->prodi->id, 'sks' => $sks]);
            $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => $sks, 'aktivitas_mahasiswa_id' => $aktivitasId]);
            Nilai::create([
                'krs_detail_id' => $detail->id, 'kelas_kuliah_id' => $kelas->id, 'mahasiswa_id' => $m->id,
                'nilai_angka' => 85, 'nilai_huruf' => 'A', 'bobot' => 4.0, 'is_final' => true,
            ]);
        }

        // Credit recognised from a previous institution: no term of its own.
        KonversiKredit::create([
            'mahasiswa_id' => $m->id, 'mata_kuliah_id' => $transfer->id, 'jenis' => JenisKonversi::Transfer, 'status' => StatusKonversi::Disetujui,
            'asal_institusi' => 'Politeknik Negeri Bandung', 'asal_nama' => 'Pengantar', 'asal_sks' => 4, 'asal_nilai' => 'B',
            'sks_diakui' => 4, 'nilai_huruf' => 'B', 'bobot' => 3.00, 'kunci_aktif' => KonversiKredit::kunci($m->id, $transfer->id),
        ]);

        StatusMahasiswa::create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'status' => 'A', 'semester_ke' => 1]);
        $status = app(IndeksPrestasiCalculator::class)->hitungUlang($m, $this->term);

        $payload = app(AktivitasKuliahMapper::class)->payload($status->load(['mahasiswa', 'tahunAkademik']));

        // 3 taught + 6 converted from the internship this term; 13 in total
        // with the 4 transferred.
        expect($payload['sks_semester'])->toBe(9)
            ->and($payload['sks_total'])->toBe(13);
    });
});
