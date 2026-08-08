<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Konsentrasi;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\PaketKuliah;
use App\Services\Akademik\PadananMataKuliah;
use App\Services\Akademik\PaketKuliahService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The three things a curriculum needs once it has been replaced at least once:
 * equivalences, tracks, and packaged semesters.
 *
 * One screen, because they are the same conversation. A curriculum revision
 * produces all three at the same meeting — what replaces what, which tracks
 * exist, and what each cohort is issued.
 */
class KurikulumLanjutanController extends Controller
{
    public function __construct(
        private readonly PadananMataKuliah $padanan,
        private readonly PaketKuliahService $paket,
    ) {}

    public function index(Request $request): View
    {
        $this->izin('master.view');

        $kurikulum = $request->filled('kurikulum')
            ? Kurikulum::where('uuid', $request->string('kurikulum'))->firstOrFail()
            : Kurikulum::query()->orderByDesc('tahun_mulai')->first();

        $prodiId = $kurikulum?->prodi_id;

        return view('admin.kurikulum-lanjutan', [
            'judul' => 'Padanan, Konsentrasi & Paket',
            'konteks' => $kurikulum?->nama ?? 'Belum ada kurikulum',
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Master Akademik' => route('admin.master.index'),
                'Kurikulum Lanjutan',
            ],

            'kurikulum' => $kurikulum,
            'semua' => Kurikulum::with('prodi')->orderByDesc('tahun_mulai')->get(),

            'padanan' => $prodiId === null ? collect() : $this->padanan->daftar($prodiId),
            'konsentrasi' => $kurikulum?->konsentrasi()->withCount('mahasiswa')->get() ?? collect(),
            'paket' => $kurikulum === null ? collect() : $this->paket->daftar($kurikulum->id),

            'mataKuliahPilihan' => $prodiId === null ? [] : MataKuliah::query()
                ->where('prodi_id', $prodiId)
                ->orderBy('kode')
                ->get()
                ->mapWithKeys(fn (MataKuliah $m): array => [$m->id => $m->kode.' — '.$m->nama])
                ->all(),

            'konsentrasiPilihan' => $kurikulum?->konsentrasi()->pluck('nama', 'id')->all() ?? [],
            'bolehKelola' => Portal::user()?->hasPermissionTo('master.manage', 'staff') ?? false,
        ]);
    }

    public function simpanPadanan(Request $request): RedirectResponse
    {
        $this->izin('master.manage');

        $data = $request->validate([
            'mata_kuliah_id' => ['required', 'integer', Rule::exists('mata_kuliah', 'id')],
            'diakui_sebagai_id' => ['required', 'integer', Rule::exists('mata_kuliah', 'id')],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->padanan->tetapkan(
            MataKuliah::findOrFail($data['mata_kuliah_id']),
            MataKuliah::findOrFail($data['diakui_sebagai_id']),
            Portal::user(),
            $data['catatan'] ?? null,
        );

        return back()->with('sukses',
            'Padanan dicatat. Mahasiswa yang sudah lulus mata kuliah asal kini terhitung '
            .'sudah memenuhi mata kuliah penggantinya — termasuk sebagai prasyarat.');
    }

    public function hapusPadanan(Request $request): RedirectResponse
    {
        $this->izin('master.manage');

        $data = $request->validate([
            'mata_kuliah_id' => ['required', 'integer'],
            'diakui_sebagai_id' => ['required', 'integer'],
        ]);

        $this->padanan->hapus((int) $data['mata_kuliah_id'], (int) $data['diakui_sebagai_id']);

        return back()->with('sukses', 'Padanan dihapus.');
    }

    public function simpanKonsentrasi(Request $request, Kurikulum $kurikulum): RedirectResponse
    {
        $this->izin('master.manage');

        $data = $request->validate([
            'kode' => ['required', 'string', 'max:16'],
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'sks_wajib' => ['nullable', 'integer', 'min:0', 'max:200'],
        ]);

        Konsentrasi::create(['kurikulum_id' => $kurikulum->id] + $data);

        return back()->with('sukses', 'Konsentrasi ditambahkan.');
    }

    public function simpanPaket(Request $request, Kurikulum $kurikulum): RedirectResponse
    {
        $this->izin('master.manage');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'semester_ke' => ['required', 'integer', 'min:1', 'max:14'],
            'konsentrasi_id' => ['nullable', 'integer', Rule::exists('konsentrasi', 'id')],
            'mata_kuliah' => ['required', 'array', 'min:1'],
            'mata_kuliah.*' => ['integer', Rule::exists('mata_kuliah', 'id')],
        ]);

        DB::transaction(function () use ($kurikulum, $data): void {
            $paket = PaketKuliah::create([
                'kurikulum_id' => $kurikulum->id,
                'konsentrasi_id' => $data['konsentrasi_id'] ?? null,
                'semester_ke' => $data['semester_ke'],
                'nama' => $data['nama'],
            ]);

            $paket->mataKuliah()->sync($data['mata_kuliah']);
        });

        return back()->with('sukses', 'Paket kuliah tersimpan.');
    }

    /** Which courses a curriculum belongs to a track, set from this screen. */
    public function petakanKonsentrasi(Request $request, Kurikulum $kurikulum): RedirectResponse
    {
        $this->izin('master.manage');

        $data = $request->validate([
            'mata_kuliah_id' => ['required', 'integer'],
            'konsentrasi_id' => ['nullable', 'integer'],
        ]);

        DB::table('kurikulum_mata_kuliah')
            ->where('kurikulum_id', $kurikulum->id)
            ->where('mata_kuliah_id', $data['mata_kuliah_id'])
            ->update(['konsentrasi_id' => $data['konsentrasi_id'] ?: null]);

        return back()->with('sukses',
            $data['konsentrasi_id']
                ? 'Mata kuliah ditandai milik konsentrasi tersebut.'
                : 'Mata kuliah dikembalikan menjadi mata kuliah bersama.');
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
