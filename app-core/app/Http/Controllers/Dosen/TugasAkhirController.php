<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Sdm\Dosen;
use App\Models\TugasAkhir\Bimbingan;
use App\Models\TugasAkhir\Penguji;
use App\Models\TugasAkhir\TugasAkhir;
use App\Services\TugasAkhir\BimbinganService;
use App\Services\TugasAkhir\UjianService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A lecturer's own supervision and examining.
 *
 * Every route here authorises against the specific project or panel seat, not
 * against the lecturer's role. The role says a lecturer may supervise; only the
 * pembimbing and penguji rows say *whose* work. Checking the role alone would
 * let any lecturer sign off any student's consultation log — which is exactly
 * the record a defence is scheduled against.
 */
class TugasAkhirController extends Controller
{
    public function __construct(
        private readonly BimbinganService $bimbingan,
        private readonly UjianService $ujian,
    ) {}

    public function index(): View
    {
        $dosen = $this->dosen();

        $dibimbing = TugasAkhir::query()
            ->with(['mahasiswa.prodi', 'pembimbing.dosen'])
            ->withCount([
                'bimbingan as menunggu_persetujuan_count' => fn ($q) => $q
                    ->where('dosen_id', $dosen->id)
                    ->where('disetujui', false),
                'bimbingan as bimbingan_disetujui_count' => fn ($q) => $q
                    ->where('disetujui', true),
            ])
            ->whereHas('pembimbing', fn ($q) => $q->where('dosen_id', $dosen->id))
            ->aktif()
            ->orderByDesc('id')
            ->get();

        // Panels this lecturer sits on that have not been concluded.
        $menguji = Penguji::query()
            ->with(['ujian.tugasAkhir.mahasiswa.prodi', 'ujian.ruang'])
            ->where('dosen_id', $dosen->id)
            ->whereHas('ujian', fn ($q) => $q->where('status', 'dijadwalkan'))
            ->get()
            ->sortBy(fn (Penguji $p) => $p->ujian->tanggal)
            ->values();

        return view('dosen.tugas-akhir.index', [
            'judul' => 'Tugas Akhir',
            'konteks' => $dibimbing->count().' bimbingan berjalan',
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), 'Tugas Akhir'],
            'dibimbing' => $dibimbing,
            'menguji' => $menguji,
            'minBimbingan' => (int) config('academic.tugas_akhir.min_bimbingan_sebelum_sidang'),
        ]);
    }

    public function show(TugasAkhir $tugasAkhir): View
    {
        $dosen = $this->dosen();

        $this->pastikanTerlibat($tugasAkhir, $dosen);

        $tugasAkhir->load([
            'mahasiswa.prodi',
            'pembimbing.dosen',
            'bimbingan.dosen',
            'ujian.penguji.dosen',
            'ujian.ruang',
        ]);

        return view('dosen.tugas-akhir.show', [
            'judul' => $tugasAkhir->sebutan(),
            'konteks' => $tugasAkhir->mahasiswa->nama,
            'breadcrumb' => [
                'Dasbor' => route('dosen.dashboard'),
                'Tugas Akhir' => route('dosen.tugas-akhir'),
                $tugasAkhir->mahasiswa->nim,
            ],
            'ta' => $tugasAkhir,
            'dosen' => $dosen,
            'minBimbingan' => (int) config('academic.tugas_akhir.min_bimbingan_sebelum_sidang'),
        ]);
    }

    /**
     * The supervisor's sign-off on one consultation.
     *
     * BimbinganService refuses when the lecturer is not the one named on the
     * row; this check exists so the refusal is a 403 rather than a rule
     * violation message, and so a lecturer cannot enumerate other students'
     * logs by trying identifiers.
     */
    public function setujuiBimbingan(Request $request, Bimbingan $bimbingan): RedirectResponse
    {
        $dosen = $this->dosen();

        abort_unless($bimbingan->dosen_id === $dosen->id, 403);

        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']]);

        $this->bimbingan->setujui($bimbingan, $dosen, $data['catatan'] ?? null);

        return back()->with('sukses', 'Bimbingan disetujui.');
    }

    public function batalkanPersetujuan(Bimbingan $bimbingan): RedirectResponse
    {
        $dosen = $this->dosen();

        abort_unless($bimbingan->dosen_id === $dosen->id, 403);

        $this->bimbingan->batalkanPersetujuan($bimbingan, $dosen);

        return back()->with('sukses', 'Persetujuan bimbingan dicabut.');
    }

    /** One panel member's own mark. Nobody may enter another examiner's. */
    public function nilaiUjian(Request $request, Penguji $penguji): RedirectResponse
    {
        $dosen = $this->dosen();

        abort_unless($penguji->dosen_id === $dosen->id, 403);

        $data = $request->validate([
            'nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ujian->nilaiPenguji($penguji, (float) $data['nilai'], $data['catatan'] ?? null);

        return back()->with('sukses', 'Nilai ujian disimpan.');
    }

    /**
     * A lecturer may open a project they supervise or examine — nothing else.
     *
     * Supervising and examining are separate grounds: an external examiner has
     * no supervision row but must still be able to read the work they are about
     * to assess.
     */
    private function pastikanTerlibat(TugasAkhir $ta, Dosen $dosen): void
    {
        $membimbing = $ta->pembimbing()->where('dosen_id', $dosen->id)->exists();

        $menguji = Penguji::query()
            ->where('dosen_id', $dosen->id)
            ->whereHas('ujian', fn ($q) => $q->where('tugas_akhir_id', $ta->id))
            ->exists();

        abort_unless($membimbing || $menguji, 403);
    }

    private function dosen(): Dosen
    {
        $dosen = Portal::user();

        abort_unless($dosen instanceof Dosen, 403);

        return $dosen;
    }
}
