<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\IntakePindahanRequest;
use App\Http\Requests\Kemahasiswaan\PerbaruiAsalRequest;
use App\Http\Requests\Kemahasiswaan\TranskripAsalRequest;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Berkas\BerkasService;
use App\Services\Kemahasiswaan\PindahanService;
use App\Support\Format;
use App\Support\Portal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Intake for transfer and RPL students, and the first step after it: their
 * origin transcript.
 *
 * Deciding each proposed conversion stays on the Konversi Kredit screen, which
 * already shows the one ceiling every decision is bounded by. This screen is
 * where the student is admitted and their transcript is entered; that screen
 * is where an assessor rules on it.
 */
class PindahanController extends Controller
{
    public function __construct(
        private readonly PindahanService $pindahan,
        private readonly BerkasService $berkas,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Mahasiswa::class);

        $daftar = $this->pindahan->daftar($request->string('cari')->toString());

        return view('admin.pindahan.index', [
            'judul' => __('pindahan.judul'),
            'konteks' => __('pindahan.konteks', ['jumlah' => $daftar->total()]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('pindahan.judul')],
            'daftar' => $daftar,
            'menunggu' => $this->pindahan->menungguRegistrasi(),
            'cari' => $request->string('cari')->toString(),
        ]);
    }

    public function create(Request $request): View
    {
        $pendaftar = $request->filled('pendaftar')
            ? PmbPendaftar::with(['gelombang', 'prodiDiterima'])
                ->where('uuid', $request->string('pendaftar'))
                ->whereNull('mahasiswa_id')
                ->firstOrFail()
            : null;

        $this->authorize($pendaftar === null ? 'create' : 'createFromPmb', Mahasiswa::class);

        return view('admin.pindahan.create', [
            'judul' => __('pindahan.judul_baru'),
            'konteks' => $pendaftar === null
                ? __('pindahan.konteks_baru_langsung')
                : __('pindahan.konteks_baru_pmb', ['nomor' => $pendaftar->nomor_pendaftaran]),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('pindahan.judul') => route('admin.pindahan'),
                __('pindahan.judul_baru'),
            ],
            'pendaftar' => $pendaftar,
            'jenisPilihan' => PindahanService::jenisPilihan(),
            'jenisBawaan' => $pendaftar?->gelombang?->jenisDaftarBawaan()?->value,
            'daftarProdi' => Prodi::orderBy('nama')->pluck('nama', 'id'),
            'daftarTerm' => TahunAkademik::terbaru()->pluck('nama', 'id'),
            'termBawaan' => $pendaftar?->gelombang?->tahun_akademik_id ?? Portal::term()?->id,
            'genderPilihan' => Gender::options(),
            'belumDipetakan' => $this->pindahan->jenisBelumDipetakan(),
        ]);
    }

    public function store(IntakePindahanRequest $request): RedirectResponse
    {
        $term = TahunAkademik::findOrFail($request->integer('tahun_akademik_id'));
        $pendaftar = $request->pendaftar();

        $hasil = $pendaftar !== null
            ? $this->pindahan->daftarkanDariPmb($pendaftar, $term, $request->asal())
            : $this->pindahan->daftarkanLangsung(
                $request->identitas(),
                Prodi::findOrFail($request->integer('prodi_id')),
                $term,
                $request->asal(),
            );

        $mahasiswa = $hasil['mahasiswa'];

        return redirect()
            ->route('admin.pindahan.show', $mahasiswa)
            ->with('kata_sandi_baru', [
                'nama' => $mahasiswa->nama,
                'identitas' => $mahasiswa->nim,
                'kata_sandi' => $hasil['kata_sandi'],
            ])
            ->with('sukses', trim(
                __('pindahan.sukses.intake', ['nama' => $mahasiswa->nama, 'nim' => $mahasiswa->nim]).' '
                .($hasil['tagihan'] === null
                    ? __('pindahan.sukses.tanpa_tagihan')
                    : __('pindahan.sukses.tagihan', ['nominal' => Format::rupiah((int) $hasil['tagihan']->total)])),
            ));
    }

    public function show(Mahasiswa $mahasiswa): View
    {
        $this->authorize('view', $mahasiswa);

        $mahasiswa->load('prodi');

        return view('admin.pindahan.show', [
            'judul' => $mahasiswa->nama,
            'konteks' => $mahasiswa->nim.' · '.$mahasiswa->prodi->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('pindahan.judul') => route('admin.pindahan'),
                $mahasiswa->nim,
            ],
            'mahasiswa' => $mahasiswa,
            'ringkasan' => $this->pindahan->ringkasan($mahasiswa),
            'konversi' => $mahasiswa->konversiKredit()->with('mataKuliah')->orderByDesc('id')->get(),
            'mataKuliah' => MataKuliah::query()
                ->where('prodi_id', $mahasiswa->prodi_id)
                ->orderBy('kode')
                ->get(['id', 'kode', 'nama', 'sks']),
        ]);
    }

    public function update(PerbaruiAsalRequest $request, Mahasiswa $mahasiswa): RedirectResponse
    {
        $this->pindahan->perbaruiAsal($mahasiswa, $request->toDto());

        return back()->with('sukses', __('pindahan.sukses.asal'));
    }

    public function transkrip(TranskripAsalRequest $request, Mahasiswa $mahasiswa): RedirectResponse
    {
        $usulan = $this->pindahan->ajukanTranskrip(
            $mahasiswa,
            $request->toDto(),
            $request->hasFile('berkas')
                ? $this->berkas->simpan($request->file('berkas'), 'konversi/'.$mahasiswa->uuid)
                : null,
        );

        return back()->with('sukses', __('pindahan.sukses.transkrip', ['jumlah' => $usulan->count()]));
    }

    /** Name suggestions for the origin fields, as the operator types. */
    public function referensi(Request $request): JsonResponse
    {
        $this->authorize('create', Mahasiswa::class);

        return response()->json($this->pindahan->sarankanAsal(
            $request->string('tipe')->toString(),
            $request->string('q')->toString(),
        ));
    }
}
