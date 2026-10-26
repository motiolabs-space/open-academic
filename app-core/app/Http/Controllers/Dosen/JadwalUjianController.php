<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Akademik\JadwalUjian;
use App\Services\Akademik\JadwalUjianService;
use App\Support\Portal;
use Illuminate\View\View;

/**
 * Exam sessions a lecturer is expected at: as the class's lecturer, as an
 * invigilator, or both.
 */
class JadwalUjianController extends Controller
{
    public function __construct(private readonly JadwalUjianService $ujian) {}

    public function __invoke(): View
    {
        $this->authorize('viewAny', JadwalUjian::class);

        $dosen = Portal::user();
        $term = Portal::term();

        $sesi = $this->ujian->untukDosen($dosen, $term);

        $mengawasi = $sesi->filter(fn (JadwalUjian $j): bool => $j->pengawas->contains('dosen_id', $dosen->id))->values();

        return view('dosen.jadwal-ujian', [
            'judul' => __('jadwal_ujian.judul'),
            'konteks' => __('jadwal_ujian.konteks_portal', ['term' => $term->nama, 'jumlah' => $sesi->count()]),
            'breadcrumb' => ['Portal Dosen' => route('dosen.dashboard'), __('jadwal_ujian.judul')],
            'dosen' => $dosen,
            'mengawasi' => $mengawasi,
            'mengampu' => $sesi->filter(fn (JadwalUjian $j): bool => $j->kelasKuliah->dosen->contains('id', $dosen->id))->values(),
        ]);
    }
}
