<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use Database\Seeders\RolePermissionSeeder;

/**
 * Penolakan Feeder harus sampai ke operator sebagai pesan, bukan halaman 500.
 *
 * Ditemukan dengan menyapu seluruh rute tulis memakai muatan kosong: dari 57
 * rute tanpa parameter, `POST /admin/feeder/referensi` satu-satunya yang
 * membalas 500. Sebabnya bukan validasi yang hilang melainkan
 * `FeederException` yang tidak punya penangan — `AturanAkademikException`
 * punya, dan keduanya jenis hal yang sama: operasi yang ditolak dengan alasan
 * yang memang ditulis untuk dibaca manusia.
 *
 * `FEEDER_ENABLED` bawaannya false, jadi keadaan ini bukan kasus pinggiran —
 * ia yang pertama ditemui setiap kampus yang belum menyalakan integrasinya.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Seluruh rute /admin dilindungi EnsureTermIsActive; tanpa term aktif ia
    // membalas 503 dan tesnya tidak pernah sampai ke controllernya.
    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');
});

it('mengembalikan pesan, bukan 500, saat integrasi Feeder dimatikan', function () {
    config(['feeder.enabled' => false]);

    $this->actingAs($this->staf, 'staff')
        ->post('/admin/feeder/referensi')
        ->assertRedirect()
        ->assertSessionHas('galat');
});

it('menyebutkan bendera konfigurasinya, supaya operator tahu apa yang kurang', function () {
    // Pesan "terjadi kesalahan" akan membuat operator membuka tiket. Menyebut
    // FEEDER_ENABLED membuatnya selesai sendiri dalam satu menit.
    config(['feeder.enabled' => false]);

    $this->actingAs($this->staf, 'staff')
        ->post('/admin/feeder/referensi')
        ->assertSessionHas('galat', fn (string $pesan): bool => str_contains($pesan, 'FEEDER_ENABLED'));
});

it('menjawab JSON 422 untuk pemanggil yang meminta JSON', function () {
    config(['feeder.enabled' => false]);

    $this->actingAs($this->staf, 'staff')
        ->postJson('/admin/feeder/referensi')
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});
