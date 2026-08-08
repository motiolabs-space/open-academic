<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Master;

use App\Enums\EducationLevel;
use App\Models\Akademik\Fakultas;
use App\Models\Akademik\Prodi;
use App\Models\Sdm\Dosen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProdiController extends MasterController
{
    public function index(): View
    {
        $this->izin('master.view');

        $daftar = Prodi::query()
            ->with(['fakultas', 'kaprodi'])
            ->withCount(['mahasiswa', 'mataKuliah'])
            ->orderBy('kode')
            ->get();

        return view('admin.master.prodi', $this->halaman(
            'prodi',
            $daftar->count().' program studi',
            [
                'daftar' => $daftar,
                'daftarFakultas' => Fakultas::orderBy('nama')->get(['id', 'nama']),
                'daftarDosen' => Dosen::orderBy('nama')
                    ->get(['id', 'nama', 'gelar_depan', 'gelar_belakang']),
                'jenjangPilihan' => EducationLevel::cases(),
            ],
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->izin('master.manage');

        Prodi::create($this->validasi($request) + ['is_active' => true]);

        return back()->with('sukses', 'Program studi ditambahkan.');
    }

    public function update(Request $request, Prodi $prodi): RedirectResponse
    {
        $this->izin('master.manage');

        $prodi->update($this->validasi($request, $prodi));

        return back()->with('sukses', "Program studi {$prodi->nama} diperbarui.");
    }

    public function destroy(Prodi $prodi): RedirectResponse
    {
        $this->izin('master.manage');

        if ($prodi->mahasiswa()->exists()) {
            return back()->with(
                'galat',
                'Program studi ini masih memiliki mahasiswa dan tidak dapat dihapus. '
                    .'Nonaktifkan saja bila sudah tidak menerima mahasiswa baru.',
            );
        }

        $prodi->delete();

        return back()->with('sukses', 'Program studi dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Prodi $prodi = null): array
    {
        return $request->validate([
            'fakultas_id' => ['required', 'integer', Rule::exists('fakultas', 'id')],
            'kode' => [
                'required', 'string', 'max:16',
                Rule::unique('prodi', 'kode')->ignore($prodi?->id)->whereNull('deleted_at'),
            ],

            // id_sms PDDIKTI. Salah isi berarti seluruh pelaporan prodi ini
            // masuk ke prodi lain di Feeder.
            'kode_pddikti' => ['nullable', 'string', 'max:64'],

            'nama' => ['required', 'string', 'max:255'],
            'jenjang' => ['required', Rule::enum(EducationLevel::class)],
            'gelar_pendek' => ['nullable', 'string', 'max:32'],
            'gelar_panjang' => ['nullable', 'string', 'max:128'],
            'akreditasi' => ['nullable', 'string', 'max:16'],
            'sks_lulus' => ['required', 'integer', 'min:36', 'max:300'],
            'kaprodi_dosen_id' => ['nullable', 'integer', Rule::exists('dosen', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
