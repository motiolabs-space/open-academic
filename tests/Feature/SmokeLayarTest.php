<?php

declare(strict_types=1);

use App\Models\Bridge\BridgeConsumer;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\TugasAkhir;
use App\Support\Portal;
use Database\Seeders\DemoCampusSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Walks every screen and every Bridge endpoint against the full demo campus.
 *
 * Two defects are being hunted, and they need two different instruments:
 *
 *  1. Lazy-loaded relations — caught by `Model::preventLazyLoading()`, which is
 *     armed outside production. Note that Laravel only arms it on queries that
 *     hydrate more than one model (Builder::hydrate), on the reasoning that a
 *     single record cannot cause an N+1.
 *
 *  2. A fresh query builder per row — invisible to the guard above, because
 *     nothing is being lazily *loaded*; the controller simply asks the database
 *     again inside a loop. Only a query count catches this.
 *
 * The ceilings below are deliberately close to the measured counts. A budget
 * with slack in it is a budget that never fails, and a regression that lands
 * inside the slack is a regression nobody sees.
 *
 * Every authenticated screen carries one query these ceilings did not used to
 * include: the unread-notification count behind the topbar bell. It is a single
 * COUNT over the (notifiable_type, notifiable_id, read_at) index and it runs on
 * every page of every portal — a real cost, accepted deliberately rather than
 * hidden by widening the budgets. The KRS screen is the one that had no slack
 * left, which is exactly what these numbers are for.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoCampusSeeder::class);

    Portal::lupakanTerm();

    // The seeder writes fixtures directly and relaxes strictness while it does
    // so; the walk itself must run under the strict rules.
    Model::preventLazyLoading();
});

/**
 * Runs a request with the query log on and fails if it overruns the budget.
 */
function dalamAnggaranKueri(Closure $panggil, int $anggaran): void
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $panggil();
    } finally {
        $kueri = DB::getRawQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect(count($kueri))->toBeLessThanOrEqual(
        $anggaran,
        sprintf(
            "Anggaran kueri terlampaui: %d > %d.\nKueri terakhir:\n%s",
            count($kueri),
            $anggaran,
            collect($kueri)->take(-12)->pluck('raw_query')->implode("\n"),
        ),
    );
}

it('membuka layar mahasiswa dalam anggaran kueri', function (string $url, int $anggaran) {
    $mahasiswa = Mahasiswa::query()->whereHas('krs')->orderBy('id')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($mahasiswa, 'mahasiswa')->get($url)->assertOk(),
        $anggaran,
    );
})->with([
    ['/mahasiswa', 25],
    ['/mahasiswa/krs', 34],
    ['/mahasiswa/jadwal', 20],
    ['/mahasiswa/khs', 25],
    ['/mahasiswa/tagihan', 20],
    ['/mahasiswa/tugas-akhir', 25],
    ['/notifikasi', 25],
    ['/notifikasi/preferensi', 25],
    ['/mahasiswa/surat', 30],
]);

it('membuka layar dosen dalam anggaran kueri', function (string $url, int $anggaran) {
    $dosen = Dosen::where('email', 'dosen1@demo.test')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($dosen, 'dosen')->get($url)->assertOk(),
        $anggaran,
    );
})->with([
    ['/dosen', 25],
    ['/dosen/kelas', 25],
    ['/dosen/nilai', 25],
    ['/dosen/presensi', 25],
    ['/dosen/persetujuan-krs', 25],
    ['/dosen/bimbingan', 25],
    ['/dosen/tugas-akhir', 25],
    ['/notifikasi', 25],
]);

it('membuka layar admin dalam anggaran kueri', function (string $url, int $anggaran) {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get($url)->assertOk(),
        $anggaran,
    );
})->with([
    ['/admin', 30],
    ['/admin/mahasiswa', 25],
    ['/admin/yudisium', 30],
    ['/admin/tugas-akhir', 25],
    ['/admin/data-iku', 25],
    ['/admin/feeder', 30],
    ['/admin/bridge', 25],
    ['/admin/surat', 25],
    ['/notifikasi', 25],
]);

it('melayani endpoint Bridge dalam anggaran kueri', function (string $url, int $anggaran) {
    $consumer = BridgeConsumer::query()->orderBy('id')->firstOrFail();
    $token = $consumer->createToken('smoke', $consumer->scopes)->plainTextToken;

    dalamAnggaranKueri(
        fn () => $this->getJson($url, ['Authorization' => 'Bearer '.$token])->assertOk(),
        $anggaran,
    );
})->with([
    ['/api/bridge/v1/students', 20],
    ['/api/bridge/v1/students/statistics', 20],
    ['/api/bridge/v1/lecturers', 20],
    ['/api/bridge/v1/classes', 20],
    ['/api/bridge/v1/student-activities', 20],
    ['/api/bridge/v1/graduates', 20],
    ['/api/bridge/v1/academic-terms', 20],
    ['/api/bridge/v1/academic-terms/current', 20],
    ['/api/bridge/v1/iku-data', 35],
]);

it('membuka halaman publik tanpa menyentuh basis data berlebihan', function (string $url, int $anggaran) {
    dalamAnggaranKueri(fn () => $this->get($url)->assertOk(), $anggaran);
})->with([
    ['/', 5],
    ['/masuk', 5],

    // Halaman publik yang paling mungkin dijangkau bot: satu kueri pun terasa
    // bila seseorang memindai rentang UUID.
    ['/verifikasi', 5],
]);

it('membuka layar kelola tugas akhir dalam anggaran kueri', function () {
    // Layar terberat modul ini: satu karya beserta pembimbing, seluruh log
    // bimbingan, tiap ujian dengan panelnya, dan beban bimbingan setiap dosen
    // aktif agar yang mengalokasikan tahu siapa yang sudah penuh. Semuanya
    // mudah berubah menjadi satu kueri per baris.
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $ta = TugasAkhir::query()->orderBy('id')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get('/admin/tugas-akhir/'.$ta->uuid)->assertOk(),
        30,
    );
});

it('menyusun transkrip PDF dalam anggaran kueri', function () {
    $mahasiswa = Mahasiswa::query()
        ->whereHas('nilai', fn ($q) => $q->where('is_final', true))
        ->orderBy('id')
        ->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($mahasiswa, 'mahasiswa')->get('/mahasiswa/khs/transkrip')->assertOk(),
        25,
    );
});
