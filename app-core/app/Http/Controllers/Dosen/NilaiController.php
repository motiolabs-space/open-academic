<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Nilai;
use App\Services\Akademik\PenilaianService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Grade entry, one class at a time.
 *
 * The sheet computes the weighted final in the browser as the lecturer types,
 * but the number that gets stored is always recomputed server-side — the live
 * figure is a convenience, never the source of the grade.
 */
class NilaiController extends Controller
{
    public function __construct(private readonly PenilaianService $penilaian) {}

    public function index(): View
    {
        $dosen = Portal::user();
        $term = Portal::term();

        $this->authorize('viewAny', Nilai::class);

        $kelas = KelasKuliah::query()
            ->with(['mataKuliah', 'jadwal'])
            ->withCount('krsDetail as jumlah_peserta')
            ->where('tahun_akademik_id', $term->id)
            ->whereHas('dosen', fn ($query) => $query->where('dosen.id', $dosen->id))
            ->orderBy('status_nilai')
            ->get();

        return view('dosen.nilai', [
            'judul' => 'Input Nilai',
            'konteks' => $term->nama.' · '.$kelas->where('status_nilai', '!=', 'final')->count().' kelas belum final',
            'breadcrumb' => ['Portal Dosen' => route('dosen.dashboard'), 'Input Nilai'],
            'daftar' => $kelas,
            'periodeDibuka' => $term->penilaianDibuka(),
        ]);
    }

    public function edit(KelasKuliah $kelas): View
    {
        $this->authorize('grade', $kelas);

        return view('dosen.nilai-kelas', [
            'judul' => $kelas->mataKuliah->nama,
            'konteks' => 'Kelas '.$kelas->kode.' · '.$kelas->sks.' SKS · '.$kelas->tahunAkademik->nama,
            'breadcrumb' => [
                'Portal Dosen' => route('dosen.dashboard'),
                'Input Nilai' => route('dosen.nilai'),
                $kelas->mataKuliah->nama,
            ],
            'kelas' => $kelas,
            'komponen' => $this->penilaian->komponen($kelas),
            'lembar' => $this->penilaian->lembarNilai($kelas),
            'periodeDibuka' => $kelas->tahunAkademik->penilaianDibuka(),
        ]);
    }

    public function simpan(Request $request, KelasKuliah $kelas): RedirectResponse
    {
        $this->authorize('grade', $kelas);

        $validated = $request->validate([
            'nilai' => ['required', 'array'],
            'nilai.*' => ['array'],
            'nilai.*.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'nilai.*.*.max' => 'Nilai komponen tidak boleh lebih dari 100.',
            'nilai.*.*.min' => 'Nilai komponen tidak boleh kurang dari 0.',
        ]);

        $jumlah = $this->penilaian->simpanNilai($kelas, $validated['nilai']);

        return back()->with('sukses', "Nilai {$jumlah} mahasiswa tersimpan.");
    }

    public function finalisasi(KelasKuliah $kelas): RedirectResponse
    {
        $this->authorize('grade', $kelas);

        $this->penilaian->finalisasi($kelas, Portal::user());

        return redirect()
            ->route('dosen.nilai')
            ->with('sukses', 'Nilai '.$kelas->mataKuliah->nama.' dikunci dan IPS mahasiswa diperbarui.');
    }
}
