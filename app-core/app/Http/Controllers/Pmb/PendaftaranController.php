<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pmb;

use App\Enums\Agama;
use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pmb\BiodataPendaftarRequest;
use App\Http\Requests\Pmb\MulaiPendaftaranRequest;
use App\Models\Pmb\PmbAkun;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Pmb\KartuUjianPendaftarService;
use App\Services\Pmb\PendaftaranPmbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * One application, seen by the applicant who owns it.
 *
 * Every route takes the application's UUID and authorises against the
 * signed-in account. The UUID alone is not the protection — a forwarded link
 * still opens nothing for anybody else.
 */
class PendaftaranController extends Controller
{
    public function __construct(
        private readonly PendaftaranPmbService $pendaftaran,
        private readonly KartuUjianPendaftarService $kartu,
    ) {}

    public function store(MulaiPendaftaranRequest $request): RedirectResponse
    {
        /** @var PmbAkun $akun */
        $akun = $request->user();

        $pendaftar = $this->pendaftaran->mulai(
            $akun,
            $request->gelombang(),
            $request->pilihan1(),
            $request->pilihan2(),
        );

        return redirect()->route('pmb.portal.show', $pendaftar);
    }

    public function show(PmbPendaftar $pendaftar): View
    {
        $this->authorize('view', $pendaftar);

        $pendaftar->load([
            'gelombang.tahunAkademik', 'gelombang.prodiDitawarkan', 'gelombang.jadwalSeleksi',
            'prodiPilihan1', 'prodiPilihan2', 'prodiDiterima', 'mahasiswa', 'berkas', 'pembayaran',
        ]);

        return view('pmb.portal.pendaftaran', [
            'pendaftar' => $pendaftar,
            'biodataKurang' => $this->pendaftaran->biodataKurang($pendaftar),
            'dokumenWajib' => $pendaftar->dokumenWajib(),
            'jenisBerkas' => (array) config('berkas.pmb'),
            'kartu' => $this->kartu->kelayakan($pendaftar),
            'opsiGender' => Gender::options(),
            'opsiAgama' => Agama::options(),
        ]);
    }

    public function biodata(BiodataPendaftarRequest $request, PmbPendaftar $pendaftar): RedirectResponse
    {
        $this->pendaftaran->simpanBiodata($pendaftar, $request->dto());

        return back()->with('sukses', __('pmb.formulir.biodata_tersimpan'));
    }

    public function ajukan(PmbPendaftar $pendaftar): RedirectResponse
    {
        $this->authorize('update', $pendaftar);

        $this->pendaftaran->ajukan($pendaftar);

        return back()->with('sukses', __('pmb.formulir.diajukan'));
    }

    public function batal(PmbPendaftar $pendaftar): RedirectResponse
    {
        $this->authorize('update', $pendaftar);

        $this->pendaftaran->batalkan($pendaftar);

        return back()->with('sukses', __('pmb.formulir.dibatalkan'));
    }

    /**
     * The exam card. A refusal is sent back with its reason, which the
     * applicant can act on; a 403 they could not.
     */
    public function kartu(Request $request, PmbPendaftar $pendaftar): Response|RedirectResponse
    {
        $this->authorize('view', $pendaftar);

        $kelayakan = $this->kartu->kelayakan($pendaftar);

        if (!$kelayakan['layak']) {
            return back()->with('galat', __('pmb.kartu.belum').' '.implode(' ', $kelayakan['alasan']));
        }

        return $this->kartu->pdf($pendaftar)
            ->download('Kartu-Ujian-'.$pendaftar->nomor_pendaftaran.'.pdf');
    }
}
