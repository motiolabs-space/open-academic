<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pmb\CatatPembayaranFormulirRequest;
use App\Http\Requests\Pmb\KembalikanBerkasRequest;
use App\Models\Pmb\PmbBerkas;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Pmb\KartuUjianPendaftarService;
use App\Services\Pmb\PendaftaranPmbService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * One applicant, as the admissions desk sees them: biodata, documents to
 * accept or send back, the form fee, and the decision.
 */
class PmbPendaftarController extends Controller
{
    public function __construct(
        private readonly PendaftaranPmbService $pendaftaran,
        private readonly KartuUjianPendaftarService $kartu,
    ) {}

    public function show(PmbPendaftar $pendaftar): View
    {
        $this->authorize('view', $pendaftar);

        $pendaftar->load([
            'gelombang.tahunAkademik', 'gelombang.prodiDitawarkan', 'akun', 'prodiPilihan1', 'prodiPilihan2',
            'prodiDiterima', 'mahasiswa', 'berkas', 'pembayaran.pencatat',
        ]);

        return view('admin.pmb.pendaftar', [
            'judul' => $pendaftar->nama,
            'konteks' => $pendaftar->nomor_pendaftaran.' · '.$pendaftar->status->label(),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'PMB' => route('admin.pmb'), $pendaftar->nomor_pendaftaran],
            'pendaftar' => $pendaftar,
            'biodataKurang' => $this->pendaftaran->biodataKurang($pendaftar),
            'dokumenWajib' => $pendaftar->dokumenWajib(),
            'jenisBerkas' => (array) config('berkas.pmb'),
            'kartu' => $this->kartu->kelayakan($pendaftar),
            'channel' => CatatPembayaranFormulirRequest::CHANNEL,
            'termAktif' => Portal::term(),
        ]);
    }

    public function kembalikanBerkas(KembalikanBerkasRequest $request, PmbBerkas $berkas): RedirectResponse
    {
        $this->pendaftaran->kembalikanBerkas($berkas, (string) $request->string('catatan'));

        return back()->with('sukses', __('pmb.admin.berkas_dikembalikan'));
    }
}
