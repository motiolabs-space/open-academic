<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\FeederSyncStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SyncFeederEntityJob;
use App\Models\Feeder\FeederDiff;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Services\Feeder\FeederRekonsiliasi;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The PDDIKTI reporting console.
 *
 * Answers the three questions an operator has before a reporting deadline:
 * what is still unsent, what would be rejected if I sent it now, and what
 * failed last time. The sync itself is queued — a campus-wide run takes
 * minutes, and a browser tab is no place to hold one open.
 */
class FeederController extends Controller
{
    public function __construct(
        private readonly FeederSyncService $sync,
        private readonly FeederValidator $validator,
        private readonly FeederRekonsiliasi $rekonsiliasi,
    ) {}

    public function index(): View
    {
        $this->authorizeFeeder('feeder.view');

        $term = Portal::term();

        return view('admin.feeder', [
            'judul' => 'Neo Feeder PDDIKTI',
            'konteks' => $term->nama.' · mode '.config('feeder.driver'),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Neo Feeder PDDIKTI'],
            'term' => $term,
            'aktif' => (bool) config('feeder.enabled'),
            'sehat' => config('feeder.enabled') && $this->sync->sehat(),
            'entitas' => $this->ringkasEntitas($term->id),
            'ledger' => FeederSyncLog::query()
                ->with('tahunAkademik')
                ->where('tahun_akademik_id', $term->id)
                ->latest('id')
                ->limit(40)
                ->get(),
            'validasiTerakhir' => $this->validasiTerakhir(),
            'selisih' => $this->selisihTerakhir($term->kode),
        ]);
    }

    /**
     * Reads PDDIKTI back and reports where it disagrees with this application.
     *
     * Runs in the request rather than on the queue, unlike the sync. A sync
     * makes one HTTP call per row; a comparison makes one per page of five
     * hundred, and the rest is a local query. The wait is seconds, and an
     * operator who has to come back later to read the answer will not run it.
     */
    public function bandingkan(string $entity): RedirectResponse
    {
        $this->authorizeFeeder('feeder.sync');

        if (!isset(FeederSyncService::MAPPERS[$entity])) {
            abort(404);
        }

        $hasil = $this->rekonsiliasi->bandingkan($entity, Portal::term());

        $selisih = $hasil['hanya_lokal'] + $hasil['hanya_feeder'] + $hasil['berbeda'] + $hasil['tanpa_kunci'];

        return back()->with(
            $selisih === 0 ? 'sukses' : 'peringatan',
            $selisih === 0
                ? sprintf('%s: %d baris diperiksa, seluruhnya cocok dengan Feeder.', $entity, $hasil['cocok'])
                : sprintf(
                    '%s: %d selisih — %d hanya di SIAKAD, %d hanya di Feeder, %d berbeda isinya, %d tanpa kunci.',
                    $entity,
                    $selisih,
                    $hasil['hanya_lokal'],
                    $hasil['hanya_feeder'],
                    $hasil['berbeda'],
                    $hasil['tanpa_kunci'],
                ),
        );
    }

    /** Runs the pre-flight check for every entity without sending anything. */
    public function validasi(): RedirectResponse
    {
        $this->authorizeFeeder('feeder.view');

        $term = Portal::term();
        $totalError = 0;

        foreach (FeederSyncService::MAPPERS as $entity => $kelas) {
            $hasil = $this->validator->periksa($entity, $term->kode, app($kelas));
            $totalError += $hasil['error'];
        }

        return back()->with(
            $totalError === 0 ? 'sukses' : 'peringatan',
            $totalError === 0
                ? 'Seluruh baris lolos aturan PDDIKTI. Sinkronisasi dapat dijalankan.'
                : "{$totalError} baris akan ditolak Feeder. Perbaiki sebelum sinkronisasi.",
        );
    }

    public function jalankan(string $entity): RedirectResponse
    {
        $this->authorizeFeeder('feeder.sync');

        if (!isset(FeederSyncService::MAPPERS[$entity])) {
            abort(404);
        }

        SyncFeederEntityJob::dispatch($entity, Portal::term()->id);

        return back()->with('sukses', "Sinkronisasi {$entity} dijadwalkan. Pantau perkembangannya pada buku besar di bawah.");
    }

