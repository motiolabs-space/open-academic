<?php

declare(strict_types=1);

use App\Models\Akademik\CapaianCplMahasiswa;
use App\Models\Akademik\CapaianCpmkMahasiswa;
use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Rps;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Akademik\CapaianService;
use App\Support\Portal;
use Database\Seeders\DemoCampusSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The demo campus demonstrates the outcome hierarchy end to end: one
 * programme with CPL → CPMK → sub-CPMK complete, classes mapped, attainment
 * stored, and every screen of the module opening within a query budget.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoCampusSeeder::class);

    Portal::lupakanTerm();
    Model::preventLazyLoading();

    $this->prodi = Prodi::orderBy('id')->firstOrFail();
    $this->kurikulum = Kurikulum::where('prodi_id', $this->prodi->id)->where('is_active', true)->firstOrFail();
});

function kueriDemoObe(Closure $panggil): int
{
    auth()->forgetGuards();
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $panggil();
    } finally {
        $jumlah = count(array_filter(
            DB::getRawQueryLog(),
            fn (array $q): bool => preg_match('/sqlite_master|pragma_table_xinfo/', $q['raw_query']) !== 1,
        ));
        DB::disableQueryLog();
    }

    return $jumlah;
}

it('menyemai satu prodi dengan hierarki capaian lengkap dan capaian tersimpan', function () {
    $jumlahMk = $this->kurikulum->mataKuliah()->count();

    $berlaku = CpmkVersi::with(CpmkVersi::MUAT)->where('kurikulum_id', $this->kurikulum->id)->aktif()->get();

    expect($berlaku)->toHaveCount($jumlahMk)
        ->and($berlaku->every(fn (CpmkVersi $v): bool => $v->cpmk->count() === 3 && $v->cpmk->sum(fn ($c) => $c->sub->count()) === 4))->toBeTrue();

    expect(CapaianCpmkMahasiswa::count())->toBeGreaterThan(0)
        ->and(CapaianCplMahasiswa::count())->toBeGreaterThan(0);

    // Attainment exists only for finalised classes.
    expect(KelasKuliah::whereIn('id', CapaianCpmkMahasiswa::distinct()->pluck('kelas_kuliah_id'))->where('status_nilai', '!=', 'final')->exists())
        ->toBeFalse();

    // The second programme is left mid-adoption.
    expect(CpmkVersi::where('kurikulum_id', '!=', $this->kurikulum->id)->exists())->toBeFalse();
});

it('menyisakan satu celah cakupan dan memasang CPMK pada RPS', function () {
    $berjalan = KelasKuliah::query()
        ->where('prodi_id', $this->prodi->id)
        ->where('status_nilai', '!=', 'final')
        ->whereHas('komponenNilai.pemetaanCpmk')
        ->orderBy('id')
        ->firstOrFail();

    $cakupan = app(CapaianService::class)->cakupan($berjalan);

    expect($cakupan['tak_terukur']->pluck('kode')->all())->toBe(['Sub-CPMK-2.1']);

    $rps = Rps::query()
        ->aktif()
        ->whereIn('mata_kuliah_id', $this->kurikulum->mataKuliah()->pluck('mata_kuliah.id'))
        ->get();

    expect($rps)->not->toBeEmpty()
        ->and($rps->every(fn (Rps $r): bool => $r->cpmk_versi_id !== null))->toBeTrue();
});

it('membuka layar modul capaian dalam anggaran kueri', function () {
    $staf = Staff::where('email', 'admin@demo.test')->firstOrFail();

    $final = KelasKuliah::query()
        ->whereIn('id', CapaianCpmkMahasiswa::distinct()->pluck('kelas_kuliah_id'))
        ->whereHas('dosen')
        ->with('dosen')
        ->orderBy('id')
        ->firstOrFail();
    // Fetched alone, as a signed-in user is: a model hydrated inside a
    // collection carries the lazy-loading guard into the topbar.
    $dosenFinal = Dosen::findOrFail($final->dosen->first()->id);

    $berjalan = KelasKuliah::query()
        ->where('prodi_id', $this->prodi->id)
        ->where('tahun_akademik_id', Portal::term()->id)
        ->whereHas('komponenNilai.pemetaanCpmk')
        ->whereHas('dosen')
        ->with(['dosen', 'mataKuliah'])
        ->orderBy('id')
        ->firstOrFail();
    $dosen = Dosen::findOrFail($berjalan->dosen->first()->id);

    $mahasiswa = Mahasiswa::findOrFail(CapaianCplMahasiswa::orderBy('mahasiswa_id')->value('mahasiswa_id'));

    /*
     * Measured on the demo, one step of slack each. None of these grows per
     * row: the recaps read stored rows in one query per table, the editor
     * loads a version's CPMK, sub-CPMK and CPL in three, and the evaluation
     * plan's coverage adds the mapping as one eager load.
     */
    $layar = [
        [$staf, 'staff', '/admin/cpmk?prodi='.$this->prodi->uuid, 6],
        [$staf, 'staff', '/admin/cpmk/'.$this->kurikulum->uuid.'/'.$berjalan->mataKuliah->uuid, 10],
        [$staf, 'staff', '/admin/capaian?prodi='.$this->prodi->uuid, 7],
        [$dosenFinal, 'dosen', '/dosen/capaian/'.$final->uuid, 11],
        [$dosen, 'dosen', '/dosen/capaian', 4],
        [$dosen, 'dosen', '/dosen/rps/cpmk/'.$berjalan->mataKuliah->uuid, 11],
        [$dosen, 'dosen', '/dosen/nilai/'.$berjalan->uuid, 24],
        [$dosen, 'dosen', '/dosen/rps/susun/'.$berjalan->mataKuliah->uuid, 17],
        [$mahasiswa, 'mahasiswa', '/mahasiswa/capaian', 11],
    ];

    $gagal = [];

    foreach ($layar as [$aktor, $guard, $url, $anggaran]) {
        $buka = fn () => kueriDemoObe(fn () => $this->actingAs($aktor, $guard)->get($url)->assertOk());
        $buka();

        if (($n = $buka()) > $anggaran) {
            $gagal[] = "{$url}: {$n} > {$anggaran}";
        }
    }

    expect($gagal)->toBe([]);
});
