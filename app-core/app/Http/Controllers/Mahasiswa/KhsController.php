<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Nilai;
use App\Support\Portal;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Kartu Hasil Studi and the cumulative transcript.
 *
 * The KHS is not a table of its own: it is the finalised grades of a term read
 * against the enrolment record that froze that term's IPS/IPK. Keeping it
 * derived means a grade correction can never leave a stale KHS behind.
 */
class KhsController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', Nilai::class);

        $mahasiswa = Portal::user();

        $riwayat = $mahasiswa->statusPerSemester()
            ->with('tahunAkademik')
            ->orderBy('tahun_akademik_id')
            ->get();

        return view('mahasiswa.khs', [
            'judul' => 'KHS & Transkrip',
            'konteks' => $mahasiswa->nim.' · '.$mahasiswa->prodi->namaLengkap(),
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), 'KHS & Transkrip'],
            'mahasiswa' => $mahasiswa,
            'riwayat' => $riwayat,
            'nilaiPerTerm' => $this->nilaiPerTerm($mahasiswa->id),
            'terakhir' => $riwayat->where('is_final', true)->last(),
        ]);
    }

    /**
     * Finalised grades grouped by term id.
     *
     * @return Collection<int, Collection<int, Nilai>>
     */
    private function nilaiPerTerm(int $mahasiswaId): Collection
    {
        return Nilai::query()
            ->with(['kelasKuliah.mataKuliah', 'krsDetail.krs'])
            ->where('mahasiswa_id', $mahasiswaId)
            ->final()
            ->get()
            ->groupBy(fn (Nilai $nilai): int => $nilai->krsDetail->krs->tahun_akademik_id);
    }
}
