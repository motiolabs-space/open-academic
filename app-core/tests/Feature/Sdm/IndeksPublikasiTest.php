<?php

declare(strict_types=1);

use App\DTOs\Sdm\KegiatanDosenData;
use App\Enums\IndeksPublikasi;
use App\Enums\JenisLuaran;
use App\Enums\KuartilPublikasi;
use App\Enums\LecturerAssignmentType;
use App\Enums\SemesterType;
use App\Enums\UnsurBkd;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\Staff;
use App\Services\Sdm\PenugasanDosenService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

/*
 * IKU 6 facts on a publication (ADR-041 §2): index, quartile, DOI, link,
 * publisher, and international co-authorship — declared by the lecturer,
 * verified by staff, counted per article in iku_2026. Nothing here calls an
 * indexing service.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'tanggal_mulai' => now()->subMonths(2),
        'tanggal_selesai' => now()->addMonths(3),
    ]);
    $this->dosen = Dosen::factory()->create(['is_active' => true]);

    $this->isian = fn (array $lain = []): array => array_merge([
        'jenis' => LecturerAssignmentType::Penelitian->value,
        'unsur' => UnsurBkd::Penelitian->value,
        'judul' => 'Riset Deteksi Anomali',
        'tanggal_mulai' => now()->subWeek()->toDateString(),
        'luaran_jenis' => JenisLuaran::Jurnal->value,
        'luaran_identitas' => 'ISSN 1234-5678',
        'luaran_tahun' => 2026,
        'luaran_indeks' => IndeksPublikasi::Scopus->value,
        'luaran_kuartil' => KuartilPublikasi::Q2->value,
        'luaran_doi' => 'https://doi.org/10.1016/J.CAMPUS.2026.042',
        'luaran_tautan' => 'https://jurnal.example.org/artikel/42',
        'luaran_penerbit' => 'Penerbit Uji',
        'luaran_kolaborasi_internasional' => '1',
    ], $lain);
});

function publikasi(Dosen $dosen, TahunAkademik $term, array $lain = []): PenugasanDosen
{
    return PenugasanDosen::create(array_merge([
        'dosen_id' => $dosen->id,
        'tahun_akademik_id' => $term->id,
        'jenis' => LecturerAssignmentType::Penelitian,
        'unsur' => UnsurBkd::Penelitian,
        'judul' => 'Artikel',
        'tanggal_mulai' => now(),
        'luaran_jenis' => JenisLuaran::Jurnal,
        'is_verified' => true,
    ], $lain));
}

describe('portofolio dosen', function () {
    it('mencatat indeks, kuartil, DOI ternormalkan, tautan, penerbit, dan kolaborasi internasional', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', ($this->isian)())
            ->assertSessionHasNoErrors();

        $p = PenugasanDosen::sole();

        expect($p->luaran_indeks)->toBe(IndeksPublikasi::Scopus)
            ->and($p->luaran_kuartil)->toBe(KuartilPublikasi::Q2)
            ->and($p->luaran_doi)->toBe('10.1016/j.campus.2026.042')
            ->and($p->tautanDoi())->toBe('https://doi.org/10.1016/j.campus.2026.042')
            ->and($p->luaran_tautan)->toBe('https://jurnal.example.org/artikel/42')
            ->and($p->luaran_penerbit)->toBe('Penerbit Uji')
            ->and($p->luaran_kolaborasi_internasional)->toBeTrue()
            ->and($p->is_verified)->toBeFalse();
    });

    it('menolak kuartil untuk indeks selain Scopus/WoS dan untuk prosiding', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', ($this->isian)(['luaran_indeks' => IndeksPublikasi::Sinta2->value]))
            ->assertSessionHasErrors('luaran_kuartil');

        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', ($this->isian)(['luaran_jenis' => JenisLuaran::Prosiding->value]))
            ->assertSessionHasErrors('luaran_kuartil');

        expect(PenugasanDosen::count())->toBe(0);
    });

    it('menolak DOI yang bukan DOI dan tautan yang bukan http(s)', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', ($this->isian)(['luaran_doi' => 'ISSN 1234-5678', 'luaran_tautan' => 'javascript:alert(1)']))
            ->assertSessionHasErrors(['luaran_doi', 'luaran_tautan']);
    });

    it('membuang fakta publikasi dari luaran yang bukan publikasi', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', ($this->isian)([
                'luaran_jenis' => JenisLuaran::Paten->value,
                'luaran_kuartil' => '',
            ]))
            ->assertSessionHasNoErrors();

        $p = PenugasanDosen::sole();

        expect($p->luaran_indeks)->toBeNull()
            ->and($p->luaran_doi)->toBeNull()
            ->and($p->luaran_tautan)->toBeNull()
            ->and($p->luaran_kolaborasi_internasional)->toBeNull();
    });

    it('menampilkan indeks dan DOI pada daftar kegiatan', function () {
        publikasi($this->dosen, $this->term, [
            'luaran_indeks' => IndeksPublikasi::WebOfScience, 'luaran_kuartil' => KuartilPublikasi::Q1,
            'luaran_doi' => '10.1000/uji.1', 'is_verified' => false,
        ]);

        $this->actingAs($this->dosen, 'dosen')
            ->get('/dosen/portofolio')
            ->assertOk()
            ->assertSee('Web of Science Q1')
            ->assertSee('https://doi.org/10.1000/uji.1', false)
            ->assertSee('luaran_indeks', false);
    });
});

describe('DTO', function () {
    it('menormalkan DOI dari bentuk tautan maupun awalan doi:', function (string $masuk) {
        expect(KegiatanDosenData::doi($masuk))->toBe('10.1000/abc.def');
    })->with(['10.1000/ABC.def', 'https://doi.org/10.1000/abc.DEF', 'http://dx.doi.org/10.1000/abc.def', 'doi: 10.1000/abc.def']);
});

describe('verifikasi staf', function () {
    it('menampilkan fakta publikasi di /admin/data-iku dan memverifikasinya', function () {
        $staf = Staff::factory()->create()->assignRole('super-admin');
        $p = publikasi($this->dosen, $this->term, [
            'judul' => 'Artikel menunggu', 'luaran_indeks' => IndeksPublikasi::Scopus,
            'luaran_kuartil' => KuartilPublikasi::Q3, 'luaran_doi' => '10.1000/uji.2',
            'luaran_kolaborasi_internasional' => true, 'is_verified' => false,
        ]);

        $this->actingAs($staf, 'staff')
            ->get('/admin/data-iku')
            ->assertOk()
            ->assertSee('Artikel menunggu')
            ->assertSee('Scopus Q3')
            ->assertSee('DOI 10.1000/uji.2')
            ->assertSee('kolaborasi internasional');

        app(PenugasanDosenService::class)->verifikasi($p, $staf);

        expect($p->fresh()->is_verified)->toBeTrue();
    });
});

describe('Campus Bridge iku_2026', function () {
    it('menghitung IKU 6 per artikel: baris ber-DOI sama digabung, kuartil hanya untuk jurnal bereputasi', function () {
        $scopes = ['students.read', 'lecturers.read', 'classes.read', 'activities.read', 'graduates.read'];
        $konsumen = BridgeConsumer::create(['nama' => 'Open Campus', 'slug' => 'iku6-'.Str::random(6), 'scopes' => $scopes, 'is_active' => true]);
        $header = ['Authorization' => 'Bearer '.$konsumen->createToken('uji', $scopes)->plainTextToken, 'Accept' => 'application/json'];
        $rekan = Dosen::factory()->create(['is_active' => true]);

        // One Scopus Q1 article written by two lecturers here: two rows, one article.
        publikasi($this->dosen, $this->term, ['luaran_indeks' => IndeksPublikasi::Scopus, 'luaran_kuartil' => KuartilPublikasi::Q1, 'luaran_doi' => '10.1000/bersama', 'luaran_kolaborasi_internasional' => true]);
        publikasi($rekan, $this->term, ['luaran_indeks' => IndeksPublikasi::Scopus, 'luaran_kuartil' => KuartilPublikasi::Q1, 'luaran_doi' => '10.1000/bersama', 'luaran_kolaborasi_internasional' => true]);
        // A WoS journal article without a quartile recorded.
        publikasi($this->dosen, $this->term, ['luaran_indeks' => IndeksPublikasi::WebOfScience, 'luaran_doi' => '10.1000/wos']);
        // A Scopus proceedings paper, no DOI.
        publikasi($this->dosen, $this->term, ['luaran_jenis' => JenisLuaran::Prosiding, 'luaran_indeks' => IndeksPublikasi::Scopus]);
        // A SINTA 2 journal article and one with no index stated.
        publikasi($rekan, $this->term, ['luaran_indeks' => IndeksPublikasi::Sinta2, 'luaran_doi' => '10.1000/sinta']);
        publikasi($rekan, $this->term);
        // Unverified: not evidence.
        publikasi($rekan, $this->term, ['luaran_indeks' => IndeksPublikasi::Scopus, 'luaran_kuartil' => KuartilPublikasi::Q4, 'is_verified' => false]);

        $iku6 = $this->getJson('/api/bridge/v1/iku-data', $header)->assertOk()->json('data.iku_2026.iku_6_publikasi');

        expect($iku6)->toEqual([
            // Row-based keys, unchanged (ADR-036 contract).
            'total_publikasi' => 6,
            'per_jenis' => ['jurnal' => 5, 'prosiding' => 1],
            'per_tingkat' => ['tidak_disebutkan' => 6],
            // Article-based keys (ADR-041 §2).
            'publikasi_unik' => 5,
            'ber_doi' => 3,
            'per_indeks' => ['scopus' => 2, 'wos' => 1, 'sinta_2' => 1, 'tidak_disebutkan' => 1],
            'bereputasi_internasional' => 3,
            'jurnal_bereputasi_per_kuartil' => ['q1' => 1, 'tidak_disebutkan' => 1],
            'prosiding_bereputasi' => 1,
            'bereputasi_kolaborasi_internasional' => 1,
        ]);
    });
});
