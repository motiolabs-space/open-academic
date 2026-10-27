<?php

declare(strict_types=1);

use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

/**
 * Staff administration cannot be used to climb to the top role.
 *
 * `pengaturan.manage` also gates announcements, SPMI and performance targets,
 * so the case that matters is a campus that hands it to a non-admin role —
 * here "humas" — for one of those screens.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TahunAkademik::factory()->berjalan()->create(['is_active' => true]);

    Role::create(['name' => 'humas', 'guard_name' => 'staff'])->givePermissionTo('pengaturan.manage');

    $this->humas = Staff::factory()->create();
    $this->humas->assignRole('humas');

    $this->admin = Staff::factory()->create();
    $this->admin->assignRole('super-admin');
});

function isianStaf(Staff $staf, string $peran): array
{
    return ['nama' => $staf->nama, 'email' => $staf->email, 'nip' => $staf->nip, 'peran' => $peran];
}

it('menolak pemegang pengaturan.manage yang mengangkat dirinya menjadi super-admin', function () {
    $this->actingAs($this->humas, 'staff')
        ->put(route('admin.staff.update', $this->humas), isianStaf($this->humas, 'super-admin'))
        ->assertForbidden();

    expect($this->humas->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('menolak pemberian peran super-admin oleh selain super-admin', function () {
    $lain = Staff::factory()->create();
    $lain->assignRole('baak');

    $this->actingAs($this->humas, 'staff')
        ->put(route('admin.staff.update', $lain), isianStaf($lain, 'super-admin'))
        ->assertForbidden();

    $this->actingAs($this->humas, 'staff')
        ->post(route('admin.staff.store'), ['nama' => 'Baru', 'email' => 'baru@kampus.test', 'peran' => 'super-admin'])
        ->assertForbidden();

    expect($lain->fresh()->hasRole('super-admin'))->toBeFalse()
        ->and(Staff::where('email', 'baru@kampus.test')->exists())->toBeFalse();
});

it('menolak pengaturan ulang sandi dan penonaktifan akun super-admin oleh selain super-admin', function () {
    $hash = $this->admin->password;

    $this->actingAs($this->humas, 'staff')
        ->post(route('admin.staff.reset-sandi', $this->admin))
        ->assertForbidden()
        ->assertSessionMissing('kata_sandi_baru');

    $this->actingAs($this->humas, 'staff')
        ->post(route('admin.staff.nonaktifkan', $this->admin))
        ->assertForbidden();

    expect($this->admin->fresh()->password)->toBe($hash)
        ->and($this->admin->fresh()->is_active)->toBeTrue();
});

it('menolak siapa pun mengubah perannya sendiri', function () {
    $this->actingAs($this->admin, 'staff')
        ->put(route('admin.staff.update', $this->admin), isianStaf($this->admin, 'baak'))
        ->assertForbidden();

    expect($this->admin->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('tetap mengizinkan super-admin mengangkat super-admin lain', function () {
    $this->actingAs($this->admin, 'staff')
        ->put(route('admin.staff.update', $this->humas), isianStaf($this->humas, 'super-admin'))
        ->assertRedirect();

    expect($this->humas->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('tetap mengizinkan pemegang pengaturan.manage mengelola akun biasa', function () {
    $lain = Staff::factory()->create();
    $lain->assignRole('baak');

    $this->actingAs($this->humas, 'staff')
        ->put(route('admin.staff.update', $lain), isianStaf($lain, 'keuangan'))
        ->assertRedirect();

    expect($lain->fresh()->hasRole('keuangan'))->toBeTrue();
});
