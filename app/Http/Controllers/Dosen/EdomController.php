<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Edom\EdomPeriode;
use App\Models\Sdm\Dosen;
use App\Services\Edom\HasilEdom;
use App\Support\Portal;
use Illuminate\View\View;

/**
 * A lecturer reading their own results, and nobody else's.
 *
 * Scoped to the signed-in lecturer with no identifier in the URL, so there is no
 * shape of request that returns a colleague's scores.
 *
 * Comments follow config('edom.komentar'): by default they go to the programme
 * rather than to the person being described, because a sentence in a small class
 * identifies its author through its content whatever the database stores.
 */
class EdomController extends Controller
{
    public function __construct(private readonly HasilEdom $hasil) {}

    public function __invoke(): View
    {
        $dosen = Portal::user();

        abort_unless($dosen instanceof Dosen, 403);

        $periode = EdomPeriode::query()
            ->with('tahunAkademik')
            ->orderByDesc('id')
            ->first();

        $bolehKomentar = config('edom.komentar') === 'dosen';

        $diampu = $periode === null ? collect() : KelasKuliah::query()
            ->with('mataKuliah')
            ->where('tahun_akademik_id', $periode->tahun_akademik_id)
            ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosen->id))
            ->get();

        // Resolved in one go: one query for the counts and one for the answers,
        // however many classes this lecturer teaches.
        $hasil = $periode === null
            ? []
            : $this->hasil->beberapaKelas($periode, $dosen, $diampu->pluck('id')->all(), $bolehKomentar);

        $kelas = $diampu->map(fn (KelasKuliah $k): array => [
            'kelas' => $k,
            'hasil' => $hasil[$k->id],
        ]);

        return view('dosen.edom', [
            'judul' => 'Hasil Evaluasi Dosen',
            'konteks' => $periode?->tahunAkademik->nama ?? 'Belum ada periode',
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), 'Hasil EDOM'],
            'periode' => $periode,
            'daftar' => $kelas,
            'bolehKomentar' => $bolehKomentar,
            'kebijakanKomentar' => config('edom.komentar'),
        ]);
    }
}
