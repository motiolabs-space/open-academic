<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Kemahasiswaan\DataSimkatmawa;
use App\DTOs\Kemahasiswaan\FilterSimkatmawa;
use App\Enums\BentukKepesertaan;
use App\Enums\BidangLomba;
use App\Enums\JenisPoin;
use App\Enums\JenisRekognisi;
use App\Enums\ModaPelaksanaan;
use App\Enums\PeringkatPrestasi;
use App\Enums\TingkatSimkatmawa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\DataSimkatmawaRequest;
use App\Http\Requests\Kemahasiswaan\FilterSimkatmawaRequest;
use App\Http\Requests\Kemahasiswaan\KategoriSimkatmawaRequest;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Kemahasiswaan\EksporSimkatmawa;
use App\Services\Kemahasiswaan\PoinKemahasiswaanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The SIMKATMAWA report: pick a period, see what would be refused, download
 * what would not (ADR-037 §1). Nothing is sent to the ministry from here.
 */
class SimkatmawaController extends Controller
{
    public function __construct(
        private readonly EksporSimkatmawa $ekspor,
        private readonly PoinKemahasiswaanService $poin,
    ) {}

    public function index(FilterSimkatmawaRequest $request): View
    {
        $filter = FilterSimkatmawa::fromRequest($request);
        $tabel = $this->ekspor->tabel($filter);

        return view('admin.simkatmawa', [
            'judul' => __('simkatmawa.judul'),
            'konteks' => __('simkatmawa.konteks'),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('simkatmawa.judul')],

            'filter' => $filter,
            'tabel' => $tabel,
            'kategoriAktif' => $request->kategori(),
            'menunggu' => $this->ekspor->menungguVerifikasi($filter),
            'daftarProdi' => Prodi::query()->orderBy('nama')->get(['id', 'kode', 'nama', 'jenjang']),
            'tanpaKategori' => PoinKategori::query()->aktif()->where('jenis', JenisPoin::Prestasi->value)->whereNull('simkatmawa_kategori')->count(),
        ]);
    }

    public function unduh(FilterSimkatmawaRequest $request): StreamedResponse|BinaryFileResponse
    {
        return $this->ekspor->unduh(FilterSimkatmawa::fromRequest($request), $request->kategori(), $request->formatBerkas());
    }

    public function edit(PoinMahasiswa $poin): View
    {
        $this->authorize('lengkapiSimkatmawa', $poin);

        $poin->load(['mahasiswa.prodi', 'kategori', 'dosenPembimbing']);

        return view('admin.simkatmawa-lengkapi', [
            'judul' => __('simkatmawa.lengkapi.judul'),
            'konteks' => $poin->mahasiswa->nim.' · '.$poin->judul,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('simkatmawa.judul') => route('admin.simkatmawa'),
                __('simkatmawa.lengkapi.judul'),
            ],

            'poin' => $poin,
            'temuan' => $this->ekspor->periksa($poin),
            'opsi' => [
                'tingkat' => TingkatSimkatmawa::options(),
                'peringkat' => PeringkatPrestasi::options(),
                'bentuk_kepesertaan' => BentukKepesertaan::options(),
                'moda_pelaksanaan' => ModaPelaksanaan::options(),
                'bidang_lomba' => BidangLomba::options(),
                'jenis_rekognisi' => JenisRekognisi::options(),
            ],
            'dosen' => Dosen::query()->aktif()->orderBy('nama')->get(['id', 'uuid', 'nama', 'nidn', 'nuptk', 'gelar_depan', 'gelar_belakang'])
                ->mapWithKeys(fn (Dosen $d): array => [$d->uuid => $d->namaLengkap().($d->nidn ? ' · '.$d->nidn : '')])
                ->all(),
        ]);
    }

    public function perbarui(DataSimkatmawaRequest $request, PoinMahasiswa $poin): RedirectResponse
    {
        $this->poin->lengkapiSimkatmawa($poin, DataSimkatmawa::fromRequest($request));

        return redirect()
            ->route('admin.poin-kemahasiswaan.simkatmawa', $poin)
            ->with('sukses', __('simkatmawa.lengkapi.tersimpan'));
    }

    public function kategori(KategoriSimkatmawaRequest $request, PoinKategori $kategori): RedirectResponse
    {
        $this->poin->tetapkanKategoriSimkatmawa($kategori, $request->kategori());

        return back()->with('sukses', __('simkatmawa.katalog.tersimpan', [
            'nama' => $kategori->nama,
            'kategori' => $request->kategori()?->label() ?? __('simkatmawa.katalog.tidak_dilaporkan'),
        ]));
    }
}
