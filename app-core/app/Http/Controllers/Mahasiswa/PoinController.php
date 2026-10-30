<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\DTOs\Kemahasiswaan\LaporanPrestasiData;
use App\Enums\BentukKepesertaan;
use App\Enums\BidangLomba;
use App\Enums\JenisRekognisi;
use App\Enums\ModaPelaksanaan;
use App\Enums\PeringkatPrestasi;
use App\Enums\TingkatSimkatmawa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\LaporPrestasiRequest;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Services\Kemahasiswaan\PoinKemahasiswaanService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The student's own conduct record, read-only, and the form to report an
 * achievement (ADR-040 §3).
 *
 * The two ledgers are shown side by side and never summed — the same rule as
 * the staff screen, for the reason PoinKemahasiswaanService gives.
 */
class PoinController extends Controller
{
    public function __construct(private readonly PoinKemahasiswaanService $poin) {}

    public function index(): View
    {
        $mahasiswa = Portal::user();

        return view('mahasiswa.poin', [
            'judul' => __('swalayan.prestasi.judul'),
            'konteks' => $mahasiswa->nim,
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), __('swalayan.prestasi.judul')],
            'rekap' => $this->poin->rekap($mahasiswa),
            'riwayat' => $this->poin->riwayat($mahasiswa),
            'kategori' => $this->poin->kategoriLaporan(),
            'daftarTerm' => TahunAkademik::terbaru()->limit(8)->get(['id', 'kode', 'nama']),
            'pilihan' => [
                'tingkat' => TingkatSimkatmawa::options(),
                'peringkat' => PeringkatPrestasi::options(),
                'bentuk_kepesertaan' => BentukKepesertaan::options(),
                'moda_pelaksanaan' => ModaPelaksanaan::options(),
                'bidang_lomba' => BidangLomba::options(),
                'jenis_rekognisi' => JenisRekognisi::options(),
            ],
        ]);
    }

    public function lapor(LaporPrestasiRequest $request): RedirectResponse
    {
        $kategori = PoinKategori::whereUuid((string) $request->string('kategori'))->firstOrFail();

        $baris = $this->poin->laporkan(Portal::user(), $kategori, LaporanPrestasiData::fromRequest($request));

        return back()->with('sukses', __('swalayan.prestasi.sukses', ['judul' => $baris->judul]));
    }
}
