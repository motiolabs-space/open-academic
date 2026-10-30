<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Master;

use App\DTOs\Akademik\AkreditasiProdiData;
use App\Enums\LembagaAkreditasi;
use App\Enums\PeringkatAkreditasi;
use App\Exceptions\AturanAkademikException;
use App\Http\Requests\Admin\CatatAkreditasiRequest;
use App\Models\Akademik\AkreditasiProdi;
use App\Models\Akademik\Prodi;
use App\Services\Akademik\AkreditasiProdiService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * One programme's accreditation decrees (ADR-041 §3). Added, or withdrawn
 * when entered by mistake; never edited, because the decree in force on a
 * past graduation day must stay answerable.
 */
class AkreditasiProdiController extends MasterController
{
    public function __construct(private readonly AkreditasiProdiService $akreditasi) {}

    public function index(Prodi $prodi): View
    {
        $this->authorize('viewAny', AkreditasiProdi::class);

        $prodi->load('riwayatAkreditasi.pencatat');

        return view('admin.master.prodi-akreditasi', [
            ...$this->halaman('prodi', __('akreditasi.konteks', [
                'prodi' => $prodi->namaLengkap(),
                'jumlah' => $prodi->riwayatAkreditasi->count(),
            ]), []),
            'judul' => __('akreditasi.judul'),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Master Akademik' => route('admin.master.prodi'),
                __('akreditasi.judul'),
            ],
            'prodi' => $prodi,
            'keadaan' => $this->akreditasi->keadaan($prodi),
            'berlaku' => $prodi->akreditasiPada(now()),
            'lembagaPilihan' => LembagaAkreditasi::options(),
            'peringkatPilihan' => PeringkatAkreditasi::options(),
            'bulanPeringatan' => $this->akreditasi->bulanPeringatan(),
        ]);
    }

    public function store(CatatAkreditasiRequest $request, Prodi $prodi): RedirectResponse
    {
        $data = AkreditasiProdiData::fromRequest($request);

        try {
            $this->akreditasi->catat($prodi, $data, Portal::user(), $request->file('dokumen'));
        } catch (AturanAkademikException $e) {
            return back()->withInput()->with('galat', $e->getMessage());
        }

        return back()->with('sukses', __('akreditasi.pesan.dicatat', ['nomor' => $data->nomorSk]));
    }

    public function destroy(Prodi $prodi, AkreditasiProdi $akreditasi): RedirectResponse
    {
        // Scoped by hand: a decree reached through another programme's URL
        // is not found, whatever its id.
        abort_unless($akreditasi->prodi_id === $prodi->id, 404);
        $this->authorize('delete', $akreditasi);

        $this->akreditasi->tarik($akreditasi);

        return back()->with('sukses', __('akreditasi.pesan.ditarik', ['nomor' => $akreditasi->nomor_sk]));
    }
}
