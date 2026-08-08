<?php

declare(strict_types=1);

use App\Enums\JenisUjian;
use App\Enums\PeranPembimbing;
use App\Enums\PeranPenguji;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\TugasAkhir;
use App\Services\TugasAkhir\BimbinganService;
use App\Services\TugasAkhir\TugasAkhirService;
use App\Services\TugasAkhir\UjianService;
use Database\Seeders\RolePermissionSeeder;

/**
 * Layar tugas akhir, dan siapa yang boleh menyentuh apa.
 *
 * Bagian terpenting berkas ini bukan render-nya, melainkan kasus lintas objek:
 * "sudah masuk sebagai dosen" tidak sama dengan "pembimbing karya ini", dan
 * "sudah masuk sebagai mahasiswa" tidak sama dengan "pemilik log ini".
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $this->prodi = Prodi::factory()->create(['sks_lulus' => 144]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->ta = app(TugasAkhirService::class);
    $this->bimbingan = app(BimbinganService::class);
    $this->ujian = app(UjianService::class);
});

/** Mahasiswa aktif yang SKS-nya sudah cukup untuk mengajukan. */
function mahasiswaLayar(): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);

    $mahasiswa->assignRole('mahasiswa');

    StatusMahasiswa::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 7,
        'status' => StudentStatus::Aktif,
        'sks_semester' => 20,
        'sks_kumulatif' => 120,
        'ips' => 3.5,
        'ipk' => 3.5,
    ]);

    return $mahasiswa->fresh();
}

/** Dosen dengan peran "dosen" pada guard-nya. */
function dosenLayar(): Dosen
{
    $dosen = Dosen::factory()->create(['is_active' => true]);
    $dosen->assignRole('dosen');

    return $dosen;
}

/** Tugas akhir yang sudah dibimbing, beserta mahasiswa dan pembimbingnya. */
function taLayar(?Mahasiswa $mahasiswa = null, ?Dosen $dosen = null): TugasAkhir
{
    $mahasiswa ??= mahasiswaLayar();
    $dosen ??= dosenLayar();

    $ta = test()->ta->ajukan($mahasiswa, test()->term, 'Judul Uji Layar');
    test()->ta->setujuiJudul($ta, test()->staf);
    test()->ta->tetapkanPembimbing($ta->fresh(), $dosen, PeranPembimbing::Utama);

    return $ta->fresh();
}

describe('layar admin', function () {
    it('merender daftar tugas akhir', function () {
        taLayar();

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.tugas-akhir'))
            ->assertOk()
            ->assertSee('Judul Uji Layar');
    });

    it('merender layar kelola', function () {
        $ta = taLayar();

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.tugas-akhir.show', $ta))
            ->assertOk()
            ->assertSee('Pembimbing');
    });

    it('menolak staf tanpa izin tugas akhir', function () {
        $ta = taLayar();

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')
            ->get(route('admin.tugas-akhir'))
            ->assertForbidden();

        $this->actingAs($keuangan, 'staff')
            ->post(route('admin.tugas-akhir.setujui', $ta))
            ->assertForbidden();
    });

    it('menetapkan pembimbing lewat layar', function () {
        $mahasiswa = mahasiswaLayar();
        $ta = $this->ta->ajukan($mahasiswa, $this->term, 'Judul');
        $this->ta->setujuiJudul($ta, $this->staf);

        $dosen = dosenLayar();

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.tugas-akhir.pembimbing', $ta), [
                'dosen_id' => $dosen->id,
                'peran' => PeranPembimbing::Utama->value,
            ])
            ->assertRedirect();

        expect($ta->fresh()->pembimbing)->toHaveCount(1);
    });
});

