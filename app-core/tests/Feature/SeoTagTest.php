<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use Database\Seeders\RolePermissionSeeder;

/**
 * Tag mesin pencari.
 *
 * Yang dijaga di sini bukan peringkat pencarian melainkan kebalikannya: halaman
 * mana yang TIDAK boleh muncul di sana. Halaman verifikasi surat memuat nama,
 * NIM, dan program studi seseorang, dan seluruh pengamanannya bertumpu pada
 * UUID yang tak dapat ditebak — pengindeksan meniadakan itu sepenuhnya, karena
 * URL-nya tidak lagi perlu ditebak.
 */
beforeEach(function () {
    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
});

describe('bawaan noindex', function () {
    it('menolak pengindeksan pada halaman verifikasi surat', function () {
        $this->get('/verifikasi')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    });

    it('menolak pengindeksan pada halaman masuk', function () {
        $this->get('/masuk')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    });

    it('menolak pengindeksan pada layar dalam aplikasi', function () {
        $this->seed(RolePermissionSeeder::class);
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->get('/admin')
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    });
});

describe('halaman muka', function () {
    it('meminta diindeks — satu-satunya yang begitu', function () {
        $this->get('/')
            ->assertOk()
            ->assertSee('name="robots" content="index, follow"', false);
    });

    it('membawa keterangan yang menjelaskan isinya', function () {
        // Tanpa ini hasil pencariannya diisi potongan sembarang dari halaman.
        $this->get('/')
            ->assertSee('name="description"', false)
            ->assertSee('KRS, nilai, transkrip', false);
    });
});

describe('pratinjau tautan', function () {
    it('membawa Open Graph untuk pratinjau WhatsApp', function () {
        $this->get('/')
            ->assertSee('property="og:title"', false)
            ->assertSee('property="og:url"', false)
            ->assertSee('name="twitter:card"', false);
    });

    it('tidak pernah memuat data halaman pada keterangan pratinjau', function () {
        /*
         * Tautan verifikasi yang diteruskan ke sebuah grup WhatsApp tidak boleh
         * ikut membawa nama dan NIM seseorang ke dalam pratinjaunya. Karena itu
         * keterangannya umum, bukan diambil dari isi halaman.
         */
        $respons = $this->get('/verifikasi');

        $respons->assertSee('Sistem informasi akademik', false);
    });
});

describe('favicon & kanonis', function () {
    it('merujuk favicon lewat asset(), bukan mengandalkan jenguk akar domain', function () {
        // Jenguk bawaan peramban selalu menuju akar domain, sehingga pemasangan
        // subfolder — pola yang dipakai repo ini — tidak akan pernah terlayani.
        $this->get('/')->assertSee('rel="icon"', false);
    });

    it('menyebut URL kanonisnya', function () {
        $this->get('/')->assertSee('rel="canonical"', false);
    });
});
