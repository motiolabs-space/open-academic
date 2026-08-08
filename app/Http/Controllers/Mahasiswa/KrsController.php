<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KrsDetail;
use App\Services\Akademik\KrsService;
use App\Services\Akademik\PrasyaratChecker;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The KRS screen — the flow a student uses most and complains about loudest.
 *
 * Every rule shown here is asked of KrsService rather than re-derived: the
 * catalogue marks a row unavailable for exactly the reason the service would
 * refuse it, so the button a student can press is the button that works.
 */
class KrsController extends Controller
{
    public function __construct(
        private readonly KrsService $krsService,
        private readonly PrasyaratChecker $prasyarat,
    ) {}

    public function index(): View
    {
        $mahasiswa = Portal::user();
        $term = Portal::term();

        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $term);
        $krs->load(['detail.kelasKuliah.mataKuliah', 'detail.kelasKuliah.jadwal.ruang', 'detail.kelasKuliah.dosenPengampu']);

        $this->authorize('view', $krs);

        return view('mahasiswa.krs', [
            'judul' => 'Rencana Studi',
            'konteks' => $term->nama.' · Semester '.$krs->semester_ke,
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), 'Rencana Studi'],
            'krs' => $krs,
            'ringkasan' => $this->krsService->ringkas($krs),
            'katalog' => $this->katalog($krs),
            'term' => $term,
        ]);
    }

    public function tambah(KelasKuliah $kelas): RedirectResponse
    {
        $krs = $this->krsService->bukaAtauAmbil(Portal::user(), Portal::term());

        $this->authorize('update', $krs);

        $this->krsService->tambahKelas($krs, $kelas);

        return back()->with('sukses', $kelas->mataKuliah->nama.' ditambahkan ke rencana studi.');
    }

    public function hapus(KrsDetail $detail): RedirectResponse
    {
        $krs = $detail->krs;

        $this->authorize('update', $krs);

        $this->krsService->hapusKelas($krs, $detail);

        return back()->with('sukses', 'Kelas dikeluarkan dari rencana studi.');
    }

    public function ajukan(): RedirectResponse
    {
        $krs = $this->krsService->bukaAtauAmbil(Portal::user(), Portal::term());

        $this->authorize('submit', $krs);

        $this->krsService->ajukan($krs);

        return back()->with('krs_diajukan', true);
    }

    /**
     * Offerings the student may consider, each annotated with why it is or is
     * not takeable.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function katalog($krs): Collection
    {
        $mahasiswa = $krs->mahasiswa;

        $diambil = $krs->detail->pluck('kelasKuliah.mata_kuliah_id')->all();

        $kelas = KelasKuliah::query()
            ->with(['mataKuliah.prasyarat', 'jadwal.ruang', 'dosenPengampu'])
            ->where('tahun_akademik_id', $krs->tahun_akademik_id)
            ->when(
                $mahasiswa->kurikulum_id,
                fn ($query) => $query->whereHas(
                    'mataKuliah.kurikulum',
                    fn ($sub) => $sub->where('kurikulum.id', $mahasiswa->kurikulum_id),
                ),
                fn ($query) => $query->where('prodi_id', $mahasiswa->prodi_id),
            )
            ->get();

        return $kelas
            ->map(function (KelasKuliah $item) use ($mahasiswa, $diambil, $krs): array {
                $sudahDiambil = in_array($item->mata_kuliah_id, $diambil, true);
                $sudahLulus = $this->prasyarat->sudahLulus($mahasiswa, $item->mataKuliah);
                $belumPrasyarat = config('academic.krs.enforce_prerequisites')
                    ? $this->prasyarat->belumTerpenuhi($mahasiswa, $item->mataKuliah)
                    : [];

                $melebihiBatas = ($krs->total_sks + $item->sks) > $krs->batas_sks;

                return [
                    'kelas' => $item,
                    'sudah_diambil' => $sudahDiambil,
                    'sudah_lulus' => $sudahLulus,
                    'belum_prasyarat' => $belumPrasyarat,
                    'penuh' => $item->penuh(),
                    'melebihi_batas' => $melebihiBatas,
                    'dapat_diambil' => !$sudahDiambil
                        && !$sudahLulus
                        && $belumPrasyarat === []
                        && !$item->penuh()
                        && !$melebihiBatas
                        && $krs->status->isEditable(),
                ];
            })
            ->sortBy(fn (array $baris): string => $baris['kelas']->mataKuliah->kode)
            ->values();
    }
}
