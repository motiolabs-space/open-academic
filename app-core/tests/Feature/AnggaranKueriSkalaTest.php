<?php

declare(strict_types=1);

use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Pmb\PmbGelombang;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Support\Portal;
use Database\Seeders\DemoCampusSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Support\PenggandaBaris;

/**
 * Query budgets for the busiest screens, measured on a campus grown past the
 * demo — the N+1 audit of October 2026 (docs/KAPASITAS.md).
 *
 * SmokeLayarTest walks every screen on the fifty-student demo, and that is
 * exactly why it could not see the N+1s this audit found: on the demo, the
 * semester-closing preview ran 61 queries and stayed inside a budget written
 * for it. On three times the students it ran 212. A ceiling measured on small
 * data cannot tell "twenty queries" from "twenty queries because there are
 * twenty rows".
 *
 * So the campus here is grown before anything is counted: every class cloned
 * once (a lecturer's classes and a student's plan double), every student
 * cloned twice (rosters, advisee lists, and approval queues triple), and the
 * other queues — applicants, tickets, calendar events, exam sessions, activity
 * records — multiplied alongside. PenggandaBaris walks the foreign keys so the
 * copies carry their grades, attendance, and bills with them. A per-row query
 * on any screen below now costs dozens of queries, far past its ceiling.
 *
 * Each screen is also counted once on the ungrown demo, and the grown count
 * must not exceed it by more than a step: a list that was empty on the demo
 * and is not any more adds its one eager load, which is not growth. Anything
 * beyond that step is a query per row.
 */
const LANGKAH_EAGER_LOAD = 4;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoCampusSeeder::class);

    Portal::lupakanTerm();
    Model::preventLazyLoading();
});

/** Grows the demo campus; see the file docblock for what each line multiplies. */
function tumbuhkanKampus(): void
{
    $ketat = Model::preventsLazyLoading();
    Model::preventLazyLoading(false);

    foreach ([
        'kelas_kuliah' => 1,
        'mahasiswa' => 2,
        'pmb_pendaftar' => 2,
        'helpdesk_tiket' => 3,
        'kalender_peristiwa' => 3,
        'jadwal_ujian' => 1,
        'penugasan_dosen' => 2,
        'aktivitas_mahasiswa' => 2,
        'tagihan_item' => 2,
        'pembayaran' => 2,
    ] as $tabel => $kali) {
        PenggandaBaris::gandakan($tabel, $kali);
    }

    Model::preventLazyLoading($ketat);
    Portal::lupakanTerm();
}

