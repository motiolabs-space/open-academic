<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Akademik\CariRuangKosongRequest;
use App\Http\Requests\Akademik\PemanfaatanRuangRequest;
use App\Models\Akademik\Gedung;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Services\Akademik\PemanfaatanRuangService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Room utilisation (#42): the report, its export, and the free-room search.
 *
 * Everything here reads. Rooms are held by the timetables that use them —
 * the class timetable's and the exam timetable's clash checks — and this
 * screen only shows what those already hold.
 */
class PemanfaatanRuangController extends Controller
{
    public function __construct(private readonly PemanfaatanRuangService $pemanfaatan) {}

    public function index(PemanfaatanRuangRequest $request): View
    {
        $laporan = $this->pemanfaatan->laporan($request->filter());
        $dipilih = $laporan->cariRuang($request->validated('ruang'));

        return view('admin.pemanfaatan-ruang.index', [
            'judul' => __('pemanfaatan_ruang.judul'),
            'konteks' => __('pemanfaatan_ruang.konteks', [
                'term' => $laporan->filter->term->nama,
                'jumlah' => $laporan->ruang->count(),
            ]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('pemanfaatan_ruang.judul')],
            'laporan' => $laporan,
            'dipilih' => $dipilih,
            'peta' => $dipilih?->petaPanas ?? $laporan->petaPanasRerata(),
            'daftarTerm' => TahunAkademik::terbaru()->pluck('nama', 'id'),
            'daftarGedung' => Gedung::orderBy('kode')->pluck('nama', 'id'),
            'jenisPilihan' => __('pemanfaatan_ruang.jenis_ruang'),
            'urutPilihan' => __('pemanfaatan_ruang.urut'),
            'namaHari' => JadwalKuliah::HARI,
        ]);
    }

    public function ekspor(PemanfaatanRuangRequest $request): StreamedResponse
    {
        return $this->pemanfaatan->ekspor($this->pemanfaatan->laporan($request->filter()));
    }

    public function ruangKosong(CariRuangKosongRequest $request): View
    {
        $term = $request->term();
        $kriteria = $request->kriteria();

        return view('admin.pemanfaatan-ruang.ruang-kosong', [
            'judul' => __('pemanfaatan_ruang.kosong.judul'),
            'konteks' => $term->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('pemanfaatan_ruang.judul') => route('admin.pemanfaatan-ruang.index', ['term' => $term->id]),
                __('pemanfaatan_ruang.kosong.judul'),
            ],
            'term' => $term,
            'hasil' => $kriteria === null ? null : $this->pemanfaatan->cariKosong($kriteria),
            'daftarTerm' => TahunAkademik::terbaru()->pluck('nama', 'id'),
            'daftarGedung' => Gedung::orderBy('kode')->pluck('nama', 'id'),
            'jenisPilihan' => __('pemanfaatan_ruang.jenis_ruang'),
            'hariPilihan' => JadwalKuliah::HARI,
        ]);
    }
}
