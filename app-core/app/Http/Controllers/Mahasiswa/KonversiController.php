<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Akademik\UnggahDokumenKonversiRequest;
use App\Models\Akademik\KonversiKredit;
use App\Services\Akademik\KonversiService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The student's credit conversions, read-only, plus the one thing they can
 * do: send a document the assessor asked for (ADR-040 §4).
 */
class KonversiController extends Controller
{
    public function __construct(private readonly KonversiService $konversi) {}

    public function index(): View
    {
        $mahasiswa = Portal::user();
        $mahasiswa->loadMissing('prodi');

        $sudah = $this->konversi->sudahDiakui($mahasiswa);

        return view('mahasiswa.konversi', [
            'judul' => __('swalayan.konversi.judul'),
            'konteks' => __('swalayan.konversi.konteks', ['sks' => $sudah]),
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), __('swalayan.konversi.judul')],
            'daftar' => $this->konversi->milikMahasiswa($mahasiswa),
            'sudah' => $sudah,
            'batas' => $this->konversi->batas($mahasiswa),
        ]);
    }

    public function unggah(UnggahDokumenKonversiRequest $request, KonversiKredit $konversi): RedirectResponse
    {
        $this->konversi->unggahDokumen($konversi, Portal::user(), $request->file('berkas'));

        return back()->with('sukses', __('swalayan.konversi.sukses_unggah'));
    }
}
