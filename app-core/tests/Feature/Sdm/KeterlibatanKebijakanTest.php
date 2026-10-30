<?php

declare(strict_types=1);

use App\DTOs\Sdm\KegiatanDosenData;
use App\Enums\BentukKeterlibatanKebijakan;
use App\Enums\JenisBuktiKebijakan;
use App\Enums\LecturerAssignmentType;
use App\Enums\SemesterType;
use App\Enums\TingkatKebijakan;
use App\Enums\UnsurBkd;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\Staff;
use App\Services\Bridge\BridgeEventPublisher;
use App\Services\Sdm\PenugasanDosenService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * IKU 8 of Kepmendiktisaintek 358/M/KEP/2025 — lecturers officially involved
 * in drafting a policy (ADR-041 §1): recorded on the portfolio with its
 * official document, verified on /admin/data-iku, counted in iku_2026.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake((string) config('berkas.disk'));

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'tanggal_mulai' => now()->subMonths(2),
        'tanggal_selesai' => now()->addMonths(3),
    ]);
    $this->dosen = Dosen::factory()->create(['is_active' => true]);
    $this->staf = Staff::factory()->create()->assignRole('super-admin');
    $this->layanan = app(PenugasanDosenService::class);

    $this->isian = fn (array $lain = []): array => array_merge([
        'jenis' => LecturerAssignmentType::PenyusunanKebijakan->value,
        'unsur' => UnsurBkd::Pengabdian->value,
        'judul' => 'Tim Penyusun Peraturan Daerah Transformasi Digital',
        'mitra_nama' => 'Pemerintah Kota Uji',
        'tanggal_mulai' => now()->subWeek()->toDateString(),
        'kebijakan_bentuk' => BentukKeterlibatanKebijakan::TimPenyusun->value,
        'kebijakan_tingkat' => TingkatKebijakan::Daerah->value,
        'kebijakan_bukti_jenis' => JenisBuktiKebijakan::SkPenugasan->value,
        'kebijakan_nomor_bukti' => 'SK/UJI/014/2026',
        'dokumen' => UploadedFile::fake()->create('sk.pdf', 40, 'application/pdf'),
    ], $lain);
});

function keterlibatan(Dosen $dosen, TahunAkademik $term, array $lain = []): PenugasanDosen
{
    return PenugasanDosen::create(array_merge([
        'dosen_id' => $dosen->id,
        'tahun_akademik_id' => $term->id,
        'jenis' => LecturerAssignmentType::PenyusunanKebijakan,
        'unsur' => UnsurBkd::Pengabdian,
        'judul' => 'Kebijakan uji',
        'mitra_nama' => 'Kementerian Uji',
        'tanggal_mulai' => now(),
        'kebijakan_bentuk' => BentukKeterlibatanKebijakan::Narasumber,
        'kebijakan_tingkat' => TingkatKebijakan::Nasional,
        'kebijakan_bukti_jenis' => JenisBuktiKebijakan::UndanganResmi,
        'dokumen_path' => 'portofolio/uji/undangan.pdf',
        'is_verified' => true,
    ], $lain));
}

