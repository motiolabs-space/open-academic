<?php

declare(strict_types=1);

use App\Enums\LecturerAssignmentType;
use App\Enums\SemesterType;
use App\Enums\StudentActivityType;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeApiRequest;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => '55201']);
});

/** @param array<int, string> $scopes */
function konsumen(array $scopes, bool $aktif = true): array
{
    $consumer = BridgeConsumer::create([
        'nama' => 'Open Campus',
        'slug' => 'open-campus-'.Str::random(6),
        'scopes' => $scopes,
        'is_active' => $aktif,
    ]);

    return [$consumer, $consumer->createToken('uji', $scopes)->plainTextToken];
}

function sebagai(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

describe('autentikasi & scope', function () {
    it('menolak permintaan tanpa token', function () {
        $this->getJson('/api/bridge/v1/students')->assertUnauthorized();
    });

    it('menolak token yang scope-nya tidak mencakup sumber daya', function () {
        [, $token] = konsumen(['classes.read']);

        // Token untuk membaca kelas tidak boleh berubah menjadi izin membaca
        // data pribadi mahasiswa.
        $this->getJson('/api/bridge/v1/students', sebagai($token))
            ->assertForbidden()
            ->assertJsonPath('message', fn (string $p): bool => str_contains($p, 'students.read'));
    });

    it('meloloskan token dengan scope yang tepat', function () {
        Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        [, $token] = konsumen(['students.read']);

        $this->getJson('/api/bridge/v1/students', sebagai($token))
            ->assertOk()
            ->assertJsonStructure(['data' => [['uuid', 'nim', 'nama', 'status', 'prodi']]]);
    });

    it('menolak aplikasi yang dinonaktifkan meski tokennya masih sah', function () {
        [$consumer, $token] = konsumen(['students.read']);
        $consumer->update(['is_active' => false]);

        $this->getJson('/api/bridge/v1/students', sebagai($token))->assertForbidden();
    });

    it('menghormati pencabutan scope tanpa perlu menerbitkan token baru', function () {
        [$consumer, $token] = konsumen(['students.read', 'classes.read']);

        $this->getJson('/api/bridge/v1/students', sebagai($token))->assertOk();

        $consumer->update(['scopes' => ['classes.read']]);

        $this->getJson('/api/bridge/v1/students', sebagai($token))->assertForbidden();
    });

    it('mencatat lalu lintas API untuk konsol', function () {
        [$consumer, $token] = konsumen(['terms.read']);

        $this->getJson('/api/bridge/v1/academic-terms', sebagai($token))->assertOk();

        $log = BridgeApiRequest::latest('id')->firstOrFail();

        expect($log->bridge_consumer_id)->toBe($consumer->id)
            ->and($log->status_code)->toBe(200)
            ->and($log->path)->toContain('bridge/v1/academic-terms');
    });
});

describe('privasi payload', function () {
    it('tidak pernah membocorkan data pribadi mahasiswa', function () {
        Mahasiswa::factory()->create([
            'prodi_id' => $this->prodi->id,
            'nik' => '3201234567890123',
            'alamat' => 'Jl. Rahasia No. 1',
            'nama_ibu' => 'Ibu Rahasia',
            'penghasilan_ortu' => 5_000_000,
        ]);

        [, $token] = konsumen(['students.read']);

        $isi = $this->getJson('/api/bridge/v1/students', sebagai($token))->getContent();

        // Dikumpulkan untuk pelaporan PDDIKTI, bukan untuk platform engagement.
        expect($isi)->not->toContain('3201234567890123')
            ->and($isi)->not->toContain('Jl. Rahasia')
            ->and($isi)->not->toContain('Ibu Rahasia')
            ->and($isi)->not->toContain('5000000');
    });

    it('menyertakan riwayat semester hanya bila diminta', function () {
        Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        [, $token] = konsumen(['students.read']);

        $this->getJson('/api/bridge/v1/students', sebagai($token))
            ->assertJsonMissingPath('data.0.riwayat_semester');

        $this->getJson('/api/bridge/v1/students?sertakan_riwayat=1', sebagai($token))
            ->assertJsonPath('data.0.riwayat_semester', []);
    });
});

describe('sumber data IKU', function () {
    it('menandai kelas kolaboratif untuk IKU 7', function () {
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id]);

        KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->term->id,
            'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id,
            'is_case_method' => true,
            'is_team_based_project' => false,
        ]);

        [, $token] = konsumen(['classes.read']);

        $this->getJson('/api/bridge/v1/classes?kolaboratif=1', sebagai($token))
            ->assertOk()
            ->assertJsonPath('data.0.metode_pembelajaran.case_method', true)
            ->assertJsonPath('data.0.metode_pembelajaran.kolaboratif', true);
    });

    it('menyebutkan IKU mana yang disumbang tiap penugasan dosen', function () {
        $dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);

        PenugasanDosen::create([
            'dosen_id' => $dosen->id,
            'tahun_akademik_id' => $this->term->id,
            'jenis' => LecturerAssignmentType::PraktisiMengajar,
            'judul' => 'Praktisi Mengajar RPL',
            'tanggal_mulai' => now(),
            'is_verified' => true,
        ]);

        [, $token] = konsumen(['lecturers.read']);

        $this->getJson('/api/bridge/v1/lecturers?sertakan_penugasan=1', sebagai($token))
            ->assertOk()
            ->assertJsonPath('data.0.penugasan.0.iku', [4]);
    });

    it('hanya mengembalikan aktivitas MBKM terverifikasi secara bawaan', function () {
        $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        foreach ([true, false] as $terverifikasi) {
            AktivitasMahasiswa::create([
                'mahasiswa_id' => $mahasiswa->id,
                'tahun_akademik_id' => $this->term->id,
                'jenis' => StudentActivityType::Magang,
                'judul' => $terverifikasi ? 'Sudah diverifikasi' : 'Belum diverifikasi',
                'tanggal_mulai' => now(),
                'sks_konversi' => 20,
                'is_verified' => $terverifikasi,
            ]);
        }

        [, $token] = konsumen(['activities.read']);

        // Indikator yang dibangun di atas laporan mandiri tanpa verifikasi
        // bukan bukti.
        $this->getJson('/api/bridge/v1/student-activities', sebagai($token))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.judul', 'Sudah diverifikasi');

        $this->getJson('/api/bridge/v1/student-activities?sertakan_belum_verifikasi=1', sebagai($token))
            ->assertJsonCount(2, 'data');
    });

    it('tidak menerapkan ambang 20 SKS IKU 2 di sisi Open Academic', function () {
        $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        AktivitasMahasiswa::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'jenis' => StudentActivityType::Magang,
            'judul' => 'Magang pendek',
            'tanggal_mulai' => now(),
            'sks_konversi' => 6,
            'is_verified' => true,
        ]);

        [, $token] = konsumen(['activities.read']);

        // Ambangnya diatur peraturan menteri dan berubah; keputusannya milik
        // Open Campus, bukan dibekukan di payload ini.
        $this->getJson('/api/bridge/v1/student-activities', sebagai($token))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sks_konversi', 6);
    });
});

describe('konvensi kueri', function () {
    it('membatasi ukuran halaman pada plafon terkonfigurasi', function () {
        Mahasiswa::factory()->count(3)->create(['prodi_id' => $this->prodi->id]);
        [, $token] = konsumen(['students.read']);

        $this->getJson('/api/bridge/v1/students?per_page=99999', sebagai($token))
            ->assertOk()
            ->assertJsonPath('meta.per_page', config('bridge.api.max_per_page'));
    });

    it('membalas 404 untuk kode semester yang tidak dikenal', function () {
        [, $token] = konsumen(['classes.read']);

        // Bukan daftar kosong yang terbaca seperti "tidak ada data".
        $this->getJson('/api/bridge/v1/classes?semester=19991', sebagai($token))
            ->assertNotFound();
    });

    it('menggunakan kode PDDIKTI, bukan id internal, sebagai kunci semester', function () {
        [, $token] = konsumen(['terms.read']);

        $this->getJson('/api/bridge/v1/academic-terms/current', sebagai($token))
            ->assertOk()
            ->assertJsonPath('data.kode', '20261');
    });
});
