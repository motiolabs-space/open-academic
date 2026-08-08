<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\System\LogAktivitas;
use App\Support\Portal;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The audit trail, finally readable.
 *
 * The trail has been written since the first release; without a screen it was
 * only reachable through the database, which means in practice it was never
 * read — and an audit trail nobody reads is a log file, not a control.
 *
 * Read-only by construction: there is no route that edits or deletes a row
 * here, because a trail that can be corrected is not evidence of anything.
 */
class LogAktivitasController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(Portal::user()?->hasPermissionTo('log.view', 'staff') ?? false, 403);

        $daftar = LogAktivitas::query()
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->when($request->filled('subjek'), fn ($q) => $q->where('subject_type', $request->string('subjek')))
            ->cari($request->string('cari'), ['description', 'causer_name', 'subject_label'])
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('sampai')))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.log-aktivitas', [
            'judul' => 'Log Aktivitas',
            'konteks' => number_format($daftar->total()).' catatan',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Log Aktivitas'],
            'daftar' => $daftar,
            'filter' => $request->only(['event', 'subjek', 'cari', 'dari', 'sampai']),

            // Built from what is actually in the trail rather than a hard-coded
            // list, so a new audited event appears in the filter by itself.
            'daftarEvent' => LogAktivitas::query()->distinct()->orderBy('event')->pluck('event', 'event'),
            'daftarSubjek' => LogAktivitas::query()
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type', 'subject_type')
                ->mapWithKeys(fn ($kelas) => [$kelas => class_basename($kelas)]),
        ]);
    }
}
