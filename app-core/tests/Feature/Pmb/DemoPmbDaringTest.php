<?php

declare(strict_types=1);

use App\Models\Pmb\PmbAkun;
use App\Models\Pmb\PmbGelombang;
use Database\Seeders\DemoCampusSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * The demo campus ships an online wave that is open today, with one applicant
 * account at every stage, so the whole admission flow can be shown from a
 * fresh install.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoCampusSeeder::class);
});

describe('demo', function () {
    it('memuat satu gelombang daring terbuka dengan pendaftar di berbagai status', function () {
        $g = PmbGelombang::query()->terbukaDaring()->with('pendaftar')->firstOrFail();
        $status = $g->pendaftar->pluck('status')->map->value->unique()->sort()->values()->all();

        expect($g->pendaftar->whereNotNull('pmb_akun_id'))->toHaveCount(6)
            ->and($status)->toEqualCanonicalizing(['draf', 'lulus', 'mendaftar', 'seleksi', 'tidak_lulus', 'verifikasi']);

        $calon = PmbAkun::firstWhere('email', 'calon.seleksi@demo.test');
        $this->post('/pmb/masuk', ['email' => $calon->email, 'password' => 'password'])->assertRedirect(route('pmb.portal.index'));
        $this->get('/pmb/portal')->assertOk();
        $this->get('/pmb/portal/'.$calon->pendaftaran()->first()->uuid)->assertOk();
        $this->get('/pmb/portal/'.$calon->pendaftaran()->first()->uuid.'/kartu-ujian')->assertOk();
    });
});
