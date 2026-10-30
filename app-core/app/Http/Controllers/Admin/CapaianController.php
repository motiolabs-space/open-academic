<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Akademik\CapaianCplMahasiswa;
use App\Models\Akademik\Prodi;
use App\Services\Akademik\RekapCapaianService;
use App\Support\EksporTabel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CPL attainment of one programme, per intake year — the operational recap a
 * programme head reads before a curriculum review.
 *
 * Not a dashboard and not a form: no targets, no comparison across
 * programmes, nothing arranged for a LAM (CLAUDE.md §1, §6).
 */
class CapaianController extends Controller
{
    public function __construct(private readonly RekapCapaianService $rekap) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CapaianCplMahasiswa::class);

        [$daftarProdi, $prodi, $angkatan] = $this->pilihan($request);

        $hasil = $prodi === null ? null : $this->rekap->prodi($prodi, $angkatan);

        return view('admin.capaian', [
            'judul' => __('capaian.rekap.prodi_judul'),
            'konteks' => $prodi === null ? '' : $prodi->nama.($angkatan === null ? '' : ' · '.__('capaian.rekap.angkatan').' '.$angkatan),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('capaian.rekap.prodi_judul')],

            'daftarProdi' => $daftarProdi,
            'prodi' => $prodi,
            'angkatan' => $angkatan,
            'hasil' => $hasil,
        ]);
    }

    public function ekspor(Request $request): StreamedResponse|BinaryFileResponse
    {
        $this->authorize('viewAny', CapaianCplMahasiswa::class);

        [, $prodi, $angkatan] = $this->pilihan($request);

        abort_if($prodi === null, 404);

        $hasil = $this->rekap->prodi($prodi, $angkatan);

        return EksporTabel::unduh(
            sprintf('capaian-cpl-%s%s', $prodi->kode, $angkatan === null ? '' : '-'.$angkatan),
            'csv',
            [
                __('capaian.rekap.nim'),
                __('capaian.rekap.nama'),
                __('capaian.rekap.angkatan'),
                ...$hasil['cpl']->pluck('kode')->all(),
            ],
            $hasil['mahasiswa']->map(fn (array $baris): array => [
                $baris['mahasiswa']->nim,
                $baris['mahasiswa']->nama,
                $baris['mahasiswa']->angkatan,
                // Decimal comma: the people opening the file read Indonesian numbers.
                ...$hasil['cpl']->map(fn ($c): string => isset($baris['cpl'][$c->id])
                    ? number_format($baris['cpl'][$c->id], 2, ',', '')
                    : '')->all(),
            ]),
        );
    }

    /** @return array{0: Collection<int, Prodi>, 1: Prodi|null, 2: int|null} */
    private function pilihan(Request $request): array
    {
        $daftarProdi = Prodi::orderBy('nama')->get();

        $prodi = $request->filled('prodi')
            ? $daftarProdi->firstWhere('uuid', (string) $request->string('prodi'))
            : $daftarProdi->first();

        $angkatan = $request->filled('angkatan') ? $request->integer('angkatan') : null;

        return [$daftarProdi, $prodi, $angkatan];
    }
}
