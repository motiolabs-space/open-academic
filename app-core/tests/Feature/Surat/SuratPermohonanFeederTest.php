<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\JalurPerubahanFeeder;
use App\Enums\JenisPerubahanFeeder;
use App\Enums\JenisSurat;
use App\Enums\SemesterType;
use App\Enums\StatusPerubahanFeeder;
use App\Enums\StatusSurat;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederChangeRequest;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Models\Surat\Surat;
use App\Services\Surat\PermohonanFeederSurat;
use App\Services\Surat\SuratPdfService;
use App\Services\Surat\SuratService;
use Database\Seeders\RolePermissionSeeder;

/**
 * Roadmap #27b: a PDM/PJK request that "must be filed at PDDIKTI admin" is
 * filed with a numbered letter assembled from the requests themselves — old
 * value, new value, reason, attachments — and the number goes back onto
 * every request the letter carries.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'a1b2c3d4']);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');

    $this->layanan = app(PermohonanFeederSurat::class);
});

/** A student PDDIKTI already holds, so the observer records locked changes. */
function mhsPermohonan(array $atribut = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'nim' => '2201001',
        'nama' => 'Rani Puspita',
        'nama_ibu' => 'Siti Aminah',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2004-05-12',
        'jenis_kelamin' => Gender::Perempuan,
        'feeder_id' => 'mhs-1',
        'feeder_registrasi_id' => 'reg-1',
    ], $atribut));
}

function permintaanPdm(Mahasiswa $m, string $kolom, ?string $lama, ?string $baru, array $atribut = []): FeederChangeRequest
{
    return FeederChangeRequest::create(array_merge([
        'mahasiswa_id' => $m->id,
        'jenis' => JenisPerubahanFeeder::Pdm,
        'kolom' => $kolom,
        'nilai_lama' => $lama,
        'nilai_baru' => $baru,
        'jalur' => JalurPerubahanFeeder::PddiktiAdmin,
        'status' => StatusPerubahanFeeder::Menunggu,
    ], $atribut));
}

it('merakit satu surat dari beberapa permintaan mahasiswa yang sama, dan mencatat nomornya balik', function () {
    $m = mhsPermohonan();
    $m->update(['nama' => 'Rani Puspitasari', 'tanggal_lahir' => '2004-05-21']);

    $permintaan = FeederChangeRequest::query()->where('mahasiswa_id', $m->id)->orderBy('id')->get();
    expect($permintaan)->toHaveCount(2);

    $surat = $this->layanan->terbitkan($permintaan, $this->staf, 'Kesalahan pengetikan saat pendaftaran');

    expect($surat->jenis)->toBe(JenisSurat::PermohonanPdm)
        ->and($surat->status)->toBe(StatusSurat::Diterbitkan)
        ->and($surat->nomor)->toContain('/PDM/')
        ->and($surat->berlaku_sampai)->toBeNull()
        ->and($surat->isi('jenis_perubahan'))->toBe('Perubahan Data Mahasiswa')
        ->and($surat->isi('perubahan'))->toBe([
            ['kolom' => 'nama_mahasiswa', 'label' => 'Nama', 'lama' => 'Rani Puspita', 'baru' => 'Rani Puspitasari', 'alasan' => 'Kesalahan pengetikan saat pendaftaran'],
            ['kolom' => 'tanggal_lahir', 'label' => 'Tanggal lahir', 'lama' => '2004-05-12', 'baru' => '2004-05-21', 'alasan' => 'Kesalahan pengetikan saat pendaftaran'],
        ])
        // Shared documents appear once, in order, after the declaration.
        ->and($surat->isi('lampiran'))->toBe([
            'Surat Pernyataan Tanggung Jawab Mutlak pimpinan perguruan tinggi',
            'Salinan Kartu Tanda Penduduk mahasiswa',
            'Salinan akta kelahiran',
            'Salinan ijazah jenjang pendidikan sebelumnya',
        ]);

    foreach ($permintaan as $p) {
        $p->refresh();

        expect($p->status)->toBe(StatusPerubahanFeeder::Diajukan)
            ->and($p->nomor_surat)->toBe($surat->nomor)
            ->and($p->surat_id)->toBe($surat->id)
            ->and($p->diproses_by_staff_id)->toBe($this->staf->id);
    }
});

