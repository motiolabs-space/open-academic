<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JenisKonversi;
use App\Http\Controllers\Controller;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\MataKuliah;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Akademik\KonversiService;
use App\Services\Berkas\BerkasService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Credit recognition, one student at a time.
 *
 * Scoped to a student rather than presented as a global queue, because the
 * decision is not per-row: an assessor is looking at one transfer transcript and
 * deciding which of its courses map onto this curriculum, with a ceiling that
 * applies across the whole set. A flat list of pending rows would hide the one
 * number that constrains them all.
 */
class KonversiController extends Controller
{
    public function __construct(
        private readonly KonversiService $konversi,
        private readonly BerkasService $berkas,
    ) {}

    public function index(Request $request): View
    {
        $this->izin('mahasiswa.view');

        $mahasiswa = $request->filled('mahasiswa')
            ? Mahasiswa::with('prodi')->where('uuid', $request->string('mahasiswa'))->first()
            : null;

        return view('admin.konversi', [
            'judul' => 'Konversi Kredit',
            'konteks' => $mahasiswa?->nama ?? 'Pilih mahasiswa',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Konversi Kredit'],
            'mahasiswa' => $mahasiswa,
            'cari' => $request->string('cari')->toString(),

            // Only shown once somebody is chosen: the list is thousands long and
            // nobody browses it.
            'hasilCari' => $request->filled('cari')
                ? Mahasiswa::query()->with('prodi')
                    ->cari($request->string('cari'), ['nama', 'nim'])
                    ->limit(20)->get()
                : collect(),

            'daftar' => $mahasiswa === null ? collect() : KonversiKredit::query()
                ->with(['mataKuliah', 'pemutus'])
                ->where('mahasiswa_id', $mahasiswa->id)
                ->orderByDesc('id')
                ->get(),

            'mataKuliah' => $mahasiswa === null ? collect() : MataKuliah::query()
                ->where('prodi_id', $mahasiswa->prodi_id)
                ->orderBy('kode')
                ->get(['id', 'kode', 'nama', 'sks']),

            'jenisPilihan' => JenisKonversi::options(),
            'batas' => $mahasiswa === null ? 0 : $this->konversi->batas($mahasiswa),
            'sudah' => $mahasiswa === null ? 0 : $this->konversi->sudahDiakui($mahasiswa),
            'sisa' => $mahasiswa === null ? 0 : $this->konversi->sisaKuota($mahasiswa),
        ]);
    }

    public function ajukan(Request $request, Mahasiswa $mahasiswa): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $data = $request->validate([
            'mata_kuliah_id' => ['required', 'integer', Rule::exists('mata_kuliah', 'id')],
            'jenis' => ['required', Rule::enum(JenisKonversi::class)],
            'asal_nama' => ['required', 'string', 'max:255'],
            'asal_institusi' => ['nullable', 'string', 'max:255'],
            'asal_sks' => ['nullable', 'integer', 'min:1', 'max:60'],
            'asal_nilai' => ['nullable', 'string', 'max:8'],
            // Optional at the proposal stage — the assessor often records what a
            // transcript says before the certified copy arrives. It is the
            // approval that should not happen without one.
            'berkas' => $this->berkas->aturan('dokumen', wajib: false),
        ]);

        $this->konversi->ajukan(
            $mahasiswa,
            MataKuliah::findOrFail($data['mata_kuliah_id']),
            JenisKonversi::from($data['jenis']),
            $data['asal_nama'],
            $data['asal_institusi'] ?? null,
            $data['asal_sks'] ?? null,
            $data['asal_nilai'] ?? null,
            $request->hasFile('berkas')
                ? $this->berkas->simpan($request->file('berkas'), 'konversi/'.$mahasiswa->uuid)
                : null,
        );

        return back()->with('sukses', 'Usulan konversi dicatat dan menunggu keputusan.');
    }

    public function setujui(Request $request, KonversiKredit $konversi): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $data = $request->validate([
            'sks_diakui' => ['required', 'integer', 'min:1', 'max:24'],
            'nilai_huruf' => ['nullable', 'string', 'max:2'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->konversi->setujui(
            $konversi,
            Portal::user(),
            (int) $data['sks_diakui'],
            $data['nilai_huruf'] ?: null,
            $data['catatan'] ?? null,
        );

        return back()->with('sukses', 'Konversi diakui dan dihitung ke total SKS mahasiswa.');
    }

    public function tolak(Request $request, KonversiKredit $konversi): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        $this->konversi->tolak($konversi, Portal::user(), $data['alasan']);

        return back()->with('sukses', 'Usulan ditolak.');
    }

    public function cabut(Request $request, KonversiKredit $konversi): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        $this->konversi->cabut($konversi, Portal::user(), $data['alasan']);

        return back()->with('sukses', 'Konversi dicabut dan mata kuliahnya dapat diambil kembali.');
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
