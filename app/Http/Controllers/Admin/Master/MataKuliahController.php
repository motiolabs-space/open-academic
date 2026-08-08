<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Master;

use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The course catalogue and its prerequisite graph.
 *
 * Credits are split into theory, practice and field practice because PDDIKTI
 * reports them apart; the total is stored alongside so ordinary queries do not
 * have to add three columns every time.
 */
class MataKuliahController extends MasterController
{
    public function index(Request $request): View
    {
        $this->izin('master.view');

        $daftar = MataKuliah::query()
            ->with(['prodi', 'prasyarat'])
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi_id', $request->integer('prodi')))
            ->cari($request->string('cari'), ['nama', 'kode'])
            ->orderBy('kode')
            ->paginate(25)
            ->withQueryString();

        return view('admin.master.mata-kuliah', $this->halaman(
            'mata-kuliah',
            $daftar->total().' mata kuliah',
            [
                'daftar' => $daftar,
                'daftarProdi' => Prodi::orderBy('nama')->get(['id', 'nama', 'jenjang']),
                'filter' => $request->only(['prodi', 'cari']),
            ],
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->izin('master.manage');

        $data = $this->validasi($request);
        $mk = MataKuliah::create($data + ['sks' => $this->totalSks($data), 'is_active' => true]);

        return back()->with('sukses', "Mata kuliah {$mk->nama} ditambahkan.");
    }

    public function update(Request $request, MataKuliah $mataKuliah): RedirectResponse
    {
        $this->izin('master.manage');

        $data = $this->validasi($request, $mataKuliah);
        $mataKuliah->update($data + ['sks' => $this->totalSks($data)]);

        return back()->with('sukses', "Mata kuliah {$mataKuliah->nama} diperbarui.");
    }

    public function destroy(MataKuliah $mataKuliah): RedirectResponse
    {
        $this->izin('master.manage');

        if ($mataKuliah->kelasKuliah()->exists()) {
            return back()->with(
                'galat',
                'Mata kuliah ini sudah pernah dibuka sebagai kelas. Nonaktifkan saja — menghapusnya akan memutus riwayat nilai.',
            );
        }

        $mataKuliah->delete();

        return back()->with('sukses', 'Mata kuliah dihapus.');
    }

    /**
     * Adds a prerequisite, refusing the two ways this graph can break a campus.
     */
    public function tambahPrasyarat(Request $request, MataKuliah $mataKuliah): RedirectResponse
    {
        $this->izin('master.manage');

        $validated = $request->validate([
            'prasyarat_id' => ['required', 'integer', Rule::exists('mata_kuliah', 'id')],
            'jenis' => ['required', Rule::in(['prasyarat', 'bersamaan'])],
        ]);

        $prasyarat = MataKuliah::findOrFail($validated['prasyarat_id']);

        // A course that requires itself can never be taken by anyone.
        if ($prasyarat->id === $mataKuliah->id) {
            return back()->with('galat', 'Mata kuliah tidak dapat menjadi prasyarat bagi dirinya sendiri.');
        }

        // Neither can two courses that require each other, or any longer loop.
        if ($this->menimbulkanSiklus($mataKuliah, $prasyarat)) {
            return back()->with(
                'galat',
                "Menambahkan {$prasyarat->kode} sebagai prasyarat membentuk lingkaran — "
                    .'kedua mata kuliah akan saling menunggu dan tidak pernah bisa diambil.',
            );
        }

        $mataKuliah->prasyarat()->syncWithoutDetaching([
            $prasyarat->id => ['jenis' => $validated['jenis']],
        ]);

        return back()->with('sukses', "{$prasyarat->kode} ditetapkan sebagai prasyarat {$mataKuliah->kode}.");
    }

    public function hapusPrasyarat(MataKuliah $mataKuliah, MataKuliah $prasyarat): RedirectResponse
    {
        $this->izin('master.manage');

        $mataKuliah->prasyarat()->detach($prasyarat->id);

        return back()->with('sukses', 'Prasyarat dihapus.');
    }

    /**
     * Walks the prerequisite chain upwards looking for the course we are about
     * to add a prerequisite to.
     *
     * Depth-first with a visited set, so a graph that already contains a loop
     * from an older release does not hang the request.
     */
    private function menimbulkanSiklus(MataKuliah $mataKuliah, MataKuliah $calon): bool
    {
        $terlihat = [];
        $antrean = [$calon->id];

        while ($antrean !== []) {
            $id = array_pop($antrean);

            if ($id === $mataKuliah->id) {
                return true;
            }

            if (isset($terlihat[$id])) {
                continue;
            }

            $terlihat[$id] = true;

            foreach (MataKuliah::find($id)?->prasyarat()->pluck('mata_kuliah.id') ?? [] as $induk) {
                $antrean[] = (int) $induk;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $data */
    private function totalSks(array $data): int
    {
        return (int) $data['sks_teori'] + (int) $data['sks_praktik'] + (int) $data['sks_praktik_lapangan'];
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?MataKuliah $mataKuliah = null): array
    {
        return $request->validate([
            'prodi_id' => ['required', 'integer', Rule::exists('prodi', 'id')],
            'kode' => [
                'required', 'string', 'max:32',
                Rule::unique('mata_kuliah', 'kode')
                    ->where('prodi_id', $request->integer('prodi_id'))
                    ->ignore($mataKuliah?->id)
                    ->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'nama_en' => ['nullable', 'string', 'max:255'],
            'sks_teori' => ['required', 'integer', 'min:0', 'max:12'],
            'sks_praktik' => ['required', 'integer', 'min:0', 'max:12'],
            'sks_praktik_lapangan' => ['required', 'integer', 'min:0', 'max:12'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'sks_teori' => 'SKS teori',
            'sks_praktik' => 'SKS praktik',
            'sks_praktik_lapangan' => 'SKS praktik lapangan',
        ]);
    }
}
