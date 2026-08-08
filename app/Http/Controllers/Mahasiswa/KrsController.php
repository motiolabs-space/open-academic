<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KrsDetail;
use App\Services\Akademik\KrsService;
use App\Services\Akademik\PaketKuliahService;
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

    public function index(PaketKuliahService $paketService): View
    {
        $mahasiswa = Portal::user();
        $term = Portal::term();

        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $term);
        $krs->load([
            'detail.kelasKuliah.mataKuliah',
            'detail.kelasKuliah.jadwal.ruang',
            'detail.kelasKuliah.dosenPengampu',

            // Named in the catalogue's "not your track" note.
            'mahasiswa.konsentrasi',

            // Read by PaketKuliahService::berpaket to decide whether this
            // programme issues study plans.
            'mahasiswa.prodi',
        ]);

        $this->authorize('view', $krs);

        return view('mahasiswa.krs', [
            'judul' => 'Rencana Studi',
            'konteks' => $term->nama.' · Semester '.$krs->semester_ke,
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), 'Rencana Studi'],
            'krs' => $krs,
            'ringkasan' => $this->krsService->ringkas($krs),
            'katalog' => $this->katalog($krs),
            'term' => $term,

            // Only for programmes that issue plans. Everywhere else this is
            // null and the screen behaves exactly as it did before.
            'paket' => $paketService->berpaket($krs) && $krs->status->isEditable()
                ? $paketService->pratinjau($krs)
                : null,
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

    /**
     * Fills the plan from the package issued for this cohort.
     *
     * Reports per course rather than a bare count: a package where two of eight
     * could not be added is the ordinary case — a repeat student already holds
     * one, a class is full — and the student needs to know which two rather than
     * being told "6 ditambahkan" and left to find the gap themselves.
     */
    public function paket(PaketKuliahService $paketService): RedirectResponse
    {
        $krs = $this->krsService->bukaAtauAmbil(Portal::user(), Portal::term());

        $this->authorize('update', $krs);

        $hasil = $paketService->terapkan($krs);

        if ($hasil['dilewati'] !== []) {
            return back()
                ->with('sukses', $hasil['ditambahkan'].' mata kuliah ditambahkan dari paket.')
                ->with('paket_dilewati', $hasil['dilewati']);
        }

        return back()->with('sukses', 'Paket semester diterapkan: '.$hasil['ditambahkan'].' mata kuliah ditambahkan.');
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

        // Fetched once and handed to the rule per row. The rule stays in the
        // service; only the data it reads is shared.
        $peta = $mahasiswa->kurikulum_id === null
            ? null
            : $this->krsService->petaKurikulum((int) $mahasiswa->kurikulum_id);

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
            ->map(function (KelasKuliah $item) use ($mahasiswa, $diambil, $krs, $peta): array {
                $sudahDiambil = in_array($item->mata_kuliah_id, $diambil, true);
                $sudahLulus = $this->prasyarat->sudahLulus($mahasiswa, $item->mataKuliah);
                $belumPrasyarat = config('academic.krs.enforce_prerequisites')
                    ? $this->prasyarat->belumTerpenuhi($mahasiswa, $item->mataKuliah)
                    : [];

                $melebihiBatas = ($krs->total_sks + $item->sks) > $krs->batas_sks;

                /*
                 * Asked of the service rather than re-derived here.
                 *
                 * The SQL above narrows to the student's curriculum but knows
                 * nothing about concentrations, so a track course would render
                 * with a live "Ambil" button and then be refused on submit —
                 * the one failure this screen is built to avoid.
                 */
                $luarKonsentrasi = !$this->krsService->dalamKurikulum($mahasiswa, $item, $peta);

                return [
                    'kelas' => $item,
                    'sudah_diambil' => $sudahDiambil,
                    'sudah_lulus' => $sudahLulus,
                    'belum_prasyarat' => $belumPrasyarat,
                    'penuh' => $item->penuh(),
                    'melebihi_batas' => $melebihiBatas,
                    'luar_konsentrasi' => $luarKonsentrasi,
                    'dapat_diambil' => !$sudahDiambil
                        && !$sudahLulus
                        && $belumPrasyarat === []
                        && !$item->penuh()
                        && !$melebihiBatas
                        && !$luarKonsentrasi
                        && $krs->status->isEditable(),
                ];
            })
            ->sortBy(fn (array $baris): string => $baris['kelas']->mataKuliah->kode)
            ->values();
    }
}
