<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\PemetaanCapaianRequest;
use App\Models\Akademik\CapaianCplMahasiswa;
use App\Models\Akademik\KelasKuliah;
use App\Services\Akademik\CapaianService;
use App\Services\Akademik\RekapCapaianService;
use App\Support\EksporTabel;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Outcome attainment of the classes a lecturer teaches, and the component ×
 * outcome mapping that produces it.
 */
class CapaianController extends Controller
{
    public function __construct(
        private readonly CapaianService $capaian,
        private readonly RekapCapaianService $rekap,
    ) {}

    public function index(): View
    {
        $dosen = Portal::user();
        $term = Portal::term();

        return view('dosen.capaian', [
            'judul' => __('capaian.nav.kelas'),
            'konteks' => $term->nama,
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), __('capaian.nav.kelas')],

            'daftar' => KelasKuliah::query()
                ->with('mataKuliah')
                ->where('tahun_akademik_id', $term->id)
                ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosen->getKey()))
                ->orderBy('nama')
                ->get(),
        ]);
    }

    public function kelas(KelasKuliah $kelas): View
    {
        $this->authorize('viewKelas', [CapaianCplMahasiswa::class, $kelas]);

        return view('dosen.capaian-kelas', [
            'judul' => __('capaian.rekap.kelas_judul'),
            'konteks' => $kelas->mataKuliah->nama.' · Kelas '.$kelas->kode.' · '.$kelas->tahunAkademik->nama,
            'breadcrumb' => [
                'Dasbor' => route('dosen.dashboard'),
                __('capaian.nav.kelas') => route('dosen.capaian'),
                $kelas->mataKuliah->kode,
            ],

            'kelas' => $kelas,
            'rekap' => $rekap = $this->rekap->kelas($kelas),

            // Only read when there is nothing stored yet: then the useful
            // thing to show is what the plan does not measure.
            'cakupan' => $rekap['terhitung'] ? null : $this->capaian->cakupan($kelas),
        ]);
    }

    public function ekspor(KelasKuliah $kelas): StreamedResponse|BinaryFileResponse
    {
        $this->authorize('viewKelas', [CapaianCplMahasiswa::class, $kelas]);

        $rekap = $this->rekap->kelas($kelas);

        return EksporTabel::unduh(
            sprintf('capaian-%s-%s-%s', $kelas->tahunAkademik->kode, $kelas->mataKuliah->kode, $kelas->kode),
            'csv',
            [
                __('capaian.rekap.nim'),
                __('capaian.rekap.nama'),
                ...$rekap['cpmk']->pluck('kode')->all(),
                ...$rekap['cpl']->pluck('kode')->all(),
            ],
            $rekap['mahasiswa']->map(fn (array $baris): array => [
                $baris['mahasiswa']->nim,
                $baris['mahasiswa']->nama,
                ...$rekap['cpmk']->map(fn ($c): string => self::angka($baris['cpmk'][$c->id] ?? null))->all(),
                ...$rekap['cpl']->map(fn ($c): string => self::angka($baris['cpl'][$c->id] ?? null))->all(),
            ]),
        );
    }

    public function petakan(PemetaanCapaianRequest $request, KelasKuliah $kelas): RedirectResponse
    {
        $this->authorize('grade', $kelas);

        $jumlah = $this->capaian->petakan($kelas, $request->pemetaan());

        return back()->with('sukses', __('capaian.sukses.pemetaan', ['jumlah' => $jumlah]));
    }

    /** Decimal comma in the file too: the people opening it read Indonesian numbers. */
    private static function angka(?float $nilai): string
    {
        return $nilai === null ? '' : number_format($nilai, 2, ',', '');
    }
}
