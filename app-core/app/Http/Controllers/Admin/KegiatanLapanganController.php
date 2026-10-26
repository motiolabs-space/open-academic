<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Kemahasiswaan\KegiatanLapanganData;
use App\DTOs\Kemahasiswaan\KelompokKegiatanData;
use App\DTOs\Kemahasiswaan\LokasiKegiatanData;
use App\Enums\JenisLokasiKegiatan;
use App\Enums\StudentActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\KegiatanLapanganRequest;
use App\Http\Requests\Kemahasiswaan\KelompokKegiatanRequest;
use App\Http\Requests\Kemahasiswaan\KonversiKegiatanRequest;
use App\Http\Requests\Kemahasiswaan\LokasiKegiatanRequest;
use App\Http\Requests\Kemahasiswaan\NilaiKegiatanRequest;
use App\Http\Requests\Kemahasiswaan\PesertaKegiatanRequest;
use App\Http\Requests\Kemahasiswaan\PindahAnggotaRequest;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Models\Kemahasiswaan\KegiatanLapanganKelompok;
use App\Models\Kemahasiswaan\KegiatanLapanganLokasi;
use App\Models\Sdm\Dosen;
use App\Services\Kemahasiswaan\KegiatanLapanganService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Running a KKN or PKL: locations, participants, groups, marks, conversion.
 *
 * One parameter per route, as everywhere in this portal: a route that takes a
 * group, or a participant, resolves its programme from the record itself.
 */
class KegiatanLapanganController extends Controller
{
    public function __construct(private readonly KegiatanLapanganService $kegiatan) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', KegiatanLapangan::class);

        $termId = $request->filled('term') ? $request->integer('term') : null;

        $daftar = KegiatanLapangan::query()
            ->with('tahunAkademik')
            ->withCount(['peserta', 'kelompok', 'lokasi'])
            ->when($termId !== null, fn ($q) => $q->where('tahun_akademik_id', $termId))
            ->orderByDesc('tanggal_mulai')
            ->paginate(25)
            ->withQueryString();

