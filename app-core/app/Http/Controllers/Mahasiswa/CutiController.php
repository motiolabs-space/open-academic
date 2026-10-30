<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\DTOs\Kemahasiswaan\AjuanCutiData;
use App\Enums\JenisCuti;
use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\AjukanCutiRequest;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Services\Kemahasiswaan\CutiService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Applying for academic leave from the portal (ADR-040 §1).
 *
 * The decision stays at the registrar's desk (Admin\CutiController); this
 * screen files the application and shows what became of it.
 */
class CutiController extends Controller
{
    public function __construct(private readonly CutiService $cuti) {}

    public function index(): View
    {
        $mahasiswa = Portal::user();

        $riwayat = CutiMahasiswa::query()
            ->with('tahunAkademik')
            ->where('mahasiswa_id', $mahasiswa->id)
            ->latest('diajukan_at')
            ->get();

        $sudahDiajukan = $riwayat->pluck('tahun_akademik_id')->all();

        return view('mahasiswa.cuti', [
            'judul' => __('swalayan.cuti.judul'),
            'konteks' => $mahasiswa->status->label(),
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), __('swalayan.cuti.judul')],
            'mahasiswa' => $mahasiswa,
            'riwayat' => $riwayat,
            'menunggu' => $riwayat->contains(fn (CutiMahasiswa $c): bool => $c->status === LeaveStatus::Diajukan),
            'pilihanSemester' => $this->cuti->semesterAjuanMandiri()
                ->reject(fn (TahunAkademik $t): bool => in_array($t->id, $sudahDiajukan, true))
                ->pluck('nama', 'id'),
            'jenisPilihan' => JenisCuti::options(),
        ]);
    }

    public function ajukan(AjukanCutiRequest $request): RedirectResponse
    {
        $cuti = $this->cuti->ajukanMandiri(Portal::user(), AjuanCutiData::fromRequest($request));

        return back()->with('sukses', __('swalayan.cuti.sukses', ['semester' => $cuti->tahunAkademik->nama]));
    }
}
