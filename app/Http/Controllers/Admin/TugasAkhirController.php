<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\HasilUjian;
use App\Enums\JenisUjian;
use App\Enums\PeranPembimbing;
use App\Enums\PeranPenguji;
use App\Enums\TugasAkhirStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Sdm\Dosen;
use App\Models\TugasAkhir\Pembimbing;
use App\Models\TugasAkhir\TugasAkhir;
use App\Models\TugasAkhir\Ujian;
use App\Services\TugasAkhir\TugasAkhirService;
use App\Services\TugasAkhir\UjianService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The department's view of final projects: approving titles, allocating
 * supervisors, and scheduling examinations.
 *
 * The list leads with titles awaiting a decision and projects that are running
 * without a supervisor, because those two are the states nobody notices. A
 * project silently unsupervised for a semester costs a student a semester.
 */
class TugasAkhirController extends Controller
{
    public function __construct(
        private readonly TugasAkhirService $tugasAkhir,
        private readonly UjianService $ujian,
    ) {}

    public function index(Request $request): View
    {
        $this->izin('tugas_akhir.view');

        $daftar = TugasAkhir::query()
            ->with(['mahasiswa.prodi', 'pembimbing.dosen'])
            ->withCount(['bimbingan as bimbingan_disetujui_count' => fn ($q) => $q->where('disetujui', true)])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('prodi'), fn ($q) => $q->whereHas(
                'mahasiswa',
                fn ($m) => $m->where('prodi_id', $request->integer('prodi')),
            ))
            ->cari($request->string('cari'), ['judul', 'mahasiswa.nama', 'mahasiswa.nim'])
            ->orderByRaw($this->urutanPerhatian())
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.tugas-akhir.index', [
            'judul' => 'Tugas Akhir',
            'konteks' => $daftar->total().' tugas akhir',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Tugas Akhir'],
            'daftar' => $daftar,
            'statusPilihan' => TugasAkhirStatus::options(),
            'daftarProdi' => Prodi::orderBy('nama')->get(['id', 'nama', 'jenjang']),
            'filter' => $request->only(['status', 'prodi', 'cari']),
            'rekap' => TugasAkhir::query()
                ->selectRaw('status, COUNT(*) as jumlah')
                ->groupBy('status')
                ->pluck('jumlah', 'status'),
        ]);
    }

    public function show(TugasAkhir $tugasAkhir): View
    {
        $this->izin('tugas_akhir.view');

        $tugasAkhir->load([
            'mahasiswa.prodi',
            'tahunAkademik',
            'penyetuju',
            'pembimbing.dosen',
            'bimbingan.dosen',
            'ujian.penguji.dosen',
            'ujian.ruang',
        ]);

        // Every eligible lecturer with their current supervision load, so the
        // person allocating can see who is already full before choosing rather
        // than after being refused.
        $dosen = Dosen::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'gelar_depan', 'gelar_belakang', 'nidn']);
        $beban = $this->tugasAkhir->bebanPembimbingBanyak($dosen->pluck('id')->all());

        return view('admin.tugas-akhir.show', [
            'judul' => $tugasAkhir->sebutan(),
            'konteks' => $tugasAkhir->mahasiswa->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Tugas Akhir' => route('admin.tugas-akhir'),
                $tugasAkhir->mahasiswa->nim,
            ],
            'ta' => $tugasAkhir,
            'daftarDosen' => $dosen,
            'bebanPembimbing' => $beban,
            'kuotaPembimbing' => (int) config('academic.tugas_akhir.kuota_pembimbing'),
            'minBimbingan' => (int) config('academic.tugas_akhir.min_bimbingan_sebelum_sidang'),
            'peranPembimbing' => PeranPembimbing::options(),
            'peranPenguji' => PeranPenguji::options(),
            'jenisUjian' => JenisUjian::options(),
            'hasilUjian' => HasilUjian::options(),
            'daftarRuang' => Ruang::orderBy('kode')->get(['id', 'kode', 'nama']),
        ]);
    }

    public function setujui(TugasAkhir $tugasAkhir): RedirectResponse
    {
        $this->izin('tugas_akhir.manage');

        $this->tugasAkhir->setujuiJudul($tugasAkhir, Portal::user());

        return back()->with('sukses', 'Judul disetujui. Tetapkan pembimbing agar bimbingan dapat dimulai.');
    }

    public function tolak(Request $request, TugasAkhir $tugasAkhir): RedirectResponse
    {
        $this->izin('tugas_akhir.manage');

        $data = $request->validate(
            ['alasan' => ['required', 'string', 'max:500']],
            ['alasan.required' => 'Alasan penolakan wajib diisi — mahasiswa membacanya untuk memperbaiki judul.'],
        );

        $this->tugasAkhir->tolakJudul($tugasAkhir, Portal::user(), $data['alasan']);

        return back()->with('sukses', 'Judul ditolak. Mahasiswa dapat mengajukan judul baru.');
    }

    public function tetapkanPembimbing(Request $request, TugasAkhir $tugasAkhir): RedirectResponse
    {
        $this->izin('tugas_akhir.manage');

        $data = $request->validate([
            'dosen_id' => ['required', 'integer', Rule::exists('dosen', 'id')],
            'peran' => ['required', Rule::enum(PeranPembimbing::class)],
        ]);

        $pembimbing = $this->tugasAkhir->tetapkanPembimbing(
            $tugasAkhir,
            Dosen::findOrFail($data['dosen_id']),
            PeranPembimbing::from($data['peran']),
        );

        return back()->with('sukses', $pembimbing->dosen->namaLengkap().' ditetapkan sebagai pembimbing.');
    }

    public function lepasPembimbing(Pembimbing $pembimbing): RedirectResponse
    {
        $this->izin('tugas_akhir.manage');

        $this->tugasAkhir->lepasPembimbing($pembimbing);

        return back()->with('sukses', 'Pembimbing dilepas.');
    }

    public function jadwalkanUjian(Request $request, TugasAkhir $tugasAkhir): RedirectResponse
    {
        $this->izin('tugas_akhir.manage');

        $data = $request->validate([
            'jenis' => ['required', Rule::enum(JenisUjian::class)],
            'tanggal' => ['required', 'date'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruang_id' => ['nullable', 'integer', Rule::exists('ruang', 'id')],
            'penguji' => ['required', 'array', 'min:1'],
            'penguji.*.dosen_id' => ['required', 'integer', Rule::exists('dosen', 'id')],
            'penguji.*.peran' => ['required', Rule::enum(PeranPenguji::class)],
        ]);

        $panel = array_map(static fn (array $kursi): array => [
            'dosen_id' => (int) $kursi['dosen_id'],
            'peran' => PeranPenguji::from($kursi['peran']),
        ], $data['penguji']);

        $hasil = $this->ujian->jadwalkan(
            $tugasAkhir,
            JenisUjian::from($data['jenis']),
            $data['tanggal'],
            $data['jam_mulai'],
            $data['jam_selesai'],
            $panel,
            $data['ruang_id'] ?? null,
        );

        return back()
            ->with('sukses', 'Ujian dijadwalkan.')
            ->with('peringatan', $hasil['peringatan']->pluck('pesan')->all());
    }

    public function catatHasil(Request $request, Ujian $ujian): RedirectResponse
    {
        $this->izin('tugas_akhir.manage');

        $data = $request->validate([
            'hasil' => ['required', Rule::enum(HasilUjian::class)],
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'batas_revisi' => ['nullable', 'date', 'after:today'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ujian->catatHasil(
            $ujian,
            HasilUjian::from($data['hasil']),
            isset($data['nilai']) ? (float) $data['nilai'] : null,
            $data['batas_revisi'] ?? null,
            $data['catatan'] ?? null,
        );

        return back()->with('sukses', 'Hasil ujian dicatat.');
    }

    public function batalkanUjian(Request $request, Ujian $ujian): RedirectResponse
    {
        $this->izin('tugas_akhir.manage');

        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        $this->ujian->batalkan($ujian, $data['alasan']);

        return back()->with('sukses', 'Ujian dibatalkan.');
    }

    public function selesaikan(TugasAkhir $tugasAkhir): RedirectResponse
    {
        $this->izin('tugas_akhir.manage');

        $this->tugasAkhir->selesaikan($tugasAkhir);

        return back()->with('sukses', 'Tugas akhir dinyatakan selesai dan siap masuk yudisium.');
    }

    public function batalkan(Request $request, TugasAkhir $tugasAkhir): RedirectResponse
    {
        $this->izin('tugas_akhir.manage');

        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        $this->tugasAkhir->batalkan($tugasAkhir, $data['alasan']);

        return redirect()
            ->route('admin.tugas-akhir')
            ->with('sukses', 'Tugas akhir dibatalkan.');
    }

    /**
     * Sorts the two states that go unnoticed to the top: titles waiting for a
     * decision, and approved work with nobody assigned to supervise it.
     *
     * Written as a CASE rather than several queries so pagination still counts
     * the whole set. Portable across MySQL and PostgreSQL — no vendor
     * functions, only literals the standard defines.
     */
    private function urutanPerhatian(): string
    {
        return sprintf(
            "CASE status WHEN '%s' THEN 0 WHEN '%s' THEN 1 WHEN '%s' THEN 2 ELSE 3 END",
            TugasAkhirStatus::Diajukan->value,
            TugasAkhirStatus::Disetujui->value,
            TugasAkhirStatus::Dibimbing->value,
        );
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