describe('layar mahasiswa', function () {
    it('merender formulir pengajuan saat belum ada tugas akhir', function () {
        $mahasiswa = mahasiswaLayar();

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.tugas-akhir'))
            ->assertOk()
            ->assertSee('Ajukan Judul');
    });

    it('mengajukan judul lewat layar', function () {
        $mahasiswa = mahasiswaLayar();

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.tugas-akhir.ajukan'), [
                'judul' => 'Analisis Beban Basis Data pada SIAKAD Multi-Kampus',
            ])
            ->assertRedirect();

        expect($mahasiswa->fresh()->tugasAkhirAktif)->not->toBeNull();
    });

    it('menolak mencatat bimbingan atas nama dosen yang bukan pembimbingnya', function () {
        $mahasiswa = mahasiswaLayar();
        taLayar($mahasiswa);
        $asing = dosenLayar();

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.tugas-akhir.bimbingan'), [
                'dosen_id' => $asing->id,
                'tanggal' => now()->toDateString(),
                'topik' => 'Bab 1',
            ])
            ->assertSessionHasErrors('dosen_id');
    });

    it('menolak mahasiswa menghapus catatan bimbingan milik mahasiswa lain', function () {
        // Tanpa pemeriksaan ini, seorang mahasiswa dapat menghapus log temannya
        // dan diam-diam menjatuhkannya di bawah ambang untuk sidang.
        $korban = mahasiswaLayar();
        $dosen = dosenLayar();
        $ta = taLayar($korban, $dosen);

        $log = $this->bimbingan->catat($ta->fresh(), $dosen, now()->toDateString(), 'Bab 1');

        $penyerang = mahasiswaLayar();

        $this->actingAs($penyerang, 'mahasiswa')
            ->delete(route('mahasiswa.tugas-akhir.bimbingan.hapus', $log))
            ->assertForbidden();

        expect($log->fresh())->not->toBeNull();
    });
});

describe('layar dosen', function () {
    it('merender daftar bimbingan', function () {
        $dosen = dosenLayar();
        taLayar(null, $dosen);

        $this->actingAs($dosen, 'dosen')
            ->get(route('dosen.tugas-akhir'))
            ->assertOk()
            ->assertSee('Judul Uji Layar');
    });

    it('menolak dosen membuka tugas akhir yang tidak dibimbing atau diujinya', function () {
        $ta = taLayar();
        $asing = dosenLayar();

        $this->actingAs($asing, 'dosen')
            ->get(route('dosen.tugas-akhir.show', $ta))
            ->assertForbidden();
    });

    it('mengizinkan penguji luar membaca karya yang akan diujinya', function () {
        // Penguji tidak punya baris pembimbing, tetapi tetap harus dapat
        // membaca karya yang sebentar lagi dinilainya.
        $dosen = dosenLayar();
        $ta = taLayar(null, $dosen);

        for ($i = 0; $i < 8; $i++) {
            $log = $this->bimbingan->catat($ta->fresh(), $dosen, now()->subDays(10 - $i)->toDateString(), "Bab {$i}");
            $this->bimbingan->setujui($log, $dosen);
        }

        $luar = dosenLayar();

        $this->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Sidang,
            now()->addWeek()->toDateString(),
            '09:00',
            '11:00',
            [['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua]],
        );

        $this->actingAs($luar, 'dosen')
            ->get(route('dosen.tugas-akhir.show', $ta))
            ->assertOk();
    });

    it('menolak dosen menyetujui log bimbingan yang bukan atas namanya', function () {
        $utama = dosenLayar();
        $ta = taLayar(null, $utama);

        $pendamping = dosenLayar();
        $this->ta->tetapkanPembimbing($ta->fresh(), $pendamping, PeranPembimbing::Pendamping);

        $log = $this->bimbingan->catat($ta->fresh(), $utama, now()->toDateString(), 'Bab 1');

        // Pembimbing kedua pun ditolak: ia tidak menghadiri pertemuan itu.
        $this->actingAs($pendamping, 'dosen')
            ->post(route('dosen.tugas-akhir.bimbingan.setujui', $log))
            ->assertForbidden();

        expect($log->fresh()->disetujui)->toBeFalse();
    });

    it('menolak dosen mengisi nilai kursi penguji milik orang lain', function () {
        $dosen = dosenLayar();
        $ta = taLayar(null, $dosen);

        for ($i = 0; $i < 8; $i++) {
            $log = $this->bimbingan->catat($ta->fresh(), $dosen, now()->subDays(10 - $i)->toDateString(), "Bab {$i}");
            $this->bimbingan->setujui($log, $dosen);
        }

        $luar = dosenLayar();

        $hasil = $this->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Sidang,
            now()->addWeek()->toDateString(),
            '09:00',
            '11:00',
            [['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua]],
        );

        $kursiLuar = $hasil['ujian']->penguji()->first();
        $penyusup = dosenLayar();

        $this->actingAs($penyusup, 'dosen')
            ->post(route('dosen.tugas-akhir.penguji.nilai', $kursiLuar), ['nilai' => 100])
            ->assertForbidden();

        expect($kursiLuar->fresh()->nilai)->toBeNull();
    });
});
