<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\KrsStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\JadwalKuliah;
use App\Support\Portal;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The student's weekly timetable, built from the classes in their approved
 * study plan. A plan still awaiting approval shows nothing — the schedule is a
 * consequence of enrolment, not a preview of it.
 */
class JadwalController extends Controller
{
    /** Time bands the grid is drawn from. */
    private const JAM = ['07:30', '09:20', '11:10', '13:00', '15:00'];

    public function __invoke(): View
    {
        $mahasiswa = Portal::user();
        $term = Portal::term();

        $jadwal = JadwalKuliah::query()
            ->with(['kelasKuliah.mataKuliah', 'kelasKuliah.dosenPengampu', 'ruang'])
            ->whereHas('kelasKuliah.krsDetail.krs', function ($query) use ($mahasiswa, $term): void {
                $query->where('mahasiswa_id', $mahasiswa->id)
                    ->where('tahun_akademik_id', $term->id)
                    ->where('status', KrsStatus::Disetujui->value);
            })
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return view('mahasiswa.jadwal', [
            'judul' => 'Jadwal Kuliah',
            'konteks' => $term->nama.' · '.$jadwal->count().' sesi per pekan',
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), 'Jadwal Kuliah'],
            'perHari' => $this->kelompokPerHari($jadwal),
            'hariIni' => (int) now()->dayOfWeekIso,
            'jamBand' => self::JAM,
            'totalSks' => $jadwal->pluck('kelasKuliah')->unique('id')->sum('sks'),
        ]);
    }

    /** @return Collection<int, Collection<int, JadwalKuliah>> */
    private function kelompokPerHari(Collection $jadwal): Collection
    {
        // Senin–Sabtu selalu ditampilkan meski kosong, agar grid tidak berubah
        // bentuk dari pekan ke pekan.
        return collect(range(1, 6))->mapWithKeys(fn (int $hari): array => [
            $hari => $jadwal->where('hari', $hari)->values(),
        ]);
    }
}
