<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicantStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Pmb\PmbBerkas;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Berkas\BerkasService;
use App\Services\Pmb\PmbService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admissions: waves, applicants, and the registration that turns one into a
 * student.
 *
 * The list leads with whether an applicant's data is complete enough to be
 * reported to PDDIKTI, because the alternative is discovering a missing NIK
 * months later when a whole intake fails validation at once.
 */
class PmbController extends Controller
{
    public function __construct(
        private readonly PmbService $pmb,
        private readonly BerkasService $berkas,
    ) {}

    /** Attaches a supporting document to an applicant. */
    public function unggahBerkas(Request $request, PmbPendaftar $pendaftar): RedirectResponse
    {
        $this->izin('pmb.manage');

        $validated = $request->validate([
            'jenis' => ['required', Rule::in(array_keys((array) config('berkas.pmb')))],
            'berkas' => $this->berkas->aturan('dokumen'),
        ], [
            'berkas.mimes' => 'Hanya PDF atau gambar (JPG/PNG) yang diterima.',
            'berkas.max' => 'Ukuran berkas melebihi batas '.config('berkas.maks_kb').' KB.',
        ]);

        $unggahan = $request->file('berkas');

        PmbBerkas::create([
            'pmb_pendaftar_id' => $pendaftar->id,
            'jenis' => $validated['jenis'],

            // The uploader's filename is kept as a label only; the stored name
            // is generated. See BerkasService.
            'nama_file' => $unggahan->getClientOriginalName(),
            'file_path' => $this->berkas->simpan($unggahan, 'pmb/'.$pendaftar->uuid),
            'is_verified' => false,
        ]);

        return back()->with('sukses', 'Berkas diunggah.');
    }

    public function hapusBerkas(PmbBerkas $berkas): RedirectResponse
    {
        $this->izin('pmb.manage');

        $this->berkas->hapus($berkas->file_path);
        $berkas->delete();

        return back()->with('sukses', 'Berkas dihapus.');
    }

    public function verifikasiBerkas(PmbBerkas $berkas): RedirectResponse
    {
        $this->izin('pmb.manage');

        $berkas->update(['is_verified' => !$berkas->is_verified]);

        return back()->with('sukses', $berkas->is_verified
            ? 'Berkas ditandai terverifikasi.'
            : 'Verifikasi berkas dicabut.');
    }

    public function index(Request $request): View
    {
        $this->izin('pmb.view');

        $gelombang = PmbGelombang::query()
            ->with('tahunAkademik')
            ->withCount('pendaftar')
            ->orderByDesc('tanggal_mulai')
            ->get();

        $pendaftar = PmbPendaftar::query()
            // `prodiPilihan2` ikut karena layarnya merendernya pada setiap baris
            // yang punya pilihan kedua. Tanpa ini penjaga N+1 melempar
            // LazyLoadingViolationException dan halamannya 500 di luar produksi;
            // di produksi ia tidak melempar, hanya menambah satu kueri per baris.
            ->with(['gelombang', 'prodiPilihan1', 'prodiPilihan2', 'prodiDiterima', 'mahasiswa', 'berkas'])
            ->when($request->filled('gelombang'), fn ($q) => $q->where('pmb_gelombang_id', $request->integer('gelombang')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->cari($request->string('cari'), ['nama', 'nomor_pendaftaran', 'email'])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.pmb', [
            'judul' => 'Penerimaan Mahasiswa Baru',
            'konteks' => $pendaftar->total().' pendaftar',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'PMB'],
            'daftarGelombang' => $gelombang,
            'pendaftar' => $pendaftar,
            'daftarProdi' => Prodi::orderBy('nama')->get(['id', 'nama', 'jenjang']),
            'daftarTerm' => TahunAkademik::terbaru()->get(['id', 'kode', 'nama']),
            'statusPilihan' => ApplicantStatus::cases(),
            'jenisBerkas' => (array) config('berkas.pmb'),
            'termAktif' => Portal::term(),
            'filter' => $request->only(['gelombang', 'status', 'cari']),

            // Counted per wave so the wave list shows how far selection has got.
            'rekap' => PmbPendaftar::query()
                ->selectRaw('pmb_gelombang_id, status, COUNT(*) as jumlah')
                ->groupBy('pmb_gelombang_id', 'status')
                ->get()
                ->groupBy('pmb_gelombang_id'),
        ]);
    }

    public function storeGelombang(Request $request): RedirectResponse
    {
        $this->izin('pmb.manage');

        PmbGelombang::create($request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'kode' => ['required', 'string', 'max:32', Rule::unique('pmb_gelombang', 'kode')->whereNull('deleted_at')],
            'nama' => ['required', 'string', 'max:255'],
            'jalur' => ['required', Rule::in(['reguler', 'prestasi', 'rpl', 'transfer'])],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'biaya_pendaftaran' => ['required', 'integer', 'min:0'],
            'kuota' => ['nullable', 'integer', 'min:1'],
        ]) + ['is_active' => true]);

        return back()->with('sukses', 'Gelombang pendaftaran dibuka.');
    }

    public function luluskan(Request $request, PmbPendaftar $pendaftar): RedirectResponse
    {
        $this->izin('pmb.manage');

        $validated = $request->validate([
            'prodi_diterima_id' => ['required', 'integer', Rule::exists('prodi', 'id')],
            'nilai_seleksi' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->pmb->luluskan(
            $pendaftar,
            Prodi::findOrFail($validated['prodi_diterima_id']),
            isset($validated['nilai_seleksi']) ? (float) $validated['nilai_seleksi'] : null,
        );

        return back()->with('sukses', "{$pendaftar->nama} dinyatakan lulus seleksi.");
    }

    public function tidakLuluskan(Request $request, PmbPendaftar $pendaftar): RedirectResponse
    {
        $this->izin('pmb.manage');

        $validated = $request->validate(['catatan' => ['nullable', 'string', 'max:500']]);

        $this->pmb->tidakLuluskan($pendaftar, $validated['catatan'] ?? null);

        return back()->with('sukses', "{$pendaftar->nama} dinyatakan tidak lulus.");
    }

    /**
     * Registration — the point an applicant becomes a person with a transcript.
     */
    public function daftarUlang(Request $request, PmbPendaftar $pendaftar): RedirectResponse
    {
        $this->izin('pmb.manage');

        $validated = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
        ]);

        $hasil = $this->pmb->daftarUlang(
            $pendaftar,
            TahunAkademik::findOrFail($validated['tahun_akademik_id']),
        );

        $pesan = $hasil['tagihan'] === null
            ? 'Belum ada tarif yang berlaku, sehingga tagihan awal belum diterbitkan.'
            : 'Tagihan awal '.number_format((float) $hasil['tagihan']->total, 0, ',', '.').' rupiah diterbitkan.';

        return back()
            ->with('kata_sandi_baru', [
                'nama' => $hasil['mahasiswa']->nama,
                'identitas' => $hasil['mahasiswa']->nim,
                'kata_sandi' => $hasil['kata_sandi'],
            ])
            ->with('sukses', $pesan);
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