describe('portofolio dosen', function () {
    it('mencatat keterlibatan kebijakan dengan bentuk, tingkat, lembaga, dan bukti — belum terverifikasi', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', ($this->isian)(['is_verified' => 1]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $p = PenugasanDosen::where('dosen_id', $this->dosen->id)->sole();

        expect($p->jenis)->toBe(LecturerAssignmentType::PenyusunanKebijakan)
            ->and($p->kebijakan_bentuk)->toBe(BentukKeterlibatanKebijakan::TimPenyusun)
            ->and($p->kebijakan_tingkat)->toBe(TingkatKebijakan::Daerah)
            ->and($p->kebijakan_bukti_jenis)->toBe(JenisBuktiKebijakan::SkPenugasan)
            ->and($p->kebijakan_nomor_bukti)->toBe('SK/UJI/014/2026')
            ->and($p->mitra_nama)->toBe('Pemerintah Kota Uji')
            ->and($p->tahun_akademik_id)->toBe($this->term->id)
            ->and($p->is_verified)->toBeFalse();

        Storage::disk((string) config('berkas.disk'))->assertExists($p->dokumen_path);
    });

    it('menolak keterlibatan kebijakan tanpa bentuk, tingkat, lembaga, jenis bukti, atau dokumen resmi', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', ($this->isian)([
                'mitra_nama' => '', 'kebijakan_bentuk' => '', 'kebijakan_tingkat' => '',
                'kebijakan_bukti_jenis' => '', 'dokumen' => null,
            ]))
            ->assertSessionHasErrors(['mitra_nama', 'kebijakan_bentuk', 'kebijakan_tingkat', 'kebijakan_bukti_jenis', 'dokumen']);

        expect(PenugasanDosen::count())->toBe(0);
    });

    it('membuang medan kebijakan dari jenis lain, dan tidak mewajibkannya', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', ($this->isian)([
                'jenis' => LecturerAssignmentType::Penelitian->value,
                'unsur' => UnsurBkd::Penelitian->value,
                'mitra_nama' => '',
                'dokumen' => null,
            ]))
            ->assertSessionHasNoErrors();

        $p = PenugasanDosen::sole();

        expect($p->kebijakan_bentuk)->toBeNull()
            ->and($p->kebijakan_tingkat)->toBeNull()
            ->and($p->kebijakan_bukti_jenis)->toBeNull()
            ->and($p->kebijakan_nomor_bukti)->toBeNull();
    });

    it('menolak tingkat kebijakan di luar daftar', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', ($this->isian)(['kebijakan_tingkat' => 'kecamatan']))
            ->assertSessionHasErrors('kebijakan_tingkat');
    });

    it('menampilkan medan kebijakan pada borang dan barisnya pada daftar kegiatan', function () {
        keterlibatan($this->dosen, $this->term, ['judul' => 'Rancangan Standar Sektor Uji', 'is_verified' => false]);

        $this->actingAs($this->dosen, 'dosen')
            ->get('/dosen/portofolio')
            ->assertOk()
            ->assertSee('kebijakan_bentuk', false)
            ->assertSee('Rancangan Standar Sektor Uji')
            ->assertSee('Narasumber / ahli resmi');
    });
});

describe('layanan', function () {
    it('catat() menyimpan DTO dan berkasnya tanpa pernah menandai terverifikasi', function () {
        $data = KegiatanDosenData::fromArray(($this->isian)(['dokumen' => null]));

        $p = $this->layanan->catat($this->dosen, $this->term, $data, UploadedFile::fake()->create('sk.pdf', 10, 'application/pdf'));

        expect($p->is_verified)->toBeFalse()
            ->and($p->kebijakan_bentuk)->toBe(BentukKeterlibatanKebijakan::TimPenyusun)
            ->and($p->dokumen_path)->toStartWith('portofolio/'.$this->dosen->uuid.'/');
    });

    it('verifikasi() mencatat penanda tangan dan mengabarkan Open Campus; cabutVerifikasi() menghapus jejaknya', function () {
        $p = keterlibatan($this->dosen, $this->term, ['is_verified' => false]);

        $this->mock(BridgeEventPublisher::class)
            ->shouldReceive('publish')
            ->once()
            ->withArgs(fn (string $event, array $muatan): bool => $event === 'lecturer.assignment_recorded'
                && $muatan['penugasan_uuid'] === $p->uuid
                && $muatan['jenis'] === 'penyusunan_kebijakan'
                // Frozen legacy numbering: policy drafting feeds neither.
                && $muatan['iku'] === []);

        app(PenugasanDosenService::class)->verifikasi($p, $this->staf);

        expect($p->fresh()->is_verified)->toBeTrue()
            ->and($p->fresh()->verified_by_staff_id)->toBe($this->staf->id);

        app(PenugasanDosenService::class)->cabutVerifikasi($p);

        expect($p->fresh()->is_verified)->toBeFalse()
            ->and($p->fresh()->verified_by_staff_id)->toBeNull()
            ->and($p->fresh()->verified_at)->toBeNull();
    });
});

