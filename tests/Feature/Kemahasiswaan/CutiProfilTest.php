<?php

declare(strict_types=1);

use App\Enums\KrsStatus;
use App\Enums\LeaveStatus;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Krs;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\CutiService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $this->mahasiswa = Mahasiswa::factory()->create(['status' => StudentStatus::Aktif]);
    $this->mahasiswa->assignRole('mahasiswa');

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->cuti = app(CutiService::class);
});

describe('pengajuan cuti', function () {
    it('menolak cuti bila mahasiswa masih memegang KRS aktif', function () {
        // Seseorang tidak dapat sekaligus mengambil kelas dan sedang cuti;
        // kalau lolos, kelas punya mahasiswa terdaftar yang tak pernah hadir.
        Krs::factory()->create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'status' => KrsStatus::Disetujui,
        ]);

        expect(fn () => $this->cuti->ajukan($this->mahasiswa, $this->term, 'akademik', 'Alasan yang cukup panjang.'))
            ->toThrow(AturanAkademikException::class, 'KRS aktif');
    });

    it('menolak cuti dari mahasiswa yang tidak berstatus aktif', function () {
        $this->mahasiswa->update(['status' => StudentStatus::Cuti]);

        expect(fn () => $this->cuti->ajukan($this->mahasiswa, $this->term, 'akademik', 'Alasan yang cukup panjang.'))
            ->toThrow(AturanAkademikException::class, 'berstatus aktif');
    });

    it('menolak pengajuan ganda untuk semester yang sama', function () {
        $this->cuti->ajukan($this->mahasiswa, $this->term, 'akademik', 'Alasan pertama yang panjang.');

        expect(fn () => $this->cuti->ajukan($this->mahasiswa, $this->term, 'sakit', 'Alasan kedua yang panjang.'))
            ->toThrow(AturanAkademikException::class, 'sudah pernah dibuat');
    });
});

describe('keputusan cuti', function () {
    it('memindahkan status mahasiswa saat cuti disetujui', function () {
        $cuti = $this->cuti->ajukan($this->mahasiswa, $this->term, 'akademik', 'Alasan yang cukup panjang.');

        $this->cuti->setujui($cuti, $this->staf);

        // Persetujuan tanpa perubahan status adalah justru inkonsistensi yang
        // terlanjur dilaporkan ke PDDIKTI lalu harus dibetulkan manual.
        expect($cuti->fresh()->status)->toBe(LeaveStatus::Disetujui)
            ->and($this->mahasiswa->fresh()->status)->toBe(StudentStatus::Cuti);
    });

    it('mengembalikan status aktif saat cuti diakhiri', function () {
        $cuti = $this->cuti->ajukan($this->mahasiswa, $this->term, 'akademik', 'Alasan yang cukup panjang.');
        $this->cuti->setujui($cuti, $this->staf);

        $this->cuti->aktifkanKembali($cuti->fresh(), $this->staf);

        expect($this->mahasiswa->fresh()->status)->toBe(StudentStatus::Aktif);
    });

    it('tidak mengubah status saat cuti ditolak', function () {
        $cuti = $this->cuti->ajukan($this->mahasiswa, $this->term, 'akademik', 'Alasan yang cukup panjang.');

        $this->cuti->tolak($cuti, $this->staf, 'Berkas pendukung belum lengkap.');

        expect($cuti->fresh()->status)->toBe(LeaveStatus::Ditolak)
            ->and($this->mahasiswa->fresh()->status)->toBe(StudentStatus::Aktif);
    });

    it('menolak memutus pengajuan yang sudah diputus', function () {
        $cuti = $this->cuti->ajukan($this->mahasiswa, $this->term, 'akademik', 'Alasan yang cukup panjang.');
        $this->cuti->setujui($cuti, $this->staf);

        expect(fn () => $this->cuti->tolak($cuti->fresh(), $this->staf, 'Berubah pikiran.'))
            ->toThrow(AturanAkademikException::class, 'sudah diputus');
    });

    it('mewajibkan alasan pada penolakan lewat layar', function () {
        $cuti = $this->cuti->ajukan($this->mahasiswa, $this->term, 'akademik', 'Alasan yang cukup panjang.');

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/cuti/{$cuti->uuid}/tolak", ['catatan' => ''])
            ->assertSessionHasErrors('catatan');
    });

    it('merender layar cuti', function () {
        $this->cuti->ajukan($this->mahasiswa, $this->term, 'akademik', 'Alasan yang cukup panjang.');

        $this->actingAs($this->staf, 'staff')->get('/admin/cuti')->assertOk();
    });
});

describe('profil mahasiswa', function () {
    it('merender profil dengan daftar kelengkapan PDDIKTI', function () {
        $this->mahasiswa->update(['nik' => null]);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get('/mahasiswa/profil')
            ->assertOk()
            ->assertSee('NIK');
    });

    it('mengizinkan mahasiswa melengkapi NIK yang masih kosong', function () {
        $this->mahasiswa->update(['nik' => null]);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->put('/mahasiswa/profil', ['nik' => '3201234567890001'])
            ->assertSessionHasNoErrors();

        expect($this->mahasiswa->fresh()->nik)->toBe('3201234567890001');
    });

    it('menolak mahasiswa mengubah NIK yang sudah terisi', function () {
        // NIK yang berubah adalah salah input, dan membetulkannya adalah
        // pekerjaan BAAK yang meninggalkan jejak — bukan sekali klik mahasiswa.
        $this->mahasiswa->update(['nik' => '3201234567890001']);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->put('/mahasiswa/profil', ['nik' => '9999999999999999'])
            ->assertSessionHasErrors('nik');

        expect($this->mahasiswa->fresh()->nik)->toBe('3201234567890001');
    });

    it('tidak mengizinkan mahasiswa mengubah data yang ditetapkan kampus', function () {
        $nimAsli = $this->mahasiswa->nim;

        $this->actingAs($this->mahasiswa, 'mahasiswa')->put('/mahasiswa/profil', [
            'nim' => '99999999',
            'nama' => 'Nama Palsu',
            'telepon' => '08123456789',
        ]);

        $segar = $this->mahasiswa->fresh();

        expect($segar->nim)->toBe($nimAsli)
            ->and($segar->nama)->not->toBe('Nama Palsu')
            ->and($segar->telepon)->toBe('08123456789');
    });

    it('mengganti kata sandi hanya bila yang lama cocok', function () {
        $this->mahasiswa->forceFill(['password' => Hash::make('lama-sekali')])->save();

        $this->actingAs($this->mahasiswa, 'mahasiswa')->post('/mahasiswa/profil/kata-sandi', [
            'kata_sandi_lama' => 'salah',
            'kata_sandi' => 'rahasia-baru',
            'kata_sandi_confirmation' => 'rahasia-baru',
        ])->assertSessionHasErrors('kata_sandi_lama');

        $this->actingAs($this->mahasiswa, 'mahasiswa')->post('/mahasiswa/profil/kata-sandi', [
            'kata_sandi_lama' => 'lama-sekali',
            'kata_sandi' => 'rahasia-baru',
            'kata_sandi_confirmation' => 'rahasia-baru',
        ])->assertSessionHasNoErrors();

        expect(Hash::check('rahasia-baru', $this->mahasiswa->fresh()->password))->toBeTrue();
    });
});
