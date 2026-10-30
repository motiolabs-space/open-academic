<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KoreksiNilaiRequest;
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
                ->with([
                    'mahasiswa.prodi', 'kelasKuliah.mataKuliah', 'kelasKuliah.tahunAkademik',
                    // Component scores, so a correction can go through them.
                    'kelasKuliah.komponenNilai', 'krsDetail.nilaiKomponen',
                ])
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

    public function koreksi(KoreksiNilaiRequest $request, Nilai $nilai): RedirectResponse
    {
        $this->izin('nilai.manage');

        $data = $request->koreksi();
        $sebelum = $nilai->nilai_huruf?->value;

        if ($data->lewatKomponen()) {
            $hasil = $this->penilaian->koreksiKomponen($nilai, $data->komponen, $data->alasan, Portal::user());

            return back()->with('sukses', __('capaian.koreksi.sukses_komponen', [
                'nama' => $nilai->mahasiswa->nama,
                'mk' => $nilai->kelasKuliah->mataKuliah->nama,
                'huruf' => $hasil->nilai_huruf?->value ?? '—',
            ]));
        }

        $this->penilaian->koreksi(
            $nilai,
            (float) $data->nilaiAngka,
            $data->alasan,
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
