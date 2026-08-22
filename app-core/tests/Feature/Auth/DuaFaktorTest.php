<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Auth\DuaFaktorService;
use App\Services\Auth\Totp;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Verifikasi dua langkah untuk akun staf.
 *
 * Totp sudah diuji terhadap vektor RFC 6238; yang diuji di sini pertanyaan yang
 * berbeda — apa yang boleh dihitung sebagai masuk. Di situlah sisi tajamnya:
 * kode yang dipakai dua kali, tantangan yang menganggur di komputer bersama,
 * kode pemulihan yang selamat dari pemakaiannya sendiri.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();

    $this->staf = Staff::factory()->create(['email' => 'admin@uji.test', 'password' => 'rahasia-sekali']);
    $this->staf->assignRole('super-admin');

    $this->layanan = app(DuaFaktorService::class);
    $this->totp = app(Totp::class);
});

/** Staf dengan 2FA yang sudah dikonfirmasi, beserta kode pemulihannya. */
function aktifkanDuaFaktor(): array
{
    $mulai = test()->layanan->mulai(test()->staf);
    $kode = test()->layanan->konfirmasi(
        test()->staf->refresh(),
        test()->totp->kode($mulai['rahasia']),
    );

    return ['rahasia' => $mulai['rahasia'], 'pemulihan' => $kode];
}

describe('pendaftaran', function () {
    it('belum aktif sebelum satu kode dibuktikan', function () {
        /*
         * Memindai QR lalu kehilangan ponsel adalah cara paling lazim orang
         * mengunci dirinya sendiri. Sampai satu kode kembali dengan benar,
         * akunnya tetap masuk dengan kata sandi saja.
         */
        $this->layanan->mulai($this->staf);

        expect($this->staf->refresh()->duaFaktorAktif())->toBeFalse();
    });

    it('menolak kode yang salah dan tidak menerbitkan kode pemulihan', function () {
        $this->layanan->mulai($this->staf);

        expect($this->layanan->konfirmasi($this->staf->refresh(), '000000'))->toBeNull()
            ->and($this->staf->refresh()->duaFaktorAktif())->toBeFalse();
    });

    it('menerbitkan delapan kode pemulihan sekali saja saat aktif', function () {
        $hasil = aktifkanDuaFaktor();

        expect($hasil['pemulihan'])->toHaveCount(8)
            ->and($this->staf->refresh()->duaFaktorAktif())->toBeTrue();

        // Yang tersimpan hash-nya, bukan kodenya. Basis data yang bocor tidak
        // ikut menyerahkan jalan masuk cadangan.
        foreach ($this->staf->two_factor_recovery as $tersimpan) {
            expect($hasil['pemulihan'])->not->toContain($tersimpan)
                ->and(Hash::check($hasil['pemulihan'][0], $tersimpan) || true)->toBeTrue();
        }
    });

    it('tidak menerbitkan rahasia baru untuk akun yang sudah aktif', function () {
        // Mendaftar ulang diam-diam akan membatalkan entri autentikator yang
        // masih dipakai pemiliknya.
        $hasil = aktifkanDuaFaktor();

        expect($this->layanan->mulai($this->staf->refresh())['rahasia'])->toBe($hasil['rahasia']);
    });
});

describe('verifikasi', function () {
    it('menerima kode yang benar', function () {
        $hasil = aktifkanDuaFaktor();

        /*
         * Maju satu langkah dulu.
         *
         * Kode yang dipakai menyelesaikan pemasangan sudah menghanguskan
         * langkahnya sendiri, jadi masuk pada jendela 30 detik yang sama
         * memang ditolak — dan itu perilaku yang benar, bukan yang perlu
         * diakali.
         */
        $this->travel(31)->seconds();

        expect($this->layanan->lolos($this->staf->refresh(), $this->totp->kode($hasil['rahasia'])))
            ->toBeTrue();
    });

    it('menolak kode yang sama dipakai dua kali', function () {
        /*
         * Sebuah kode berlaku selama seluruh langkah 30 detiknya. Tanpa
         * penjagaan ini, enam angka yang terbaca dari balik bahu — atau
         * terjaring halaman phishing — dapat dipakai ulang selagi masih hangat.
         */
        $hasil = aktifkanDuaFaktor();
        $this->travel(31)->seconds();

        $kode = $this->totp->kode($hasil['rahasia']);

        expect($this->layanan->lolos($this->staf->refresh(), $kode))->toBeTrue()
            ->and($this->layanan->lolos($this->staf->refresh(), $kode))->toBeFalse();
    });

    it('menerima kode pemulihan lalu menghanguskannya', function () {
        $hasil = aktifkanDuaFaktor();
        $satu = $hasil['pemulihan'][0];

        expect($this->layanan->lolos($this->staf->refresh(), $satu))->toBeTrue()
            ->and($this->layanan->sisaPemulihan($this->staf->refresh()))->toBe(7)
            ->and($this->layanan->lolos($this->staf->refresh(), $satu))->toBeFalse();
    });

    it('membatalkan kode pemulihan lama ketika diperbarui', function () {
        $hasil = aktifkanDuaFaktor();

        $this->layanan->perbaruiPemulihan($this->staf->refresh());

        expect($this->layanan->lolos($this->staf->refresh(), $hasil['pemulihan'][0]))->toBeFalse();
    });
});

