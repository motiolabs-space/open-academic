<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Enums\KrsStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Support\Portal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $dosen = Portal::user();
        $term = Portal::term();

        $kelas = $this->kelasDiampu($dosen->id, $term?->id);

        return view('dosen.dashboard', [
            'judul' => 'Selamat datang, '.Str::before($dosen->nama, ' '),
            'konteks' => ($dosen->nidn ? 'NIDN '.$dosen->nidn.' · ' : 'Praktisi · ')
                .($dosen->prodi?->namaLengkap() ?? '—'),
            'dosen' => $dosen,
            'term' => $term,
            'kelas' => $kelas,
            'totalMahasiswa' => $kelas->sum('terisi'),
            'kelasBelumFinal' => $kelas->where('status_nilai', '!=', 'final')->count(),
            'antreanKrs' => $this->antreanKrs($dosen->id, $term?->id),
            'jumlahBimbingan' => Mahasiswa::where('dosen_wali_id', $dosen->id)->aktif()->count(),
            'jadwalHariIni' => $this->jadwalHariIni($kelas),
        ]);
    }

    /** @return Collection<int, KelasKuliah> */
    private function kelasDiampu(int $dosenId, ?int $termId): Collection
    {
        if ($termId === null) {
            return collect();
        }

        return KelasKuliah::query()
            ->with(['mataKuliah', 'jadwal.ruang', 'pertemuan'])
            ->where('tahun_akademik_id', $termId)
            ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosenId))
            ->orderBy('kode')
            ->get();
    }

    /**
     * Study plans waiting for this lecturer as academic advisor.
     *
     * @return Collection<int, Krs>
     */
    private function antreanKrs(int $dosenId, ?int $termId): Collection
    {
        if ($termId === null) {
            return collect();
        }

        return Krs::query()
            ->with(['mahasiswa.prodi'])
            ->where('tahun_akademik_id', $termId)
            ->where('status', KrsStatus::Diajukan->value)
            ->untukWali($dosenId)
            ->orderBy('diajukan_at')
            ->get();
    }

    /**
     * @param Collection<int, KelasKuliah> $kelas
     * @return Collection<int, array{kelas: KelasKuliah, jadwal: JadwalKuliah}>
     */
    private function jadwalHariIni(Collection $kelas): Collection
    {
        $hari = (int) Carbon::today()->dayOfWeekIso;

        return $kelas
            ->flatMap(fn (KelasKuliah $k) => $k->jadwal->map(fn ($j) => ['kelas' => $k, 'jadwal' => $j]))
            ->filter(fn (array $row): bool => (int) $row['jadwal']->hari === $hari)
            ->sortBy(fn (array $row) => $row['jadwal']->jam_mulai)
            ->values();
    }
}