describe('verifikasi staf', function () {
    it('memverifikasi keterlibatan kebijakan dari /admin/data-iku', function () {
        $p = keterlibatan($this->dosen, $this->term, ['is_verified' => false, 'judul' => 'Kebijakan menunggu']);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/data-iku')
            ->assertOk()
            ->assertSee('Kebijakan menunggu')
            ->assertSee('Narasumber / ahli resmi')
            ->assertSee(route('berkas.kegiatan-dosen', $p), false);

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/data-iku/penugasan/{$p->uuid}/verifikasi")
            ->assertRedirect();

        expect($p->fresh()->is_verified)->toBeTrue();
    });

    it('menolak staf tanpa dosen.manage', function () {
        $p = keterlibatan($this->dosen, $this->term, ['is_verified' => false]);
        $tanpaIzin = Staff::factory()->create();

        $this->actingAs($tanpaIzin, 'staff')
            ->post("/admin/data-iku/penugasan/{$p->uuid}/verifikasi")
            ->assertForbidden();

        expect($p->fresh()->is_verified)->toBeFalse();
    });

    it('membuka bukti hanya untuk pemiliknya dan staf', function () {
        $path = 'portofolio/'.$this->dosen->uuid.'/sk.pdf';
        Storage::disk((string) config('berkas.disk'))->put($path, '%PDF-1.4');
        $p = keterlibatan($this->dosen, $this->term, ['dokumen_path' => $path]);

        // Lecturers first: once a staff guard is signed in, the route's
        // auth:staff,dosen resolves to staff for every later request.
        $this->actingAs($this->dosen, 'dosen')->get(route('berkas.kegiatan-dosen', $p))->assertOk();
        $this->actingAs(Dosen::factory()->create(), 'dosen')->get(route('berkas.kegiatan-dosen', $p))->assertForbidden();
        $this->actingAs(Staff::factory()->create(), 'staff')->get(route('berkas.kegiatan-dosen', $p))->assertForbidden();
        $this->actingAs($this->staf, 'staff')->get(route('berkas.kegiatan-dosen', $p))->assertOk();
    });
});

describe('Campus Bridge iku_2026', function () {
    beforeEach(function () {
        $scopes = ['students.read', 'lecturers.read', 'classes.read', 'activities.read', 'graduates.read'];
        $konsumen = BridgeConsumer::create(['nama' => 'Open Campus', 'slug' => 'iku8-'.Str::random(6), 'scopes' => $scopes, 'is_active' => true]);
        $this->header = ['Authorization' => 'Bearer '.$konsumen->createToken('uji', $scopes)->plainTextToken, 'Accept' => 'application/json'];
    });

    it('menghitung IKU 8 dari keterlibatan terverifikasi, dosen sekali, dosen tetap terpisah', function () {
        $tidakTetap = Dosen::factory()->create(['is_active' => true, 'status_kepegawaian' => 'tidak_tetap']);
        Dosen::factory()->create(['is_active' => false, 'status_kepegawaian' => 'tetap']);

        keterlibatan($this->dosen, $this->term, ['kebijakan_bentuk' => BentukKeterlibatanKebijakan::TimPenyusun, 'kebijakan_tingkat' => TingkatKebijakan::Daerah]);
        keterlibatan($this->dosen, $this->term, ['kebijakan_bentuk' => BentukKeterlibatanKebijakan::Narasumber, 'kebijakan_tingkat' => TingkatKebijakan::Nasional]);
        keterlibatan($tidakTetap, $this->term, ['kebijakan_bentuk' => BentukKeterlibatanKebijakan::KontributorKajian, 'kebijakan_tingkat' => TingkatKebijakan::Industri]);

        // Unverified and other-term rows are not evidence for this term.
        keterlibatan($this->dosen, $this->term, ['is_verified' => false]);
        $lain = TahunAkademik::factory()->term(2026, SemesterType::Genap)->create();
        keterlibatan($this->dosen, $lain);

        $iku = $this->getJson('/api/bridge/v1/iku-data', $this->header)->assertOk()->json('data.iku_2026');

        expect($iku['ketersediaan']['8'])->toBe(['nama' => 'SDM dalam penyusunan kebijakan', 'status' => 'tersedia', 'kunci' => 'iku_8_penyusunan_kebijakan'])
            ->and($iku['iku_8_penyusunan_kebijakan'])->toEqual([
                'keterlibatan_terverifikasi' => 3,
                'dosen_terlibat' => 2,
                'dosen_tetap_terlibat' => 1,
                // The factory's lecturer is permanent; the inactive one is not counted.
                'dosen_tetap_aktif' => 1,
                'per_bentuk' => ['tim_penyusun' => 1, 'narasumber' => 1, 'kontributor_kajian' => 1],
                'per_tingkat' => ['daerah' => 1, 'nasional' => 1, 'industri' => 1],
            ])
            // Policy rows carry no output, so IKU 4–6 do not see them.
            ->and($iku['iku_5_luaran_kerja_sama']['total_luaran'])->toBe(0);
    });
});
