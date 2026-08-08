<?php

declare(strict_types=1);

use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\Setting;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);

    $this->admin = Staff::factory()->create();
    $this->admin->assignRole('super-admin');
});

describe('log aktivitas', function () {
    it('merender penampil log', function () {
        $this->actingAs($this->admin, 'staff')->get('/admin/log')->assertOk();
    });

    it('menolak staf tanpa izin log', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get('/admin/log')->assertForbidden();
    });

    it('tidak menyediakan rute untuk mengubah atau menghapus catatan', function () {
        // Jejak audit yang bisa dikoreksi bukan bukti apa pun. Dijaga di
        // tingkat rute, bukan sekadar tidak ada tombolnya di layar.
        $rute = collect(Route::getRoutes())->filter(
            fn ($r) => str_starts_with($r->uri(), 'admin/log'),
        );

        expect($rute)->toHaveCount(1)
            ->and($rute->first()->methods())->toContain('GET');
    });
});

describe('pengaturan', function () {
    it('menyimpan identitas institusi', function () {
        $this->actingAs($this->admin, 'staff')->put('/admin/pengaturan', [
            'institution_name' => 'Universitas Contoh',
            'institution_short' => 'UC',
            'institution_code' => '001002',
            'primary_color' => '#123456',
            'accent_color' => '#ABCDEF',
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect(Setting::get('branding', 'institution_name'))->toBe('Universitas Contoh');
    });

    it('menolak warna yang bukan kode heks', function () {
        $this->actingAs($this->admin, 'staff')->put('/admin/pengaturan', [
            'institution_name' => 'Universitas Contoh',
            'institution_short' => 'UC',
            'primary_color' => 'biru tua',
            'accent_color' => '#ABCDEF',
        ])->assertSessionHasErrors('primary_color');
    });

    it('merender layar pengaturan', function () {
        $this->actingAs($this->admin, 'staff')->get('/admin/pengaturan')->assertOk();
    });
});

describe('koreksi nilai', function () {
    beforeEach(function () {
        $this->mahasiswa = Mahasiswa::factory()->create(['nama' => 'Budi Koreksi']);
        $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id]);
        $krs = Krs::factory()->create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
        ]);
        // Tidak ada factory untuk KrsDetail; dibuat langsung.
        $detail = KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_kuliah_id' => $kelas->id,
            'sks' => 3,
            'is_mengulang' => false,
        ]);

        $this->nilai = Nilai::create([
            'krs_detail_id' => $detail->id,
            'mahasiswa_id' => $this->mahasiswa->id,
            'kelas_kuliah_id' => $kelas->id,
            'nilai_angka' => 60,
            'nilai_huruf' => 'C',
            'bobot' => 2.0,
            'is_final' => true,
        ]);
    });

    it('menemukan nilai final lewat pencarian nama', function () {
        $this->actingAs($this->admin, 'staff')
            ->get('/admin/koreksi-nilai?cari=Budi')
            ->assertOk()
            ->assertSee('Budi Koreksi');
    });

    it('mewajibkan alasan yang cukup panjang', function () {
        $this->actingAs($this->admin, 'staff')
            ->post("/admin/koreksi-nilai/{$this->nilai->uuid}", [
                'nilai_angka' => 85,
                'alasan' => 'salah',
            ])->assertSessionHasErrors('alasan');

        expect((float) $this->nilai->fresh()->nilai_angka)->toBe(60.0);
    });

    it('mengubah nilai dan menyimpan alasannya', function () {
        $this->actingAs($this->admin, 'staff')
            ->post("/admin/koreksi-nilai/{$this->nilai->uuid}", [
                'nilai_angka' => 85,
                'alasan' => 'Terdapat lembar jawaban UAS yang belum terhitung saat finalisasi.',
            ])->assertSessionHas('sukses');

        $segar = $this->nilai->fresh();

        expect((float) $segar->nilai_angka)->toBe(85.0)
            ->and($segar->catatan_koreksi)->toContain('belum terhitung');
    });

    it('menolak dosen mengakses layar koreksi staf', function () {
        $dosen = Dosen::factory()->create();
        $dosen->assignRole('dosen');

        $this->actingAs($dosen, 'dosen')->get('/admin/koreksi-nilai')->assertRedirect();
    });
});

describe('presensi mandiri mahasiswa', function () {
    beforeEach(function () {
        $this->mahasiswa = Mahasiswa::factory()->create();
        $this->mahasiswa->assignRole('mahasiswa');

        $this->kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id]);
        $krs = Krs::factory()->create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'status' => 'disetujui',
        ]);
        KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_kuliah_id' => $this->kelas->id,
            'sks' => 3,
            'is_mengulang' => false,
        ]);

        $this->pertemuan = PertemuanKelas::create([
            'kelas_kuliah_id' => $this->kelas->id,
            'pertemuan_ke' => 1,
            'tanggal' => now()->toDateString(),
            'qr_token' => 'token-uji-presensi',
            'qr_expires_at' => now()->addMinutes(10),
        ]);
    });

    it('mencatat kehadiran dengan kode yang sah', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post('/mahasiswa/presensi', ['token' => 'token-uji-presensi'])
            ->assertSessionHas('sukses');

        expect($this->pertemuan->presensi()->where('mahasiswa_id', $this->mahasiswa->id)->exists())->toBeTrue();
    });

    it('menolak kode yang sudah kedaluwarsa', function () {
        $this->pertemuan->update(['qr_expires_at' => now()->subMinute()]);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post('/mahasiswa/presensi', ['token' => 'token-uji-presensi'])
            ->assertSessionHas('galat');
    });

    it('menolak mahasiswa yang tidak terdaftar pada kelas itu', function () {
        $orangLain = Mahasiswa::factory()->create();
        $orangLain->assignRole('mahasiswa');

        $this->actingAs($orangLain, 'mahasiswa')
            ->post('/mahasiswa/presensi', ['token' => 'token-uji-presensi'])
            ->assertSessionHas('galat');
    });

    it('merender halaman pindai dengan kode dari URL', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get('/mahasiswa/presensi?token=token-uji-presensi')
            ->assertOk()
            ->assertSee('token-uji-presensi', false);
    });
});
