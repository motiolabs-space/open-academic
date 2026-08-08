<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Nilai;
use App\Services\Akademik\PenilaianService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Correcting a grade after it has been finalised.
 *
 * The service has enforced the rules since Phase 1 — reason mandatory, audited,
 * staff only — but there was no screen, which meant in practice corrections
 * happened in the database. A rule that can only be followed through tinker is
 * a rule that gets bypassed.
 *
 * Search-first rather than a browsable list: a correction starts with somebody
 * naming a specific student and course, never with browsing grades.
 */
class KoreksiNilaiController extends Controller
{
    public function __construct(private readonly PenilaianService $penilaian) {}

    public function index(Request $request): View
    {
        $this->izin('nilai.manage');

        $hasil = collect();

        if ($request->filled('cari')) {
            $cari = '%'.$request->string('cari').'%';

            $hasil = Nilai::query()
                ->with(['mahasiswa.prodi', 'kelasKuliah.mataKuliah', 'kelasKuliah.tahunAkademik', 'krsDetail'])
                ->where('is_final', true)
                ->cari($request->string('cari'), ['mahasiswa.nama', 'mahasiswa.nim'])
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        return view('admin.koreksi-nilai', [
            'judul' => 'Koreksi Nilai',
            'konteks' => 'Perubahan nilai final selalu tercatat',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Koreksi Nilai'],
            'hasil' => $hasil,
            'filter' => $request->only(['cari']),
        ]);
    }

    public function koreksi(Request $request, Nilai $nilai): RedirectResponse
    {
        $this->izin('nilai.manage');

        $validated = $request->validate([
            'nilai_angka' => ['required', 'numeric', 'min:0', 'max:100'],
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'alasan.required' => 'Koreksi nilai final wajib disertai alasan — inilah satu-satunya '
                .'keterangan yang tersisa bila suatu saat perubahan ini dipertanyakan.',
            'alasan.min' => 'Alasan terlalu singkat untuk berguna enam bulan dari sekarang.',
        ]);

        $sebelum = $nilai->nilai_huruf?->value;

        $this->penilaian->koreksi(
            $nilai,
            (float) $validated['nilai_angka'],
            $validated['alasan'],
            Portal::user(),
        );

        return back()->with('sukses', sprintf(
            'Nilai %s pada %s diubah dari %s menjadi %s, dan tercatat pada jejak audit.',
            $nilai->mahasiswa->nama,
            $nilai->kelasKuliah->mataKuliah->nama,
            $sebelum ?? '—',
            $nilai->fresh()->nilai_huruf?->value,
        ));
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