function jumlahKueri(Closure $panggil): int
{
    auth()->forgetGuards();
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $panggil();
    } finally {
        $jumlah = count(DB::getRawQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    return $jumlah;
}

/**
 * Counts every screen on the demo, grows the campus, counts again, and holds
 * the grown count to both the budget and the demo count.
 *
 * @param array<string, int> $layar url => budget on the grown campus
 */
function periksaAnggaranSkala($test, Authenticatable $aktor, string $guard, array $layar, ?Closure $url = null): void
{
    $url ??= fn (string $u): string => $u;
    $buka = fn (string $u) => jumlahKueri(fn () => $test->actingAs($aktor, $guard)->get($url($u))->assertOk());

    // The first request of a screen may initialise rows (sixteen meetings on
    // first opening the attendance grid); that is not the steady state.
    $awal = [];
    foreach (array_keys($layar) as $u) {
        $buka($u);
        $awal[$u] = $buka($u);
    }

    tumbuhkanKampus();

    $gagal = [];
    foreach ($layar as $u => $anggaran) {
        $buka($u);
        $tumbuh = $buka($u);

        if ($tumbuh > $anggaran || $tumbuh > $awal[$u] + LANGKAH_EAGER_LOAD) {
            $gagal[] = sprintf('%s: %d kueri pada demo, %d sesudah ditumbuhkan (anggaran %d)', $u, $awal[$u], $tumbuh, $anggaran);
        }
    }

    expect($gagal)->toBe([], "Layar tumbuh bersama datanya:\n".implode("\n", $gagal));
}

it('menjaga layar mahasiswa tetap datar saat kampus tumbuh', function () {
    $mahasiswa = Mahasiswa::query()->whereHas('krs')->orderBy('id')->firstOrFail();

    periksaAnggaranSkala($this, $mahasiswa, 'mahasiswa', [
        '/mahasiswa' => 15,
        // Prerequisites come from the eager-loaded relation, not one query
        // per catalogue row.
        '/mahasiswa/krs' => 20,
        '/mahasiswa/jadwal' => 8,
        '/mahasiswa/jadwal-ujian' => 9,
        '/mahasiswa/kalender' => 19,
        '/mahasiswa/khs' => 10,
        '/mahasiswa/khs/transkrip' => 9,
        '/mahasiswa/tagihan' => 9,
        // Attendance for every class on the card from two queries, not two
        // per class.
        '/mahasiswa/kartu-ujian' => 20,
        '/mahasiswa/edom' => 11,
        '/mahasiswa/helpdesk' => 10,
    ]);
});

it('menjaga layar dosen tetap datar saat kampus tumbuh', function () {
    $dosen = Dosen::where('email', 'dosen1@demo.test')->firstOrFail();

    $kelas = KelasKuliah::query()
        ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosen->id))
        ->whereHas('komponenNilai')
        ->orderBy('id')
        ->firstOrFail();

    periksaAnggaranSkala($this, $dosen, 'dosen', [
        '/dosen' => 12,
        '/dosen/kelas' => 12,
        '/dosen/persetujuan-krs' => 12,
        // This term's plan for every advisee in one query.
        '/dosen/bimbingan' => 7,
        '/dosen/nilai' => 6,
        // Which components already hold scores: one EXISTS subquery.
        '/dosen/nilai/{kelas}' => 16,
        '/dosen/presensi' => 6,
        '/dosen/presensi/{kelas}' => 12,
        // Attendance from the class recap instead of per participant.
        '/dosen/analitik/{kelas}' => 21,
        '/dosen/jadwal-ujian' => 11,
        '/dosen/kalender' => 22,
    ], fn (string $u): string => str_replace('{kelas}', $kelas->uuid, $u));
});

it('menjaga layar staf tetap datar saat kampus tumbuh', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $gelombang = PmbGelombang::query()->terbukaDaring()->firstOrFail();
    $krs = Mahasiswa::query()->whereHas('krs')->orderBy('id')->firstOrFail()
        ->krs()->where('tahun_akademik_id', TahunAkademik::aktif()->id)->firstOrFail();

    periksaAnggaranSkala($this, $staff, 'staff', [
        '/admin' => 19,
        '/admin/mahasiswa' => 8,
        '/admin/kelas' => 18,
        // Pending classes for the whole term in one query, grouped per
        // student — it used to be one query per student record.
        '/admin/tutup-semester' => 12,
        '/admin/jadwal-ujian' => 22,
        '/admin/kalender' => 13,
        '/admin/pmb' => 15,
        '/admin/pmb/gelombang/{gelombang}' => 12,
        '/admin/helpdesk' => 13,
        '/admin/pemanfaatan-ruang' => 11,
        '/admin/data-iku' => 12,
        '/admin/keuangan' => 16,
        // +1 fixed (ADR-041 §3): the PISN checklist loads every listed
        // programme's accreditation history once, to judge it on the
        // graduation date. Does not grow with graduates or programmes.
        '/admin/yudisium' => 16,
        // One activities query for every lecturer in the export.
        '/admin/bkd/ekspor/kegiatan' => 3,
        '/admin/cetak/kartu-ujian/{krs}' => 20,
        '/admin/log' => 6,
    ], fn (string $u): string => str_replace(['{gelombang}', '{krs}'], [$gelombang->uuid, $krs->uuid], $u));
});
