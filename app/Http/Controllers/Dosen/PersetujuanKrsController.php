<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Enums\KrsStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\KeputusanKrsRequest;
use App\Models\Akademik\Krs;
use App\Services\Akademik\KrsService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The academic advisor's approval queue.
 *
 * The screen surfaces the two things an advisor actually decides on — whether
 * the credit load fits the student's record, and whether the schedule holds
 * together — rather than making them open each plan to find out.
 */
class PersetujuanKrsController extends Controller
{
    public function __construct(private readonly KrsService $krsService) {}

    public function index(): View
    {
        $dosen = Portal::user();
        $term = Portal::term();

        $this->authorize('viewAny', Krs::class);

        $antrean = Krs::query()
            ->with([
                'mahasiswa.prodi',
                'detail.kelasKuliah.mataKuliah',
                'detail.kelasKuliah.jadwal',
            ])
            ->where('tahun_akademik_id', $term->id)
            ->where('status', KrsStatus::Diajukan->value)
            ->untukWali($dosen->id)
            ->orderBy('diajukan_at')
            ->get();

        $riwayat = Krs::query()
            ->with('mahasiswa')
            ->where('tahun_akademik_id', $term->id)
            ->whereIn('status', [KrsStatus::Disetujui->value, KrsStatus::Ditolak->value])
            ->untukWali($dosen->id)
            ->latest('disetujui_at')
            ->limit(8)
            ->get();

        return view('dosen.persetujuan-krs', [
            'judul' => 'Persetujuan KRS',
            'konteks' => $term->nama.' · '.$antrean->count().' menunggu keputusan',
            'breadcrumb' => ['Portal Dosen' => route('dosen.dashboard'), 'Persetujuan KRS'],
            'antrean' => $antrean->map(fn (Krs $krs): array => [
                'krs' => $krs,
                'peringatan' => $this->peringatan($krs),
            ]),
            'riwayat' => $riwayat,
        ]);
    }

    public function putuskan(KeputusanKrsRequest $request, Krs $krs): RedirectResponse
    {
        $this->authorize('approve', $krs);

        $keputusan = $request->toDto();

        $this->krsService->putuskan($krs, Portal::user(), $keputusan);

        return back()->with('sukses', sprintf(
            'Rencana studi %s telah %s.',
            $krs->mahasiswa->nama,
            $keputusan->disetujui ? 'disetujui' : 'ditolak',
        ));
    }

    /**
     * Things worth an advisor's attention before deciding.
     *
     * @return array<int, string>
     */
    private function peringatan(Krs $krs): array
    {
        $peringatan = [];

        if ($krs->total_sks > $krs->batas_sks) {
            $peringatan[] = "Melebihi batas {$krs->batas_sks} SKS";
        }

        if ($krs->ips_acuan !== null && (float) $krs->ips_acuan < 2.0) {
            $peringatan[] = 'IPS semester lalu di bawah 2,00';
        }

        if (($bentrok = $this->bentrokJadwal($krs)) !== null) {
            $peringatan[] = $bentrok;
        }

        return $peringatan;
    }

    /**
     * A plan can be assembled legitimately and still clash if a schedule was
     * changed after the student submitted it, so the queue re-checks rather
     * than trusting the plan was validated once.
     */
    private function bentrokJadwal(Krs $krs): ?string
    {
        $jadwal = $krs->detail
            ->flatMap(fn ($detail) => $detail->kelasKuliah->jadwal)
            ->values();

        foreach ($jadwal as $i => $satu) {
            foreach ($jadwal->slice($i + 1) as $dua) {
                if ($satu->bentrokDengan($dua)) {
                    return 'Bentrok jadwal pada '.$satu->namaHari();
                }
            }
        }

        return null;
    }
}
