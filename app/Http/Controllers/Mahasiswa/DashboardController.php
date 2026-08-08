<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\Krs;
use App\Models\Keuangan\Tagihan;
use App\Models\System\Pengumuman;
use App\Support\Portal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $mahasiswa = Portal::user();
        $term = Portal::term();

        $statusTerm = $mahasiswa->statusPada($term);
        $statusSebelumnya = $mahasiswa->statusPerSemester()
            ->where('is_final', true)
            ->orderByDesc('tahun_akademik_id')
            ->first();

        return view('mahasiswa.dashboard', [
            'judul' => 'Selamat datang, '.Str::before($mahasiswa->nama, ' '),
            'konteks' => $mahasiswa->nim.' · '.$mahasiswa->prodi->namaLengkap(),
            'mahasiswa' => $mahasiswa,
            'term' => $term,
            'statusTerm' => $statusTerm,
            'statusSebelumnya' => $statusSebelumnya,
            'krs' => $this->krs($mahasiswa->id, $term?->id),
            'tagihan' => $this->tagihan($mahasiswa->id, $term?->id),
            'jadwalHariIni' => $this->jadwalHariIni($mahasiswa->id, $term?->id),
            'pengumuman' => Pengumuman::terbit()->untuk('mahasiswa')
                ->orderByDesc('is_pinned')->orderByDesc('published_at')->limit(4)->get(),
        ]);
    }

    private function krs(int $mahasiswaId, ?int $termId): ?Krs
    {
        if ($termId === null) {
            return null;
        }

        return Krs::where('mahasiswa_id', $mahasiswaId)
            ->where('tahun_akademik_id', $termId)
            ->first();
    }

    private function tagihan(int $mahasiswaId, ?int $termId): ?Tagihan
    {
        if ($termId === null) {
            return null;
        }

        return Tagihan::where('mahasiswa_id', $mahasiswaId)
            ->where('tahun_akademik_id', $termId)
            ->first();
    }

    /**
     * Today's lectures, drawn from the weekly slots of every class in an
     * approved study plan.
     *
     * @return Collection<int, JadwalKuliah>
     */
    private function jadwalHariIni(int $mahasiswaId, ?int $termId): Collection
    {
        if ($termId === null) {
            return collect();
        }

        // Carbon counts Sunday as 0; the schedule uses the Indonesian
        // convention of Monday as 1.
        $hari = (int) Carbon::today()->dayOfWeekIso;

        return JadwalKuliah::query()
            ->with(['kelasKuliah.mataKuliah', 'kelasKuliah.dosenPengampu', 'ruang'])
            ->where('hari', $hari)
            ->whereHas('kelasKuliah.krsDetail.krs', function ($query) use ($mahasiswaId, $termId): void {
                $query->where('mahasiswa_id', $mahasiswaId)
                    ->where('tahun_akademik_id', $termId)
                    ->where('status', 'disetujui');
            })
            ->orderBy('jam_mulai')
            ->get();
    }
}