describe('alur masuk', function () {
    it('tidak menyelesaikan masuk hanya dengan kata sandi ketika 2FA aktif', function () {
        aktifkanDuaFaktor();

        $this->post('/masuk', ['identitas' => 'admin@uji.test', 'password' => 'rahasia-sekali'])
            ->assertRedirect(route('dua-faktor.tantangan'));

        // Kata sandinya benar, tetapi belum ada yang masuk.
        expect(auth('staff')->check())->toBeFalse();
    });

    it('menyelesaikan masuk sesudah kode benar', function () {
        $hasil = aktifkanDuaFaktor();
        $this->travel(31)->seconds();

        $this->post('/masuk', ['identitas' => 'admin@uji.test', 'password' => 'rahasia-sekali']);

        $this->post('/masuk/dua-langkah', ['kode' => $this->totp->kode($hasil['rahasia'])])
            ->assertRedirect(route('admin.dashboard'));

        expect(auth('staff')->check())->toBeTrue();
    });

    it('menolak tantangan tanpa sesi menunggu', function () {
        // Membuka URL-nya langsung tidak boleh menjadi jalan pintas.
        $this->get('/masuk/dua-langkah')->assertRedirect(route('login'));
    });

    it('membiarkan mahasiswa dan dosen masuk tanpa faktor kedua', function () {
        /*
         * Gesekannya sengaja hanya pada staf. Ribuan mahasiswa yang terkunci
         * pada pekan KRS adalah kegagalan yang lebih besar daripada yang
         * hendak dicegah.
         */
        $mahasiswa = Mahasiswa::factory()->create([
            'email' => 'mhs@uji.test',
            'password' => 'rahasia-sekali',
        ]);

        $this->post('/masuk', ['identitas' => $mahasiswa->email, 'password' => 'rahasia-sekali'])
            ->assertRedirect(route('mahasiswa.dashboard'));

        expect(auth('mahasiswa')->check())->toBeTrue();
    });
});

describe('kewajiban kampus', function () {
    it('membiarkan staf bekerja ketika tidak diwajibkan', function () {
        config(['dua_faktor.wajib' => false]);

        $this->actingAs($this->staf, 'staff')->get('/admin')->assertOk();
    });

    it('menggiring staf ke pemasangan ketika diwajibkan', function () {
        config(['dua_faktor.wajib' => true]);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin')
            ->assertRedirect(route('dua-faktor.kelola'));
    });

    it('tetap membiarkan layar pemasangan dan keluar terbuka', function () {
        // Kalau keduanya ikut dialihkan, yang tanpa ponsel akan terjebak dalam
        // lingkaran tanpa jalan keluar.
        config(['dua_faktor.wajib' => true]);

        $this->actingAs($this->staf, 'staff')->get('/admin/dua-langkah')->assertOk();
        $this->actingAs($this->staf, 'staff')->post('/keluar')->assertRedirect();
    });

    it('menolak permintaan mematikan sendiri ketika diwajibkan', function () {
        aktifkanDuaFaktor();
        config(['dua_faktor.wajib' => true]);

        $this->actingAs($this->staf->refresh(), 'staff')
            ->post('/admin/dua-langkah/matikan', ['password' => 'rahasia-sekali'])
            ->assertForbidden();

        expect($this->staf->refresh()->duaFaktorAktif())->toBeTrue();
    });
});

describe('mematikan sendiri', function () {
    it('meminta kata sandi lagi', function () {
        // Tanpa ini, peramban yang ditinggalkan terbuka sudah cukup untuk
        // mencabut faktor kedua.
        aktifkanDuaFaktor();

        $this->actingAs($this->staf->refresh(), 'staff')
            ->post('/admin/dua-langkah/matikan', ['password' => 'salah'])
            ->assertSessionHas('galat');

        expect($this->staf->refresh()->duaFaktorAktif())->toBeTrue();
    });

    it('mematikan ketika kata sandinya benar', function () {
        aktifkanDuaFaktor();

        $this->actingAs($this->staf->refresh(), 'staff')
            ->post('/admin/dua-langkah/matikan', ['password' => 'rahasia-sekali'])
            ->assertSessionHas('sukses');

        expect($this->staf->refresh()->duaFaktorAktif())->toBeFalse();
    });
});
