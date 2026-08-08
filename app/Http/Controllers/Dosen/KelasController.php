<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Services\Akademik\PresensiService;
use App\Support\Portal;
use Illuminate\View\View;

/**
 * The lecturer's classes for the active term — the entry point into grading
 * and attendance, and the one screen that answers "what still needs doing".
 */
class KelasController extends Controller
{
    public function __construct(private readonly PresensiService $presensi) {}

    public function __invoke(): View
    {
        $dosen = Portal::user();
        $term = Portal::term();

        $this->authorize('viewAny', KelasKuliah::class);

        $diampu = KelasKuliah::query()
            ->with(['mataKuliah', 'jadwal.ruang', 'pertemuan', 'dosen'])
            ->withCount('krsDetail as jumlah_peserta')
            ->where('tahun_akademik_id', $term->id)
            ->whereHas('dosen', fn ($query) => $query->where('dosen.id', $dosen->id))
            ->get();

        // Asked once for every class at once. Asking per class made this screen
        // take eighteen seconds on a campus of five thousand students.
        $rawan = $this->presensi->rawanAbsensi($diampu);

        $kelas = $diampu
            ->map(fn (KelasKuliah $item): array => [
                'kelas' => $item,
                'terlaksana' => $item->pertemuan->where('is_terlaksana', true)->count(),
                'total_pertemuan' => $item->pertemuan->count(),

                // The number a lecturer needs before the exam period, not
                // after: how many students are about to be disqualified.
                'rawan_absensi' => $rawan[$item->id] ?? 0,
            ])
            ->sortBy(fn (array $baris): string => $baris['kelas']->mataKuliah->nama)
            ->values();

        return view('dosen.kelas', [
            'judul' => 'Kelas Diampu',
            'konteks' => $term->nama.' · '.$kelas->count().' kelas',
            'breadcrumb' => ['Portal Dosen' => route('dosen.dashboard'), 'Kelas Diampu'],
            'daftar' => $kelas,
            'term' => $term,
        ]);
    }
}
