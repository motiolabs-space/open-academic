<?php

declare(strict_types=1);

use App\Enums\EducationLevel;
use App\Enums\JenisLuaran;
use App\Enums\JenisPoin;
use App\Enums\LecturerAssignmentType;
use App\Enums\SemesterType;
use App\Enums\StudentActivityType;
use App\Enums\StudentStatus;
use App\Enums\TingkatKegiatan;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use Illuminate\Support\Str;

/*
 * The iku_2026 block of GET /iku-data (ADR-036): the facts filed under the
 * twelve indicators of Kepmendiktisaintek 358/M/KEP/2025. These tests pin
 * what each number MEANS — a renamed or mistyped column passes a status-code
 * test on SQLite while answering a wrong count (CLAUDE.md §9).
 */

beforeEach(function () {
    $this->ganjil = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->genap = TahunAkademik::factory()->term(2026, SemesterType::Genap)->create();
    $this->s1 = Prodi::factory()->create(['kode' => '55201', 'jenjang' => EducationLevel::S1]);
    $this->d3 = Prodi::factory()->create(['kode' => '57401', 'jenjang' => EducationLevel::D3]);

    $scopes = ['students.read', 'lecturers.read', 'classes.read', 'activities.read', 'graduates.read'];
    $konsumen = BridgeConsumer::create(['nama' => 'Open Campus', 'slug' => 'iku-'.Str::random(6), 'scopes' => $scopes, 'is_active' => true]);
    $this->header = ['Authorization' => 'Bearer '.$konsumen->createToken('uji', $scopes)->plainTextToken, 'Accept' => 'application/json'];

    $this->ambil = fn (string $query = ''): array => $this->getJson('/api/bridge/v1/iku-data'.$query, $this->header)
        ->assertOk()
        ->json('data.iku_2026');
});

function ikuMahasiswa(Prodi $prodi, string $termMasuk, StudentStatus $status = StudentStatus::Aktif, array $lain = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'prodi_id' => $prodi->id,
        'term_masuk' => $termMasuk,
        'angkatan' => (int) substr($termMasuk, 0, 4),
        'status' => $status,
    ], $lain));
}

function ikuStatus(Mahasiswa $m, TahunAkademik $term, StudentStatus $status): void
{
    StatusMahasiswa::create([
        'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $term->id, 'status' => $status, 'semester_ke' => 1,
    ]);
}

function ikuLulus(Mahasiswa $m, TahunAkademik $term): Yudisium
{
    return Yudisium::create([
        'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $term->id, 'total_sks' => 144, 'ipk' => 3.5,
        'status' => 'ditetapkan', 'tanggal_yudisium' => '2027-03-01', 'tanggal_lulus' => '2027-03-01',
    ]);
}

it('menyebut acuan dan ketersediaan kedua belas IKU', function () {
    $iku = ($this->ambil)();

    expect($iku['acuan'])->toBe('Kepmendiktisaintek 358/M/KEP/2025')
        ->and(array_keys($iku['ketersediaan']))->toBe(range(1, 12))
        ->and($iku['ketersediaan']['3'])->toBe(['nama' => 'Mahasiswa berkegiatan atau berprestasi di luar program studi', 'status' => 'tersedia', 'kunci' => 'iku_3_kegiatan_luar_prodi'])
        ->and($iku['ketersediaan']['8']['status'])->toBe('belum_tersedia')
        ->and($iku['ketersediaan']['9']['status'])->toBe('di_luar_cakupan');

    // Every key the map names exists in the block, and nothing else does.
    $kunci = collect($iku['ketersediaan'])->pluck('kunci')->filter()->values()->all();

    expect(array_values(array_diff(array_keys($iku), ['acuan', 'ketersediaan'])))->toEqualCanonicalizing($kunci);
});

