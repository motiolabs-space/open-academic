<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\NilaiKegiatanRequest;
use App\Http\Requests\Kemahasiswaan\PeriksaLogbookRequest;
use App\Models\Kemahasiswaan\KegiatanLapanganKelompok;
use App\Models\Kemahasiswaan\KegiatanLapanganLogbook;
use App\Models\Sdm\Dosen;
use App\Services\Kemahasiswaan\KegiatanLapanganService;
use App\Services\Kemahasiswaan\LogbookKegiatanService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The field supervisor's side of KKN/PKL: the groups assigned to them, their
 * members' logs, and the marks.
 *
 * Every group here is one where `dosen_pembimbing_id` is the signed-in
 * lecturer; KegiatanLapanganKelompokPolicy decides that per group.
 */
class KegiatanLapanganController extends Controller
{
    public function __construct(
        private readonly KegiatanLapanganService $kegiatan,
        private readonly LogbookKegiatanService $logbook,
    ) {}

    public function index(): View
    {
        $dosen = $this->dosen();

        $kelompok = KegiatanLapanganKelompok::query()
            ->where('dosen_pembimbing_id', $dosen->id)
            ->with(['kegiatanLapangan.tahunAkademik', 'lokasi'])
            ->withCount([
                'anggota',
                'anggota as dinilai_count' => fn ($q) => $q->whereNotNull('nilai_akhir'),
            ])
            ->orderByDesc('id')
            ->get();

        $belumDiperiksa = KegiatanLapanganLogbook::query()
            ->join('aktivitas_mahasiswa', 'aktivitas_mahasiswa.id', '=', 'kegiatan_lapangan_logbook.aktivitas_mahasiswa_id')
            ->whereNull('kegiatan_lapangan_logbook.diperiksa_at')
            ->whereNull('aktivitas_mahasiswa.deleted_at')
            ->whereIn('aktivitas_mahasiswa.kegiatan_lapangan_kelompok_id', $kelompok->pluck('id'))
            ->selectRaw('aktivitas_mahasiswa.kegiatan_lapangan_kelompok_id as kelompok_id, COUNT(*) as jumlah')
            ->groupBy('aktivitas_mahasiswa.kegiatan_lapangan_kelompok_id')
            ->pluck('jumlah', 'kelompok_id');

        return view('dosen.kegiatan-lapangan.index', [
            'judul' => __('kegiatan_lapangan.judul_dosen'),
            'konteks' => __('kegiatan_lapangan.konteks_dosen', ['jumlah' => $kelompok->count()]),
            'breadcrumb' => ['Portal' => route('dosen.dashboard'), __('kegiatan_lapangan.judul_dosen')],
            'kelompok' => $kelompok,
            'belumDiperiksa' => $belumDiperiksa,
        ]);
    }

    public function show(KegiatanLapanganKelompok $kelompok): View
    {
        $this->authorize('view', $kelompok);

        $kelompok->load([
            'kegiatanLapangan.tahunAkademik',
            'lokasi',
            'anggota' => fn ($q) => $q->with(['mahasiswa.prodi', 'krsKonversi', 'logbook']),
        ]);

        return view('dosen.kegiatan-lapangan.show', [
            'judul' => $kelompok->nama,
            'konteks' => $kelompok->kegiatanLapangan->nama,
            'breadcrumb' => [
                'Portal' => route('dosen.dashboard'),
                __('kegiatan_lapangan.judul_dosen') => route('dosen.kegiatan-lapangan'),
                $kelompok->nama,
            ],
            'kelompok' => $kelompok,
            'anggota' => $kelompok->anggota->sortBy(fn ($a): string => (string) $a->mahasiswa->nim)->values(),
        ]);
    }

    public function nilai(NilaiKegiatanRequest $request, KegiatanLapanganKelompok $kelompok): RedirectResponse
    {
        $jumlah = $this->kegiatan->nilaiKelompok($kelompok, $request->isian());

        return back()->with('sukses', __('kegiatan_lapangan.sukses.nilai', ['jumlah' => $jumlah]));
    }

    public function periksa(PeriksaLogbookRequest $request, KegiatanLapanganLogbook $logbook): RedirectResponse
    {
        $this->logbook->periksa($logbook, $this->dosen(), $request->validated('catatan'));

        return back()->with('sukses', __('kegiatan_lapangan.sukses.periksa'));
    }

    private function dosen(): Dosen
    {
        $dosen = Portal::user();

        abort_unless($dosen instanceof Dosen, 403);

        return $dosen;
    }
}
