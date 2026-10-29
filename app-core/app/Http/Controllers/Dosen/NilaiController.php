<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Enums\JenisEvaluasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\RencanaEvaluasiRequest;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
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
            'komponen' => $komponen = $this->penilaian->komponen($kelas),
            'lembar' => $this->penilaian->lembarNilai($kelas),
            'periodeDibuka' => $kelas->tahunAkademik->penilaianDibuka(),

            // The plan as the editor needs it, plus which components already
            // hold scores: those may be reshaped but not removed.
            //
            // One EXISTS subquery for every component, not one query each.
            'rencana' => $komponen->loadExists('nilai')->map(fn (KomponenNilai $k): array => [
                'id' => $k->id,
                'nama' => $k->nama,
                'bobot' => (int) $k->bobot,
                'jenis' => $k->jenis_evaluasi?->value,
                'bernilai' => (bool) $k->nilai_exists,
            ])->values(),
            'jenisEvaluasi' => JenisEvaluasi::options(),
            'ambangKolaboratif' => JenisEvaluasi::AMBANG_KOLABORATIF_PERSEN,
        ]);
    }

    /**
     * Saves the class's evaluation plan.
     *
     * The reply carries the IKU 7 verdict when the class is flagged as
     * collaborative: a lecturer who just set the plan is the one person who
     * can still change it, and the only moment it is cheap to.
     */
    public function rencana(RencanaEvaluasiRequest $request, KelasKuliah $kelas): RedirectResponse
    {
        $this->authorize('grade', $kelas);

        $komponen = $this->penilaian->simpanKomponen($kelas, $request->rencana());

        $pesan = 'Rencana evaluasi tersimpan: '.$komponen->count().' komponen.';

        if ($kelas->kelasKolaboratif()) {
            $kolaboratif = (int) $komponen
                ->filter(fn (KomponenNilai $k): bool => $k->jenis_evaluasi?->menghitungKolaboratif() ?? false)
                ->sum('bobot');

            if ($kolaboratif < JenisEvaluasi::AMBANG_KOLABORATIF_PERSEN) {
                return back()->with('peringatan', __('iku.rencana.kurang', [
                    'pesan' => $pesan,
                    'bobot' => $kolaboratif,
                    'ambang' => JenisEvaluasi::AMBANG_KOLABORATIF_PERSEN,
                ]));
            }

            $pesan .= __('iku.rencana.memenuhi', ['bobot' => $kolaboratif]);
        }

        return back()->with('sukses', $pesan);
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