it('IKU 1 membaca satu tahun akademik penuh, per jenjang, dan mengelompokkan lulusan per lama studi', function () {
    // Two terms in the same academic year, one student: counted once, at the
    // status the year ends on.
    $pindahKeLulus = ikuMahasiswa($this->s1, '20221', StudentStatus::Lulus);
    ikuStatus($pindahKeLulus, $this->ganjil, StudentStatus::Aktif);
    ikuStatus($pindahKeLulus, $this->genap, StudentStatus::Lulus);
    ikuLulus($pindahKeLulus, $this->genap);              // 20221 → 20262: 10 semesters

    $tepat = ikuMahasiswa($this->s1, '20231', StudentStatus::Lulus);
    ikuStatus($tepat, $this->ganjil, StudentStatus::Lulus);
    ikuLulus($tepat, $this->ganjil);                     // 20231 → 20261: 7 semesters

    $cuti = ikuMahasiswa($this->s1, '20251', StudentStatus::Cuti);
    ikuStatus($cuti, $this->ganjil, StudentStatus::Cuti);

    $pindahan = ikuMahasiswa($this->d3, '20251', StudentStatus::Lulus, ['term_masuk_asal' => '20231']);
    ikuStatus($pindahan, $this->ganjil, StudentStatus::Lulus);
    ikuLulus($pindahan, $this->ganjil);                  // 20251 → 20261: 3 semesters here

    // Another academic year entirely: not counted.
    $lalu = TahunAkademik::factory()->term(2025, SemesterType::Genap)->create();
    ikuStatus(ikuMahasiswa($this->s1, '20241'), $lalu, StudentStatus::Aktif);

    $aee = ($this->ambil)('?semester=20262')['iku_1_efisiensi_edukasi'];

    expect($aee['tahun_akademik'])->toBe('2026/2027')
        ->and($aee['per_jenjang']['30'])->toBe([
            'jenjang' => 'S1',
            'masa_studi_standar_semester' => 8,
            'terdaftar' => 3,
            'terdaftar_per_status' => ['L' => 2, 'C' => 1],
            'lulus' => 2,
            'lulus_pindahan' => 0,
            'lulus_per_lama_studi' => [['semester' => 7, 'lulus' => 1], ['semester' => 10, 'lulus' => 1]],
        ])
        ->and($aee['per_jenjang']['20']['masa_studi_standar_semester'])->toBe(6)
        ->and($aee['per_jenjang']['20']['lulus_pindahan'])->toBe(1)
        ->and($aee['per_jenjang']['20']['lulus_per_lama_studi'])->toBe([['semester' => 3, 'lulus' => 1]]);
});

it('IKU 3 menjumlahkan SKS konversi per mahasiswa sebelum dipitakan, dan memuat prestasi terverifikasi', function () {
    $a = ikuMahasiswa($this->s1, '20241');
    $b = ikuMahasiswa($this->d3, '20241');
    ikuMahasiswa($this->s1, '20241'); // active, no activity

    $aktivitas = fn (Mahasiswa $m, int $sks, bool $verified = true) => AktivitasMahasiswa::create([
        'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->ganjil->id, 'jenis' => StudentActivityType::Magang,
        'judul' => 'Kegiatan', 'tanggal_mulai' => now(), 'sks_konversi' => $sks, 'is_verified' => $verified,
    ]);

    // 4 + 4 = 8 credits for one student: the 6–10 band, not twice 0–5.
    $aktivitas($a, 4);
    $aktivitas($a, 4);
    $aktivitas($b, 20);
    $aktivitas($b, 20, verified: false);

    $kategori = PoinKategori::create(['kode' => 'LOMBA-NAS', 'nama' => 'Juara lomba', 'jenis' => JenisPoin::Prestasi, 'tingkat' => 'nasional', 'poin' => 30]);
    $prestasi = fn (Mahasiswa $m, bool $verified) => PoinMahasiswa::create([
        'mahasiswa_id' => $m->id, 'poin_kategori_id' => $kategori->id, 'tahun_akademik_id' => $this->ganjil->id,
        'tanggal' => '2026-10-01', 'judul' => 'Juara', 'poin' => 30, 'jenis' => JenisPoin::Prestasi, 'is_verified' => $verified,
    ]);
    $prestasi($a, true);
    $prestasi($b, false);

    $iku3 = ($this->ambil)()['iku_3_kegiatan_luar_prodi'];

    expect($iku3['mahasiswa_aktif_per_jenjang'])->toEqual(['30' => 2, '20' => 1])
        ->and($iku3['pembelajaran']['catatan_terverifikasi'])->toBe(3)
        ->and($iku3['pembelajaran']['mahasiswa_terlibat'])->toBe(2)
        ->and($iku3['pembelajaran']['mahasiswa_per_sks_konversi'])->toBe(['0-5' => 0, '6-10' => 1, '11+' => 1])
        ->and($iku3['prestasi'])->toEqual([
            'catatan_terverifikasi' => 1,
            'mahasiswa_terlibat' => 1,
            'per_tingkat' => ['nasional' => 1],
            'per_kategori' => ['LOMBA-NAS' => 1],
        ]);

    // The prodi filter narrows every population, not just one of them.
    $d3 = ($this->ambil)('?prodi=57401')['iku_3_kegiatan_luar_prodi'];

    expect($d3['mahasiswa_aktif_per_jenjang'])->toEqual(['20' => 1])
        ->and($d3['pembelajaran']['mahasiswa_per_sks_konversi'])->toBe(['0-5' => 0, '6-10' => 0, '11+' => 1])
        ->and($d3['prestasi']['catatan_terverifikasi'])->toBe(0);
});