        return view('admin.kegiatan-lapangan.index', [
            'judul' => __('kegiatan_lapangan.judul'),
            'konteks' => __('kegiatan_lapangan.konteks', ['jumlah' => $daftar->total()]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('kegiatan_lapangan.judul')],
            'daftar' => $daftar,
            'termId' => $termId,
            'daftarTerm' => TahunAkademik::terbaru()->pluck('nama', 'id'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', KegiatanLapangan::class);

        return $this->formulir(null);
    }

    public function store(KegiatanLapanganRequest $request): RedirectResponse
    {
        $kegiatan = $this->kegiatan->simpan(KegiatanLapanganData::fromRequest($request));

        return redirect()->route('admin.kegiatan-lapangan.show', $kegiatan)
            ->with('sukses', __('kegiatan_lapangan.sukses.simpan', ['nama' => $kegiatan->nama]));
    }

    public function show(KegiatanLapangan $kegiatan): View
    {
        $this->authorize('view', $kegiatan);

        $kegiatan->load([
            'tahunAkademik',
            'lokasi' => fn ($q) => $q->withCount('kelompok'),
            'kelompok.lokasi',
            'kelompok.pembimbing',
            'kelompok.anggota' => fn ($q) => $q->with(['mahasiswa.prodi', 'krsKonversi'])->withCount('logbook'),
        ]);

        $lepas = $kegiatan->peserta()
            ->whereNull('kegiatan_lapangan_kelompok_id')
            ->with('mahasiswa.prodi')
            ->get()
            ->sortBy(fn (AktivitasMahasiswa $a): string => (string) $a->mahasiswa->nim)
            ->values();

        $semua = $kegiatan->kelompok->flatMap->anggota;

        return view('admin.kegiatan-lapangan.show', [
            'judul' => $kegiatan->nama,
            'konteks' => $kegiatan->jenis->label().' · '.$kegiatan->tahunAkademik->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('kegiatan_lapangan.judul') => route('admin.kegiatan-lapangan.index'),
                $kegiatan->nama,
            ],
            'kegiatan' => $kegiatan,
            'lepas' => $lepas,
            'ringkasan' => [
                'peserta' => $semua->count() + $lepas->count(),
                'kelompok' => $kegiatan->kelompok->count(),
                'dinilai' => $semua->whereNotNull('nilai_akhir')->count(),
                'dikonversi' => $semua->filter(fn (AktivitasMahasiswa $a): bool => $a->krsKonversi !== null)->count(),
            ],
            'daftarDosen' => Dosen::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'gelar_depan', 'gelar_belakang'])
                ->mapWithKeys(fn (Dosen $d): array => [$d->id => $d->namaLengkap()]),
            'daftarLokasi' => $kegiatan->lokasi->pluck('nama', 'id'),
            'jenisLokasi' => JenisLokasiKegiatan::options(),
            'pilihanKelompok' => $kegiatan->kelompok->pluck('nama', 'uuid'),
            'pilihanKelas' => KelasKuliah::query()
                ->where('tahun_akademik_id', $kegiatan->tahun_akademik_id)
                ->with(['mataKuliah:id,kode,nama', 'prodi:id,nama'])
                ->orderBy('prodi_id')
                ->get()
                ->mapWithKeys(fn (KelasKuliah $k): array => [
                    $k->uuid => $k->mataKuliah->kode.' · '.$k->mataKuliah->nama.' · '.$k->kode.' — '.$k->prodi->nama,
                ]),
        ]);
    }

    public function edit(KegiatanLapangan $kegiatan): View
    {
        $this->authorize('update', $kegiatan);

        return $this->formulir($kegiatan);
    }

    public function update(KegiatanLapanganRequest $request, KegiatanLapangan $kegiatan): RedirectResponse
    {
        $this->kegiatan->simpan(KegiatanLapanganData::fromRequest($request), $kegiatan);

        return redirect()->route('admin.kegiatan-lapangan.show', $kegiatan)
            ->with('sukses', __('kegiatan_lapangan.sukses.simpan', ['nama' => $kegiatan->nama]));
    }

    public function simpanLokasi(LokasiKegiatanRequest $request, KegiatanLapangan $kegiatan): RedirectResponse
    {
        $lokasi = $this->kegiatan->simpanLokasi($kegiatan, LokasiKegiatanData::fromRequest($request));

        return back()->with('sukses', __('kegiatan_lapangan.sukses.lokasi', ['nama' => $lokasi->nama]));
    }

    public function hapusLokasi(KegiatanLapanganLokasi $lokasi): RedirectResponse
    {
        $this->authorize('update', $lokasi->kegiatanLapangan);

        $this->kegiatan->hapusLokasi($lokasi);

        return back()->with('sukses', __('kegiatan_lapangan.sukses.lokasi_hapus'));
    }

    public function tambahPeserta(PesertaKegiatanRequest $request, KegiatanLapangan $kegiatan): RedirectResponse
    {
        $hasil = $this->kegiatan->daftarkanBanyak($kegiatan, $request->daftarNim());

        $pesan = back()->with('sukses', __('kegiatan_lapangan.sukses.peserta', ['jumlah' => $hasil['terdaftar']]));

        return $hasil['ditolak'] === []
            ? $pesan
            : $pesan->with('ditolak', $hasil['ditolak']);
    }

    public function keluarkanPeserta(AktivitasMahasiswa $aktivitas): RedirectResponse
    {
        $this->authorize('update', $this->kegiatanDari($aktivitas));

        $this->kegiatan->keluarkan($aktivitas);

        return back()->with('sukses', __('kegiatan_lapangan.sukses.keluar'));
    }

    public function pindahkan(PindahAnggotaRequest $request, AktivitasMahasiswa $aktivitas): RedirectResponse
    {
        $this->kegiatan->pindahkan($aktivitas, $request->tujuan());

        return back()->with('sukses', __('kegiatan_lapangan.sukses.pindah'));
    }

    public function bentukOtomatis(KegiatanLapangan $kegiatan): RedirectResponse
    {
        $this->authorize('update', $kegiatan);

        $kelompok = $this->kegiatan->bentukKelompokOtomatis($kegiatan);

        return back()->with('sukses', __('kegiatan_lapangan.sukses.bentuk', ['jumlah' => $kelompok->count()]));
    }

    public function buatKelompok(KelompokKegiatanRequest $request, KegiatanLapangan $kegiatan): RedirectResponse
    {
        $kelompok = $this->kegiatan->buatKelompok($kegiatan, KelompokKegiatanData::fromRequest($request));

        return back()->with('sukses', __('kegiatan_lapangan.sukses.kelompok', ['nama' => $kelompok->nama]));
    }

    public function aturKelompok(KelompokKegiatanRequest $request, KegiatanLapanganKelompok $kelompok): RedirectResponse
    {
        $this->kegiatan->aturKelompok($kelompok, KelompokKegiatanData::fromRequest($request));

        return back()->with('sukses', __('kegiatan_lapangan.sukses.kelompok', ['nama' => $kelompok->nama]));
    }

    public function hapusKelompok(KegiatanLapanganKelompok $kelompok): RedirectResponse
    {
        $this->authorize('update', $kelompok);

        $this->kegiatan->hapusKelompok($kelompok);

        return back()->with('sukses', __('kegiatan_lapangan.sukses.kelompok_hapus'));
    }

    public function nilai(NilaiKegiatanRequest $request, KegiatanLapanganKelompok $kelompok): RedirectResponse
    {
        $jumlah = $this->kegiatan->nilaiKelompok($kelompok, $request->isian());

        return back()->with('sukses', __('kegiatan_lapangan.sukses.nilai', ['jumlah' => $jumlah]));
    }

    public function konversi(KonversiKegiatanRequest $request, KegiatanLapangan $kegiatan): RedirectResponse
    {
        $hasil = $this->kegiatan->konversi($kegiatan, $request->kelas(), Portal::user());

        $pesan = back()->with('sukses', __('kegiatan_lapangan.sukses.konversi', ['jumlah' => $hasil->dikonversi]));

        return $hasil->dilewati === []
            ? $pesan
            : $pesan->with('ditolak', $hasil->dilewati);
    }

    private function formulir(?KegiatanLapangan $kegiatan): View
    {
        $judul = $kegiatan === null ? __('kegiatan_lapangan.aksi.baru') : __('kegiatan_lapangan.aksi.ubah');

        return view('admin.kegiatan-lapangan.form', [
            'judul' => $judul,
            'konteks' => $kegiatan?->nama ?? __('kegiatan_lapangan.judul'),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('kegiatan_lapangan.judul') => route('admin.kegiatan-lapangan.index'),
                $judul,
            ],
            'kegiatan' => $kegiatan,
            'daftarTerm' => TahunAkademik::terbaru()->pluck('nama', 'id'),
            'termAktif' => TahunAkademik::aktif()?->id,
            'jenisPilihan' => StudentActivityType::opsiKegiatanLapangan(),
            'bawaan' => [
                'min_sks' => (int) config('kegiatan_lapangan.min_sks'),
                'ukuran_kelompok' => (int) config('kegiatan_lapangan.kelompok.ukuran'),
                'campur_prodi' => (bool) config('kegiatan_lapangan.kelompok.campur_prodi'),
                'campur_jenis_kelamin' => (bool) config('kegiatan_lapangan.kelompok.campur_jenis_kelamin'),
                'bobot_mitra' => (int) config('kegiatan_lapangan.bobot_mitra'),
            ],
        ]);
    }

    private function kegiatanDari(AktivitasMahasiswa $aktivitas): KegiatanLapangan
    {
        $kegiatan = $aktivitas->kegiatanLapangan;

        abort_if($kegiatan === null, 404);

        return $kegiatan;
    }
}
