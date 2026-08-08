<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Master;

use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Curriculum versions and their course lists.
 *
 * Curricula are versioned rather than edited in place: a student stays bound to
 * the version they entered under, and a graduation requirement that changed in
 * 2027 must not retroactively apply to a 2024 intake. That is the reason this
 * screen never offers to overwrite a version — it offers to create a new one.
 */
class KurikulumController extends MasterController
{
    public function index(Request $request): View
    {
        $this->izin('master.view');

        $daftar = Kurikulum::query()
            ->with('prodi')
            ->withCount(['mataKuliah', 'mahasiswa'])
            ->orderByDesc('tahun_mulai')
            ->get();

        $terpilih = $request->filled('kurikulum')
            ? Kurikulum::with(['prodi', 'mataKuliah'])->find($request->integer('kurikulum'))
            : null;

        return view('admin.master.kurikulum', $this->halaman(
            'kurikulum',
            $daftar->count().' versi kurikulum',
            [
                'daftar' => $daftar,
                'terpilih' => $terpilih,
                'daftarProdi' => Prodi::orderBy('nama')->get(['id', 'nama', 'jenjang']),
                'mkTersedia' => $terpilih
                    ? MataKuliah::where('prodi_id', $terpilih->prodi_id)
                        ->whereNotIn('id', $terpilih->mataKuliah->pluck('id'))
                        ->orderBy('kode')
                        ->get(['id', 'kode', 'nama', 'sks'])
                    : collect(),
            ],
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->izin('master.manage');

        $kurikulum = Kurikulum::create($this->validasi($request) + ['is_active' => false]);

        return back()->with('sukses', "Kurikulum {$kurikulum->nama} dibuat. Tambahkan mata kuliahnya sebelum diaktifkan.");
    }

    public function update(Request $request, Kurikulum $kurikulum): RedirectResponse
    {
        $this->izin('master.manage');

        $kurikulum->update($this->validasi($request, $kurikulum));

        return back()->with('sukses', "Kurikulum {$kurikulum->nama} diperbarui.");
    }

    /**
     * Marks one curriculum as the intake version for its programme.
     *
     * Scoped per programme, not globally: Informatika and Sistem Informasi run
     * different curricula at the same time, and there is no such thing as "the"
     * active curriculum for a campus.
     */
    public function aktifkan(Kurikulum $kurikulum): RedirectResponse
    {
        $this->izin('master.manage');

        if ($kurikulum->mataKuliah()->count() === 0) {
            return back()->with(
                'galat',
                'Kurikulum tanpa mata kuliah tidak dapat diaktifkan — mahasiswa baru tidak akan punya apa pun untuk diambil.',
            );
        }

        DB::transaction(function () use ($kurikulum): void {
            Kurikulum::query()
                ->where('prodi_id', $kurikulum->prodi_id)
                ->whereKeyNot($kurikulum->getKey())
                ->update(['is_active' => false]);

            $kurikulum->update(['is_active' => true]);
        });

        return back()->with('sukses', "{$kurikulum->nama} menjadi kurikulum aktif untuk {$kurikulum->prodi->nama}.");
    }

    public function tambahMk(Request $request, Kurikulum $kurikulum): RedirectResponse
    {
        $this->izin('master.manage');

        $validated = $request->validate([
            'mata_kuliah_id' => ['required', 'integer', Rule::exists('mata_kuliah', 'id')],
            'semester' => ['required', 'integer', 'min:1', 'max:14'],
            'jenis' => ['required', Rule::in(['wajib', 'pilihan', 'wajib_universitas'])],
        ]);

        $mk = MataKuliah::findOrFail($validated['mata_kuliah_id']);

        if ($mk->prodi_id !== $kurikulum->prodi_id) {
            return back()->with('galat', 'Mata kuliah tersebut milik program studi lain.');
        }

        $kurikulum->mataKuliah()->syncWithoutDetaching([
            $mk->id => ['semester' => $validated['semester'], 'jenis' => $validated['jenis']],
        ]);

        $this->hitungUlangSks($kurikulum);

        return back()->with('sukses', "{$mk->nama} ditambahkan ke kurikulum.");
    }

    public function hapusMk(Kurikulum $kurikulum, MataKuliah $mataKuliah): RedirectResponse
    {
        $this->izin('master.manage');

        $kurikulum->mataKuliah()->detach($mataKuliah->id);
        $this->hitungUlangSks($kurikulum);

        return back()->with('sukses', "{$mataKuliah->nama} dikeluarkan dari kurikulum.");
    }

    public function destroy(Kurikulum $kurikulum): RedirectResponse
    {
        $this->izin('master.manage');

        if (Mahasiswa::where('kurikulum_id', $kurikulum->id)->exists()) {
            return back()->with(
                'galat',
                'Kurikulum ini masih dipakai mahasiswa dan tidak boleh dihapus — riwayat studi mereka mengacu padanya.',
            );
        }

        $kurikulum->delete();

        return back()->with('sukses', 'Kurikulum dihapus.');
    }

    /** Keeps the credit totals in step with the course list. */
    private function hitungUlangSks(Kurikulum $kurikulum): void
    {
        $mk = $kurikulum->mataKuliah()->get();

        $kurikulum->update([
            'sks_wajib' => $mk->whereIn('pivot.jenis', ['wajib', 'wajib_universitas'])->sum('sks'),
            'sks_pilihan' => $mk->where('pivot.jenis', 'pilihan')->sum('sks'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Kurikulum $kurikulum = null): array
    {
        return $request->validate([
            'prodi_id' => ['required', 'integer', Rule::exists('prodi', 'id')],
            'kode' => [
                'required', 'string', 'max:32',
                Rule::unique('kurikulum', 'kode')
                    ->where('prodi_id', $request->integer('prodi_id'))
                    ->ignore($kurikulum?->id)
                    ->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'tahun_mulai' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tahun_selesai' => ['nullable', 'integer', 'min:2000', 'max:2100', 'gte:tahun_mulai'],
            'sks_lulus' => ['required', 'integer', 'min:36', 'max:300'],
        ]);
    }
}