it('memberi PJK nomor dan urutan sendiri, dengan status keluar ditulis namanya', function () {
    $m = mhsPermohonan();
    $p = FeederChangeRequest::create([
        'mahasiswa_id' => $m->id,
        'jenis' => JenisPerubahanFeeder::Pjk,
        'kolom' => 'id_jenis_keluar',
        'nilai_lama' => 'D',
        'nilai_baru' => 'K',
        'jalur' => JalurPerubahanFeeder::PddiktiAdmin,
        'status' => StatusPerubahanFeeder::Menunggu,
        'catatan' => 'Mahasiswa mengundurkan diri, bukan dikeluarkan',
    ]);

    $surat = $this->layanan->terbitkan(collect([$p]), $this->staf, 'Koreksi jenis keluar');

    expect($surat->jenis)->toBe(JenisSurat::PermohonanPjk)
        ->and($surat->nomor)->toStartWith('0001/PJK/')
        ->and($surat->isi('perubahan.0.lama'))->toContain('(D)')
        ->and($surat->isi('perubahan.0.alasan'))->toBe('Mahasiswa mengundurkan diri, bukan dikeluarkan')
        ->and($surat->isi('lampiran'))->toContain('Salinan keputusan pimpinan tentang status keluar mahasiswa');
});

it('menyertakan penolakan web service sebagai alasan permintaan yang ditolak di sana', function () {
    $m = mhsPermohonan();
    $p = permintaanPdm($m, 'nim', '2201001', '2201010', [
        'jalur' => JalurPerubahanFeeder::WebService,
        'error_message' => 'Periode pelaporan sudah ditutup',
    ]);

    $surat = $this->layanan->terbitkan(collect([$p]), $this->staf, 'NIM salah cetak');

    expect($surat->isi('perubahan.0.alasan'))->toContain('Periode pelaporan sudah ditutup')
        ->and($surat->isi('lampiran'))->toContain('Salinan keputusan penetapan Nomor Induk Mahasiswa');
});

describe('menolak surat yang tidak semestinya', function () {
    it('untuk dua mahasiswa sekaligus', function () {
        $a = permintaanPdm(mhsPermohonan(), 'nama_mahasiswa', 'A', 'B');
        $b = permintaanPdm(mhsPermohonan(['nim' => '2201002', 'feeder_id' => 'mhs-2']), 'nama_mahasiswa', 'C', 'D');

        expect(fn () => $this->layanan->terbitkan(collect([$a, $b]), $this->staf, 'x'))
            ->toThrow(AturanAkademikException::class, 'satu mahasiswa');
    });

    it('untuk PDM dan PJK dalam satu surat', function () {
        $m = mhsPermohonan();
        $a = permintaanPdm($m, 'nama_mahasiswa', 'A', 'B');
        $b = permintaanPdm($m, 'ipk', '3.10', '3.12', ['jenis' => JenisPerubahanFeeder::Pjk]);

        expect(fn () => $this->layanan->terbitkan(collect([$a, $b]), $this->staf, 'x'))
            ->toThrow(AturanAkademikException::class, 'surat terpisah');
    });

    it('untuk permintaan yang dapat dikirim lewat web service, atau sudah diajukan', function () {
        $m = mhsPermohonan();
        $ws = permintaanPdm($m, 'nim', '1', '2', ['jalur' => JalurPerubahanFeeder::WebService]);
        $diajukan = permintaanPdm($m, 'nama_mahasiswa', 'A', 'B', ['status' => StatusPerubahanFeeder::Diajukan]);

        expect(fn () => $this->layanan->terbitkan(collect([$ws]), $this->staf, 'x'))
            ->toThrow(AturanAkademikException::class, 'NIM')
            ->and(fn () => $this->layanan->terbitkan(collect([$diajukan]), $this->staf, 'x'))
            ->toThrow(AturanAkademikException::class, 'Nama');

        expect(Surat::count())->toBe(0);
    });

    it('bila diminta mahasiswa sendiri', function () {
        expect(fn () => app(SuratService::class)->ajukan(mhsPermohonan(), JenisSurat::PermohonanPdm))
            ->toThrow(AturanAkademikException::class, 'konsol Feeder');

        expect(JenisSurat::dapatDiajukan())->not->toContain(JenisSurat::PermohonanPdm, JenisSurat::PermohonanPjk);
    });
});

