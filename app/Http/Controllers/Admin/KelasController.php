<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Services\Akademik\JadwalService;
use App\Services\Akademik\KelasKuliahService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Opening classes and putting them on the timetable.
 *
 * The screen leads with what is missing rather than what exists: classes with
 * no lecturer and classes with no slot are the two states that stop a semester
 * from starting, and both are invisible on an ordinary list.
 */
class KelasController extends Controller
{
    /** 1 = Senin. Sunday is offered because some campuses run weekend classes. */
    private const HARI = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    public function __construct(
        private readonly KelasKuliahService $kelas,
        private readonly JadwalService $jadwal,
    ) {}

    public function index(Request $request): View
    {
        $this->izin('kelas.view');

        $term = $request->filled('term')
            ? TahunAkademik::find($request->integer('term'))
            : Portal::term();

        $daftar = KelasKuliah::query()
            ->with(['mataKuliah', 'prodi', 'dosen', 'jadwal.ruang'])
            ->withCount('krsDetail as terdaftar')
            ->when($term !== null, fn ($q) => $q->where('tahun_akademik_id', $term->id))
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi_id', $request->integer('prodi')))
            ->when($request->boolean('tanpa_dosen'), fn ($q) => $q->whereDoesntHave('dosen'))
            ->when($request->boolean('tanpa_jadwal'), fn ($q) => $q->whereDoesntHave('jadwal'))
            ->cari($request->string('cari'), ['mataKuliah.nama', 'mataKuliah.kode'])
            ->join('mata_kuliah', 'mata_kuliah.id', '=', 'kelas_kuliah.mata_kuliah_id')
            ->orderBy('mata_kuliah.kode')
            ->orderBy('kelas_kuliah.kode')
            ->select('kelas_kuliah.*')
            ->paginate(30)
            ->withQueryString();

        return view('admin.kelas', [
            'judul' => 'Jadwal & Kelas',
            'konteks' => $term?->nama ?? 'Belum ada semester aktif',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Jadwal & Kelas'],
            'daftar' => $daftar,
            'term' => $term,
            'daftarTerm' => TahunAkademik::terbaru()->get(['id', 'kode', 'nama']),
            'daftarProdi' => Prodi::orderBy('nama')->get(['id', 'nama', 'jenjang']),
            'daftarRuang' => Ruang::with('gedung')->where('is_active', true)->orderBy('kode')->get(),
            'daftarDosen' => Dosen::where('is_active', true)->orderBy('nama')
                ->get(['id', 'nama', 'gelar_depan', 'gelar_belakang', 'is_praktisi', 'praktisi_instansi']),
            'daftarMk' => MataKuliah::where('is_active', true)->orderBy('kode')
                ->get(['id', 'kode', 'nama', 'sks', 'prodi_id']),
            'hariPilihan' => self::HARI,
            'filter' => $request->only(['term', 'prodi', 'cari', 'tanpa_dosen', 'tanpa_jadwal']),

            // The two states that quietly stop a semester from starting.
            'belumSiap' => $term === null ? ['dosen' => 0, 'jadwal' => 0] : [
                'dosen' => KelasKuliah::where('tahun_akademik_id', $term->id)->whereDoesntHave('dosen')->count(),
                'jadwal' => KelasKuliah::where('tahun_akademik_id', $term->id)->whereDoesntHave('jadwal')->count(),
            ],
        ]);
    }

