<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Models\Akademik\Prodi;
use App\Models\Sdm\Dosen;
use App\Services\Sdm\KepegawaianService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lecturer records — the people PDDIKTI knows by NIDN.
 *
 * Two columns here decide whether a whole semester can be reported: a lecturer
 * without an NIDN cannot be pushed to Feeder at all, and one flagged as a
 * practitioner is the evidence behind IKU 4. Both are surfaced on the list
 * rather than buried in an edit form, because "why was our report rejected" is
 * a question that gets asked the day before a deadline.
 */
class DosenController extends Controller
{
    public function __construct(private readonly KepegawaianService $kepegawaian) {}

    public function index(Request $request): View
    {
        $this->izin('dosen.view');

        $daftar = Dosen::query()
            ->with('prodi')
            ->withCount(['mahasiswaBimbingan', 'kelasKuliah'])
            ->cari($request->string('cari'), ['nama', 'nidn', 'email'])
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi_id', $request->integer('prodi')))
            ->when($request->boolean('tanpa_nidn'), fn ($q) => $q->whereNull('nidn'))
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        return view('admin.dosen', [
            'judul' => 'Kepegawaian Dosen',
            'konteks' => $daftar->total().' dosen',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Kepegawaian Dosen'],
            'daftar' => $daftar,
            'daftarProdi' => Prodi::orderBy('nama')->get(['id', 'nama', 'jenjang']),
            'jenjangPilihan' => EducationLevel::cases(),
            'genderPilihan' => Gender::cases(),
            'filter' => $request->only(['cari', 'prodi', 'tanpa_nidn']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->izin('dosen.manage');

        $hasil = $this->kepegawaian->buatDosen($this->validasi($request));

        // Shown once, in a flash message, and never stored anywhere readable.
        return back()->with('kata_sandi_baru', [
            'nama' => $hasil['dosen']->nama,
            'identitas' => $hasil['dosen']->email,
            'kata_sandi' => $hasil['kata_sandi'],
        ]);
    }

    public function update(Request $request, Dosen $dosen): RedirectResponse
    {
        $this->izin('dosen.manage');

        $dosen->update($this->validasi($request, $dosen));

        return back()->with('sukses', "Data {$dosen->nama} diperbarui.");
    }

    public function nonaktifkan(Dosen $dosen): RedirectResponse
    {
        $this->izin('dosen.manage');

        $this->kepegawaian->nonaktifkanDosen($dosen);

        return back()->with('sukses', "{$dosen->nama} dinonaktifkan.");
    }

    public function aktifkan(Dosen $dosen): RedirectResponse
    {
        $this->izin('dosen.manage');

        $this->kepegawaian->aktifkanDosen($dosen);

        return back()->with('sukses', "{$dosen->nama} diaktifkan kembali.");
    }

    public function resetKataSandi(Dosen $dosen): RedirectResponse
    {
        $this->izin('dosen.manage');

        $kataSandi = $this->kepegawaian->resetKataSandi($dosen);

        return back()->with('kata_sandi_baru', [
            'nama' => $dosen->nama,
            'identitas' => $dosen->nidn ?: $dosen->email,
            'kata_sandi' => $kataSandi,
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Dosen $dosen = null): array
    {
        return $request->validate([
            // Nullable on purpose: an industry practitioner brought in for
            // IKU 4 usually has none, and the Feeder pre-flight validator is
            // what refuses to push such a row — not this form.
            'nidn' => [
                'nullable', 'string', 'max:32',
                Rule::unique('dosen', 'nidn')->ignore($dosen?->id)->whereNull('deleted_at'),
            ],
            'nip' => ['nullable', 'string', 'max:32'],
            'nama' => ['required', 'string', 'max:255'],
            'gelar_depan' => ['nullable', 'string', 'max:32'],
            'gelar_belakang' => ['nullable', 'string', 'max:64'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('dosen', 'email')->ignore($dosen?->id)->whereNull('deleted_at'),
            ],
            'nik' => ['nullable', 'string', 'max:32'],
            'tempat_lahir' => ['nullable', 'string', 'max:64'],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'jenis_kelamin' => ['nullable', Rule::enum(Gender::class)],
            'telepon' => ['nullable', 'string', 'max:32'],
            'prodi_id' => ['nullable', 'integer', Rule::exists('prodi', 'id')],
            'jabatan_fungsional' => ['nullable', 'string', 'max:64'],
            'status_kepegawaian' => ['required', Rule::in(['tetap', 'tidak_tetap', 'luar_biasa'])],
            'pendidikan_tertinggi' => ['nullable', Rule::enum(EducationLevel::class)],
            'is_praktisi' => ['sometimes', 'boolean'],
            'praktisi_instansi' => ['nullable', 'string', 'max:255', 'required_if:is_praktisi,1'],
        ], [
            'praktisi_instansi.required_if' => 'Instansi asal wajib diisi untuk dosen praktisi — '
                .'itulah bukti yang dihitung pada IKU 4.',
        ]);
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