    public function ulangi(string $entity): RedirectResponse
    {
        $this->authorizeFeeder('feeder.sync');

        $hasil = $this->sync->ulangiYangGagal($entity, Portal::term());

        return back()->with('sukses', sprintf(
            '%d baris diulang: %d berhasil, %d masih gagal.',
            $hasil['diulang'],
            $hasil['berhasil'],
            $hasil['gagal'],
        ));
    }

    public function tarikReferensi(): RedirectResponse
    {
        $this->authorizeFeeder('feeder.sync');

        $hasil = $this->sync->tarikReferensi();

        return back()->with('sukses', 'Referensi ditarik: '.collect($hasil)
            ->map(fn (int $jumlah, string $tipe): string => "{$tipe} ({$jumlah})")
            ->implode(', '));
    }

    /**
     * Per-entity state: how many rows are due, and how the last run went.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function ringkasEntitas(int $termId): Collection
    {
        $term = Portal::term();

        // One grouped aggregate for the whole ledger. Counting per entity per
        // status separately meant four round trips times six entities for a
        // panel of six numbers, and it grew every time an entity was added.
        $rekap = FeederSyncLog::query()
            ->where('tahun_akademik_id', $termId)
            ->groupBy('entity', 'status')
            ->selectRaw('entity, status, COUNT(*) as jumlah, MAX(created_at) as terakhir')
            ->get()
            ->groupBy('entity');

        return collect(config('feeder.entities'))->map(function (array $meta, string $entity) use ($term, $rekap): array {
            $baris = $rekap->get($entity) ?? collect();
            $jumlah = fn (FeederSyncStatus $status): int => (int) ($baris
                ->firstWhere('status', $status->value)?->jumlah ?? 0);

            return [
                'entity' => $entity,
                'label' => $meta['label'],
                'action' => $meta['action'],
                'depends_on' => $meta['depends_on'],

                // Stays per entity: each mapper reads a different table, so
                // this is six distinct questions, not the same one repeated.
                'antre' => app(FeederSyncService::MAPPERS[$entity])->rows($term->kode)->count(),

                'berhasil' => $jumlah(FeederSyncStatus::Success),
                'dilewati' => $jumlah(FeederSyncStatus::Skipped),
                'gagal' => $jumlah(FeederSyncStatus::Failed),
                'terakhir' => $baris->max('terakhir'),

                // Shown on the row itself, so an entity without a comparison
                // reads as "belum dapat dibandingkan" rather than as one that
                // was checked and found clean.
                'dapat_dibandingkan' => $this->rekonsiliasi->dapatDibandingkan($entity),
            ];
        })->values();
    }

    /** @return Collection<int, FeederValidationIssue> */
    private function validasiTerakhir(): Collection
    {
        $batch = FeederValidationIssue::query()->latest('id')->value('batch_id');

        if ($batch === null) {
            return collect();
        }

        // Issues from the same run, worst first.
        return FeederValidationIssue::query()
            ->where('created_at', '>=', FeederValidationIssue::batch($batch)->value('created_at'))
            ->orderByRaw("CASE severity WHEN 'error' THEN 0 ELSE 1 END")
            ->limit(40)
            ->get();
    }

    /**
     * Findings from the most recent comparison of each entity, for this term.
     *
     * Per entity rather than one global batch: entities are compared one at a
     * time, and showing only the newest run would hide every finding from the
     * five entities checked before it.
     *
     * @return Collection<int, FeederDiff>
     */
    private function selisihTerakhir(string $termKode): Collection
    {
        $batchTerbaru = FeederDiff::query()
            ->where('term_kode', $termKode)
            ->selectRaw('entity, MAX(id) as id_terakhir')
            ->groupBy('entity')
            ->pluck('id_terakhir');

        if ($batchTerbaru->isEmpty()) {
            return collect();
        }

        $batches = FeederDiff::query()
            ->whereIn('id', $batchTerbaru)
            ->pluck('batch_id');

        return FeederDiff::query()
            ->whereIn('batch_id', $batches)
            ->orderBy('entity')
            ->orderBy('jenis')
            ->limit(100)
            ->get();
    }

    private function authorizeFeeder(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