it('mencetak PDF berisi tabel perubahan dan lampiran', function () {
    $m = mhsPermohonan();
    $p = permintaanPdm($m, 'nama_ibu_kandung', 'Siti Aminah', 'Siti Aminah Lubis');
    $surat = $this->layanan->terbitkan(collect([$p]), $this->staf, 'Nama ibu tidak lengkap');

    $html = view($surat->jenis->tampilan(), app(SuratPdfService::class)->data($surat))->render();

    expect($html)->toContain('Siti Aminah Lubis')
        ->toContain('Nama ibu kandung')
        ->toContain('Salinan Kartu Keluarga')
        ->toContain((string) config('surat.tujuan_pddikti'))
        ->toContain($surat->nomor);

    expect(app(SuratPdfService::class)->pdf($surat)->output())->toStartWith('%PDF');
});

describe('konsol /admin/feeder', function () {
    it('menawarkan satu surat untuk seluruh permintaan seorang mahasiswa', function () {
        $m = mhsPermohonan();
        permintaanPdm($m, 'nama_mahasiswa', 'A', 'B');
        permintaanPdm($m, 'tempat_lahir', 'Bandung', 'Bogor');

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.feeder'))
            ->assertOk()
            ->assertSee(route('admin.feeder.perubahan.surat'), false)
            ->assertSee('Terbitkan surat permohonan');

        expect(substr_count(
            $this->actingAs($this->staf, 'staff')->get(route('admin.feeder'))->getContent(),
            'action="'.route('admin.feeder.perubahan.surat').'"',
        ))->toBe(1);
    });

    it('menerbitkan surat dan menautkannya pada permintaan', function () {
        $m = mhsPermohonan();
        $a = permintaanPdm($m, 'nama_mahasiswa', 'A', 'B');
        $b = permintaanPdm($m, 'tempat_lahir', 'Bandung', 'Bogor');

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.feeder.perubahan.surat'), [
                'perubahan' => [$a->uuid, $b->uuid],
                'alasan' => 'Data ketik ulang dari ijazah SMA',
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $surat = Surat::query()->where('jenis', JenisSurat::PermohonanPdm->value)->sole();

        expect($a->fresh()->nomor_surat)->toBe($surat->nomor)
            ->and($b->fresh()->surat_id)->toBe($surat->id);

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.feeder'))
            ->assertSee(route('admin.surat.unduh', $surat), false);
    });

    it('mewajibkan alasan', function () {
        $p = permintaanPdm(mhsPermohonan(), 'nama_mahasiswa', 'A', 'B');

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.feeder.perubahan.surat'), ['perubahan' => [$p->uuid]])
            ->assertSessionHasErrors('alasan');

        expect(Surat::count())->toBe(0);
    });

    it('menolak staf tanpa izin sinkron Feeder', function () {
        $p = permintaanPdm(mhsPermohonan(), 'nama_mahasiswa', 'A', 'B');
        $tamu = Staff::factory()->create();

        $this->actingAs($tamu, 'staff')
            ->post(route('admin.feeder.perubahan.surat'), ['perubahan' => [$p->uuid], 'alasan' => 'x'])
            ->assertForbidden();

        expect(Surat::count())->toBe(0);
    });
});
