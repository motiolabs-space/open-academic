<?php

declare(strict_types=1);

use App\Services\Auth\Totp;

/**
 * TOTP diuji terhadap vektor resmi RFC 6238, bukan terhadap dirinya sendiri.
 *
 * Ini bedanya antara implementasi yang benar dan implementasi yang konsisten.
 * Kode yang salah tetap akan cocok dengan dirinya sendiri setiap kali diuji —
 * dan baru ketahuan ketika seorang kepala BAAK memasang Google Authenticator
 * lalu tidak pernah bisa masuk.
 *
 * Vektornya dari RFC 6238 Appendix B: rahasia ASCII "12345678901234567890",
 * SHA-1, 8 digit. Kelas ini mengeluarkan 6 digit, jadi yang dibandingkan enam
 * angka terakhirnya.
 */
beforeEach(function () {
    $this->totp = new Totp;

    // "12345678901234567890" dalam base32.
    $this->rahasia = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
});

describe('vektor RFC 6238', function () {
    it('menghasilkan kode yang sama dengan yang ditetapkan standar', function (int $waktu, string $delapanDigit) {
        expect($this->totp->kode($this->rahasia, $waktu))
            ->toBe(substr($delapanDigit, -6));
    })->with([
        [59, '94287082'],
        [1111111109, '07081804'],
        [1111111111, '14050471'],
        [1234567890, '89005924'],
        [2000000000, '69279037'],
        [20000000000, '65353130'],
    ]);
});

describe('verifikasi', function () {
    it('menerima kode pada detik yang sama', function () {
        $waktu = 1700000000;

        expect($this->totp->cocok($this->rahasia, $this->totp->kode($this->rahasia, $waktu), 1, $waktu))
            ->toBeTrue();
    });

    it('memaafkan selisih jam satu langkah ke tiap arah', function () {
        // Jam ponsel dan jam server jarang persis sama. Tanpa toleransi ini,
        // pengguna dengan jam meleset 20 detik tidak akan pernah bisa masuk.
        $waktu = 1700000000;

        foreach ([-30, 30] as $geser) {
            expect($this->totp->cocok($this->rahasia, $this->totp->kode($this->rahasia, $waktu + $geser), 1, $waktu))
                ->toBeTrue();
        }
    });

    it('menolak kode dari dua langkah yang lalu', function () {
        // Jendela yang lebar adalah sasaran tebakan yang lebih lebar untuk enam
        // angka yang sama.
        $waktu = 1700000000;

        expect($this->totp->cocok($this->rahasia, $this->totp->kode($this->rahasia, $waktu - 60), 1, $waktu))
            ->toBeFalse();
    });

    it('menolak masukan yang bukan enam angka', function () {
        foreach (['', '12345', '1234567', 'abcdef', '12 34 56'] as $sampah) {
            expect($this->totp->cocok($this->rahasia, $sampah))->toBeFalse();
        }
    });
});

describe('rahasia & URI', function () {
    it('menghasilkan rahasia base32 sepanjang 160 bit', function () {
        $rahasia = $this->totp->rahasiaBaru();

        // 20 bita = 160 bit = 32 huruf base32.
        expect($rahasia)->toHaveLength(32)
            ->and($rahasia)->toMatch('/^[A-Z2-7]+$/');
    });

    it('menghasilkan rahasia yang berbeda tiap kali', function () {
        expect($this->totp->rahasiaBaru())->not->toBe($this->totp->rahasiaBaru());
    });

    it('menyusun URI otpauth yang memuat penerbit dua kali', function () {
        // Sebagian aplikasi autentikator membaca awalan labelnya, sebagian lagi
        // membaca parameternya. Entri yang hanya bertuliskan "admin" tidak
        // terpakai di ponsel yang menyimpan selusin akun.
        $uri = $this->totp->uri('ABCDEFGHIJKLMNOP', 'admin@kampus.ac.id', 'Universitas Demo');

        expect($uri)->toStartWith('otpauth://totp/')
            ->and($uri)->toContain(rawurlencode('Universitas Demo:admin@kampus.ac.id'))
            ->and($uri)->toContain('issuer=Universitas+Demo')
            ->and($uri)->toContain('secret=ABCDEFGHIJKLMNOP')
            ->and($uri)->toContain('period=30');
    });
});