    public function buka(Request $request): RedirectResponse
    {
        $this->izin('kelas.manage');

        $validated = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'mata_kuliah_id' => ['required', 'integer', Rule::exists('mata_kuliah', 'id')],
            'jumlah_kelas' => ['required', 'integer', 'min:1', 'max:26'],
            'kuota' => ['required', 'integer', 'min:1', 'max:500'],
            'mode' => ['required', Rule::in(['tatap_muka', 'daring', 'hybrid'])],
            'is_case_method' => ['sometimes', 'boolean'],
            'is_team_based_project' => ['sometimes', 'boolean'],
        ]);

        $dibuat = $this->kelas->bukaParalel(
            TahunAkademik::findOrFail($validated['tahun_akademik_id']),
            MataKuliah::findOrFail($validated['mata_kuliah_id']),
            (int) $validated['jumlah_kelas'],
            (int) $validated['kuota'],
            $validated,
        );

        return back()->with('sukses', sprintf(
            '%d kelas dibuka: %s. Tugaskan dosen dan jadwalnya sebelum masa KRS dibuka.',
            $dibuat->count(),
            $dibuat->pluck('kode')->implode(', '),
        ));
    }

    public function perbarui(Request $request, KelasKuliah $kelas): RedirectResponse
    {
        $this->izin('kelas.manage');

        $validated = $request->validate([
            'kuota' => ['required', 'integer', 'min:1', 'max:500'],
            'mode' => ['required', Rule::in(['tatap_muka', 'daring', 'hybrid'])],
            'is_case_method' => ['sometimes', 'boolean'],
            'is_team_based_project' => ['sometimes', 'boolean'],
        ]);

        $this->kelas->perbarui($kelas, $validated);

        return back()->with('sukses', "Kelas {$kelas->namaLengkap()} diperbarui.");
    }

    public function tutup(KelasKuliah $kelas): RedirectResponse
    {
        $this->izin('kelas.manage');

        $nama = $kelas->namaLengkap();
        $this->kelas->tutup($kelas);

        return back()->with('sukses', "{$nama} dihapus.");
    }

    public function tugaskanDosen(Request $request, KelasKuliah $kelas): RedirectResponse
    {
        $this->izin('kelas.manage');

        $validated = $request->validate([
            'dosen_id' => ['required', 'integer', Rule::exists('dosen', 'id')],
            'peran' => ['required', Rule::in(['pengampu', 'pendamping', 'praktisi'])],
        ]);

        $dosen = Dosen::findOrFail($validated['dosen_id']);

        $this->kelas->tugaskanDosen($kelas, $dosen, $validated['peran']);

        return back()->with('sukses', "{$dosen->nama} ditugaskan pada {$kelas->namaLengkap()}.");
    }

    public function lepasDosen(KelasKuliah $kelas, Dosen $dosen): RedirectResponse
    {
        $this->izin('kelas.manage');

        $this->kelas->lepasDosen($kelas, $dosen);

        return back()->with('sukses', "{$dosen->nama} dilepas dari kelas.");
    }

    public function jadwalkan(Request $request, KelasKuliah $kelas): RedirectResponse
    {
        $this->izin('kelas.manage');

        $validated = $request->validate([
            'hari' => ['required', 'integer', 'min:1', 'max:7'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruang_id' => ['nullable', 'integer', Rule::exists('ruang', 'id')],
        ], [
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $hasil = $this->jadwal->jadwalkan(
            $kelas,
            (int) $validated['hari'],
            $validated['jam_mulai'],
            $validated['jam_selesai'],
            $validated['ruang_id'] ?? null,
        );

        $pesan = sprintf(
            'Jadwal %s %s–%s disimpan.',
            self::HARI[$validated['hari']],
            $validated['jam_mulai'],
            $validated['jam_selesai'],
        );

        // Warnings are shown, not swallowed: the registrar decided to accept
        // them, but the decision should stay visible.
        if ($hasil['peringatan']->isNotEmpty()) {
            return back()->with('peringatan', $pesan.' Perhatikan: '
                .$hasil['peringatan']->pluck('pesan')->implode('; ').'.');
        }

        return back()->with('sukses', $pesan);
    }

    public function hapusJadwal(KelasKuliah $kelas, JadwalKuliah $jadwal): RedirectResponse
    {
        $this->izin('kelas.manage');

        // Both parameters are resolved independently; without this a slot from
        // another class could be deleted through this class's URL.
        abort_unless($jadwal->kelas_kuliah_id === $kelas->id, 404);

        $this->jadwal->hapus($jadwal);

        return back()->with('sukses', 'Slot jadwal dihapus.');
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
