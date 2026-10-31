<?php

declare(strict_types=1);

use App\Enums\JenisKonversi;
use App\Enums\SemesterType;
use App\Enums\StatusKonversi;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Notifications\Akademik\DokumenKonversiDiminta;
use App\Notifications\Akademik\KonversiDiputus;
use App\Services\Akademik\KonversiService;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * A student's view of their own credit conversions, and the document the
 * assessor may ask them for (ADR-040 §4). Until now a transfer student could
 * not see which of their courses had been recognised.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    Notification::fake();

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    Portal::lupakanTerm();
    $this->prodi = Prodi::factory()->create(['sks_lulus' => 144]);

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);
    $this->mahasiswa->assignRole('mahasiswa');
    $this->lain = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);
    $this->lain->assignRole('mahasiswa');

    $this->layanan = app(KonversiService::class);
});

function usulanKonversiUji(?Mahasiswa $mahasiswa = null, int $sks = 3): KonversiKredit
{
    return test()->layanan->ajukan(
        $mahasiswa ?? test()->mahasiswa,
        MataKuliah::factory()->create(['prodi_id' => test()->prodi->id, 'sks' => $sks]),
        JenisKonversi::Transfer,
        'Algoritma Dasar',
        'Universitas Asal Demo',
        3,
        'A',
    );
}

describe('layar konversi mahasiswa', function () {
    it('menampilkan usulan sendiri dengan status dan SKS yang diakui saja', function () {
        $diakui = usulanKonversiUji();
        $this->layanan->setujui($diakui, $this->baak, 2, 'A');
        $menunggu = usulanKonversiUji(sks: 4);
        $milikLain = usulanKonversiUji($this->lain);

        $respons = $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.konversi'))
            ->assertOk()
            ->assertSee($diakui->uuid)
            ->assertSee($menunggu->uuid)
            ->assertDontSee($milikLain->uuid)
            ->assertSee('2 SKS diakui');

        expect($respons->viewData('sudah'))->toBe(2)
            ->and($respons->viewData('batas'))->toBe($this->layanan->batas($this->mahasiswa));
    });

    it('membaca daftar milik mahasiswa lewat service', function () {
        usulanKonversiUji();
        usulanKonversiUji($this->lain);

        expect($this->layanan->milikMahasiswa($this->mahasiswa))->toHaveCount(1);
    });
});

describe('keputusan diberitahukan', function () {
    it('memberi tahu mahasiswa saat konversi diakui dan saat ditolak', function () {
        $a = usulanKonversiUji();
        $b = usulanKonversiUji();

        $this->layanan->setujui($a, $this->baak);
        $this->layanan->tolak($b, $this->baak, 'Silabus asal tidak setara.');

        Notification::assertSentToTimes($this->mahasiswa, KonversiDiputus::class, 2);
        Notification::assertSentTo($this->mahasiswa, KonversiDiputus::class,
            fn (KonversiDiputus $n): bool => str_contains($n->ringkasan($this->mahasiswa), 'Silabus asal tidak setara.'));
    });
});

describe('permintaan dokumen', function () {
    it('meminta, menerima, dan mengganti dokumen tanpa menyentuh berkas usulan', function () {
        $konversi = usulanKonversiUji();
        $konversi->update(['berkas_path' => 'konversi/usulan-staf.pdf']);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.konversi.minta-dokumen', $konversi), ['dokumen_diminta' => 'Silabus mata kuliah asal yang dilegalisasi.'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $konversi->refresh();
        expect($konversi->dokumen_diminta)->toBe('Silabus mata kuliah asal yang dilegalisasi.')
            ->and($konversi->menungguDokumen())->toBeTrue();
        Notification::assertSentTo($this->mahasiswa, DokumenKonversiDiminta::class);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.konversi.unggah', $konversi), ['berkas' => UploadedFile::fake()->create('silabus.pdf', 40, 'application/pdf')])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $pertama = $konversi->refresh()->berkas_mahasiswa_path;
        Storage::disk('local')->assertExists($pertama);
        expect($konversi->menungguDokumen())->toBeFalse()
            ->and($konversi->berkas_path)->toBe('konversi/usulan-staf.pdf');

        $this->layanan->unggahDokumen($konversi, $this->mahasiswa, UploadedFile::fake()->create('silabus-2.pdf', 40, 'application/pdf'));

        Storage::disk('local')->assertMissing($pertama);
        Storage::disk('local')->assertExists($konversi->refresh()->berkas_mahasiswa_path);

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.konversi', ['mahasiswa' => $this->mahasiswa->uuid]))
            ->assertOk()
            ->assertSee(route('berkas.konversi', [$konversi, 'mahasiswa']), false);
    });

    it('menolak unggahan bila tidak diminta, sudah diputus, atau bukan milik sendiri', function () {
        $konversi = usulanKonversiUji();
        $berkas = fn () => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf');

        expect(fn () => $this->layanan->unggahDokumen($konversi, $this->mahasiswa, $berkas()))
            ->toThrow(AturanAkademikException::class, 'Belum ada dokumen');

        $this->layanan->mintaDokumen($konversi, $this->baak, 'Transkrip asal.');

        expect(fn () => $this->layanan->unggahDokumen($konversi, $this->lain, $berkas()))
            ->toThrow(AturanAkademikException::class, 'bukan milik');

        $this->actingAs($this->lain, 'mahasiswa')
            ->post(route('mahasiswa.konversi.unggah', $konversi), ['berkas' => $berkas()])
            ->assertForbidden();

        $this->layanan->tolak($konversi, $this->baak, 'Tidak setara.');

        expect(fn () => $this->layanan->unggahDokumen($konversi->refresh(), $this->mahasiswa, $berkas()))
            ->toThrow(AturanAkademikException::class, 'sudah diputus');
        expect(fn () => $this->layanan->mintaDokumen($konversi, $this->baak, 'Lagi.'))
            ->toThrow(AturanAkademikException::class, 'sudah diputus');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.konversi.unggah', $konversi), ['berkas' => $berkas()])
            ->assertForbidden();

        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('hanya staf mahasiswa.manage yang meminta dokumen', function () {
        $konversi = usulanKonversiUji();

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')
            ->post(route('admin.konversi.minta-dokumen', $konversi), ['dokumen_diminta' => 'Transkrip asal.'])
            ->assertForbidden();

        expect($konversi->refresh()->dokumen_diminta)->toBeNull();
    });

    it('membuka berkas konversi hanya untuk pemiliknya dan staf', function () {
        $konversi = usulanKonversiUji();
        $this->layanan->mintaDokumen($konversi, $this->baak, 'Transkrip asal.');
        $this->layanan->unggahDokumen($konversi, $this->mahasiswa, UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'));

        $this->actingAs($this->lain, 'mahasiswa')->get(route('berkas.konversi', [$konversi, 'mahasiswa']))->assertForbidden();
        $this->actingAs($this->mahasiswa, 'mahasiswa')->get(route('berkas.konversi', [$konversi, 'mahasiswa']))->assertOk();
        $this->actingAs($this->baak, 'staff')->get(route('berkas.konversi', [$konversi, 'mahasiswa']))->assertOk();
        $this->actingAs($this->baak, 'staff')->get(route('berkas.konversi', [$konversi, 'usulan']))->assertNotFound();
    });
});

it('tetap tidak menghitung usulan yang belum diakui', function () {
    usulanKonversiUji();

    expect($this->layanan->sudahDiakui($this->mahasiswa))->toBe(0)
        ->and(KonversiKredit::where('mahasiswa_id', $this->mahasiswa->id)->sole()->status)->toBe(StatusKonversi::Diajukan);
});