it('IKU 4–6 membaca luaran dosen terverifikasi menurut tingkat, mitra, dan jenis', function () {
    $berNuptk = Dosen::factory()->create(['nuptk' => '1234567890123456']);
    $tanpaNuptk = Dosen::factory()->create(['nuptk' => null]);

    $luaran = fn (Dosen $d, JenisLuaran $jenis, ?TingkatKegiatan $tingkat, ?string $mitra = null, bool $verified = true) => PenugasanDosen::create([
        'dosen_id' => $d->id, 'tahun_akademik_id' => $this->ganjil->id, 'jenis' => LecturerAssignmentType::Penelitian,
        'judul' => 'Riset', 'tanggal_mulai' => now(), 'is_verified' => $verified,
        'luaran_jenis' => $jenis, 'tingkat' => $tingkat, 'mitra_nama' => $mitra, 'mitra_jenis' => $mitra ? 'industri' : null,
    ]);

    $luaran($berNuptk, JenisLuaran::Jurnal, TingkatKegiatan::Internasional);
    $luaran($tanpaNuptk, JenisLuaran::Prosiding, TingkatKegiatan::Internasional);
    $luaran($berNuptk, JenisLuaran::Produk, TingkatKegiatan::Nasional, 'PT Mitra');
    $luaran($berNuptk, JenisLuaran::Jurnal, TingkatKegiatan::Internasional, verified: false);

    $iku = ($this->ambil)();

    expect($iku['iku_4_rekognisi_dosen'])->toEqual([
        'luaran_internasional' => 2,
        'dosen_terlibat' => 2,
        'dosen_terlibat_ber_nuptk' => 1,
        'dosen_aktif_ber_nuptk' => 1,
        'per_jenis' => ['jurnal' => 1, 'prosiding' => 1],
    ])
        ->and($iku['iku_5_luaran_kerja_sama'])->toEqual([
            'total_luaran' => 3,
            'luaran_bermitra' => 1,
            'per_jenis' => ['produk' => 1],
            'per_jenis_mitra' => ['industri' => 1],
        ])
        ->and($iku['iku_6_publikasi']['total_publikasi'])->toBe(2)
        ->and($iku['iku_6_publikasi']['per_tingkat'])->toEqual(['internasional' => 2]);
});

it('membiarkan kunci penomoran lama tetap ada dan isinya tidak berubah', function () {
    $data = $this->getJson('/api/bridge/v1/iku-data', $this->header)->assertOk()->json('data');

    // Open Campus builds on these; ADR-036 freezes them until a major version.
    expect($data)->toHaveKeys([
        'iku_1_lulusan', 'iku_2_pengalaman_luar_kampus', 'iku_3_dosen_luar_kampus',
        'iku_4_praktisi_mengajar', 'iku_7_kelas_kolaboratif', 'iku_11_efisiensi',
    ])
        ->and(array_keys($data['iku_2_pengalaman_luar_kampus']['per_sks_konversi']))->toBe(['0-5', '6-19', '20+']);
});
