<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\TugasAkhir\Bimbingan;
use App\Services\TugasAkhir\BimbinganService;
use App\Services\TugasAkhir\TugasAkhirService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The student's side: proposing a title, keeping the consultation log, and
 * seeing when the defence is.
 *
 * Everything here is scoped to the signed-in student's own project. The one
 * route that takes an identifier — deleting a consultation entry — checks that
 * the entry belongs to them, because "logged in as a student" is not the same
 * as "this student's log".
 */
class TugasAkhirController extends Controller
{
    public function __construct(
        private readonly TugasAkhirService $tugasAkhir,
        private readonly BimbinganService $bimbingan,
    ) {}

    public function index(): View
    {
        $mahasiswa = $this->mahasiswa();

        $ta = $mahasiswa->tugasAkhir()
            ->with(['pembimbing.dosen', 'bimbingan.dosen', 'ujian.penguji.dosen', 'ujian.ruang'])
            ->first();

        $minSks = (int) config('academic.tugas_akhir.min_sks_pengajuan');

        return view('mahasiswa.tugas-akhir', [
            'judul' => $mahasiswa->prodi->jenjang->sebutanTugasAkhir(),
            'konteks' => $ta?->status->label() ?? 'Belum diajukan',
            'breadcrumb' => ['Portal' => route('mahasiswa.dashboard'), 'Tugas Akhir'],
            'mahasiswa' => $mahasiswa,
            'ta' => $ta,
            'minSks' => $minSks,
            'sksSaatIni' => $mahasiswa->sksKumulatif(),
            'minBimbingan' => (int) config('academic.tugas_akhir.min_bimbingan_sebelum_sidang'),

            // Only the supervisors actually assigned can be logged against.
            'pilihanPembimbing' => $ta?->pembimbing->pluck('dosen') ?? collect(),
        ]);
    }

    public function ajukan(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa();

        $data = $request->validate([
            'judul' => ['required', 'string', 'max:500'],
            'abstrak' => ['nullable', 'string', 'max:5000'],
            'bidang_kajian' => ['nullable', 'string', 'max:255'],
        ]);

        $term = Portal::term();

        abort_if($term === null, 503, 'Belum ada tahun akademik aktif.');

        $this->tugasAkhir->ajukan(
            $mahasiswa,
            $term,
            $data['judul'],
            $data['abstrak'] ?? null,
            $data['bidang_kajian'] ?? null,
        );

        return back()->with('sukses', 'Judul diajukan dan menunggu keputusan program studi.');
    }

    public function catatBimbingan(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa();
        $ta = $mahasiswa->tugasAkhirAktif;

        abort_if($ta === null, 404, 'Tidak ada tugas akhir yang sedang berjalan.');

        $data = $request->validate([
            // Constrained to this project's own supervisors, so the identifier
            // cannot be swapped for an unrelated lecturer.
            'dosen_id' => [
                'required',
                'integer',
                Rule::exists('tugas_akhir_pembimbing', 'dosen_id')
                    ->where('tugas_akhir_id', $ta->id),
            ],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'topik' => ['required', 'string', 'max:255'],
            'uraian' => ['nullable', 'string', 'max:5000'],
        ], [
            'dosen_id.exists' => 'Dosen yang dipilih bukan pembimbing tugas akhir Anda.',
        ]);

        $this->bimbingan->catat(
            $ta,
            Dosen::findOrFail($data['dosen_id']),
            $data['tanggal'],
            $data['topik'],
            $data['uraian'] ?? null,
        );

        return back()->with('sukses', 'Bimbingan dicatat dan menunggu persetujuan pembimbing.');
    }

    public function hapusBimbingan(Bimbingan $bimbingan): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa();

        // The entry must belong to this student's project. Without this, a
        // student could delete a classmate's consultation record — which would
        // silently push that classmate below the threshold for a defence.
        abort_unless($bimbingan->tugasAkhir->mahasiswa_id === $mahasiswa->id, 403);

        $this->bimbingan->hapus($bimbingan);

        return back()->with('sukses', 'Catatan bimbingan dihapus.');
    }

    private function mahasiswa(): Mahasiswa
    {
        $mahasiswa = Portal::user();

        abort_unless($mahasiswa instanceof Mahasiswa, 403);

        return $mahasiswa;
    }
}
