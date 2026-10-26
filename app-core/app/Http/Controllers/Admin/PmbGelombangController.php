<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pmb\ImporNilaiSeleksiRequest;
use App\Http\Requests\Pmb\JadwalSeleksiRequest;
use App\Http\Requests\Pmb\PengaturanGelombangRequest;
use App\Http\Requests\Pmb\TawaranProdiRequest;
use App\Models\Akademik\Prodi;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbGelombangProdi;
use App\Models\Pmb\PmbJadwalSeleksi;
use App\Services\Pmb\GelombangPmbService;
use App\Services\Pmb\PengumumanPmbService;
use App\Services\Pmb\SeleksiPmbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * One wave, end to end: who may apply online and for what, when selection
 * happens, what came out of it, and when applicants are told.
 */
class PmbGelombangController extends Controller
{
    public function __construct(
        private readonly GelombangPmbService $gelombang,
        private readonly SeleksiPmbService $seleksi,
        private readonly PengumumanPmbService $pengumuman,
    ) {}

    public function show(PmbGelombang $gelombang): View
    {
        $this->authorize('view', $gelombang);

        $gelombang->load(['tahunAkademik', 'prodiDitawarkan.prodi', 'jadwalSeleksi']);

        return view('admin.pmb.gelombang', [
            'judul' => __('pmb.admin.judul_gelombang', ['nama' => $gelombang->nama]),
            'konteks' => $gelombang->jalur?->label().' · '.$gelombang->tahunAkademik->nama,
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'PMB' => route('admin.pmb'), $gelombang->kode],
            'gelombang' => $gelombang,
            'daftarProdi' => Prodi::orderBy('nama')->get(['id', 'nama', 'jenjang']),
            'jenisBerkas' => (array) config('berkas.pmb'),
            'otomatis' => (bool) config('pmb.kelulusan_otomatis'),
            'belumDiputuskan' => $this->pengumuman->belumDiputuskan($gelombang),
            'rekap' => $this->gelombang->rekapProdi($gelombang),
        ]);
    }

    public function update(PengaturanGelombangRequest $request, PmbGelombang $gelombang): RedirectResponse
    {
        $this->gelombang->perbarui($gelombang, $request->dto());

        return back()->with('sukses', __('pmb.admin.tersimpan'));
    }

    public function simpanTawaran(TawaranProdiRequest $request, PmbGelombang $gelombang): RedirectResponse
    {
        $this->gelombang->simpanTawaran($gelombang, $request->dto());

        return back()->with('sukses', __('pmb.admin.tawaran_tersimpan'));
    }

    public function hapusTawaran(PmbGelombang $gelombang, PmbGelombangProdi $tawaran): RedirectResponse
    {
        $this->authorize('update', $gelombang);
        abort_unless($tawaran->pmb_gelombang_id === $gelombang->id, 404);

        $this->gelombang->hapusTawaran($tawaran);

        return back()->with('sukses', __('pmb.admin.tawaran_dihapus'));
    }

    public function storeJadwal(JadwalSeleksiRequest $request, PmbGelombang $gelombang): RedirectResponse
    {
        $this->seleksi->tambahJadwal($gelombang, $request->dto());

        return back()->with('sukses', __('pmb.jadwal.tersimpan'));
    }

    public function hapusJadwal(PmbGelombang $gelombang, PmbJadwalSeleksi $jadwal): RedirectResponse
    {
        $this->authorize('update', $gelombang);
        abort_unless($jadwal->pmb_gelombang_id === $gelombang->id, 404);

        $this->seleksi->hapusJadwal($jadwal);

        return back()->with('sukses', __('pmb.jadwal.dihapus'));
    }

    /**
     * The score sheet. A rejected file comes back with every bad row listed,
     * because a committee fixing a sheet one error per upload gives up.
     */
    public function imporNilai(ImporNilaiSeleksiRequest $request, PmbGelombang $gelombang): RedirectResponse
    {
        $hasil = $this->seleksi->imporNilai($gelombang, $request->isi());

        if (!$hasil->berhasil()) {
            return back()
                ->with('galat', __('pmb.admin.impor_gagal', ['jumlah' => count($hasil->galat)]))
                ->with('galat_impor', $hasil->galat);
        }

        $pesan = __('pmb.admin.impor_berhasil', ['jumlah' => $hasil->tersimpan]);

        if (config('pmb.kelulusan_otomatis')) {
            $pesan .= ' '.__('pmb.admin.impor_otomatis', [
                'lulus' => $hasil->lulusOtomatis,
                'tidak' => $hasil->tidakLulusOtomatis,
            ]);
        }

        return back()->with('sukses', $pesan);
    }

    public function umumkan(PmbGelombang $gelombang): RedirectResponse
    {
        $this->authorize('update', $gelombang);

        $jumlah = $this->pengumuman->umumkan($gelombang);

        return back()->with('sukses', __('pmb.pengumuman.diumumkan', ['jumlah' => $jumlah]));
    }
}
