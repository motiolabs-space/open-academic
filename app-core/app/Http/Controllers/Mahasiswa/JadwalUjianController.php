<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Akademik\JadwalUjian;
use App\Services\Akademik\JadwalUjianService;
use App\Support\Portal;
use Illuminate\View\View;

/**
 * The student's own exam sessions, with the room they sit in.
 *
 * Takes no parameter: the owner is whoever is signed in, the same rule as the
 * exam card route.
 */
class JadwalUjianController extends Controller
{
    public function __construct(private readonly JadwalUjianService $ujian) {}

    public function __invoke(): View
    {
        $this->authorize('viewAny', JadwalUjian::class);

        $mahasiswa = Portal::user();
        $term = Portal::term();

        $sesi = $this->ujian->untukMahasiswa($mahasiswa, $term);

        return view('mahasiswa.jadwal-ujian', [
            'judul' => __('jadwal_ujian.judul'),
            'konteks' => __('jadwal_ujian.konteks_portal', ['term' => $term->nama, 'jumlah' => $sesi->count()]),
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), __('jadwal_ujian.judul')],
            'perJenis' => $sesi->groupBy(fn (JadwalUjian $j): string => $j->jenis->value),
            'ruang' => $this->ujian->ruangUntukBanyak($sesi, $mahasiswa),
        ]);
    }
}
