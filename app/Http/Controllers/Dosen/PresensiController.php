<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\Presensi;
use App\Services\Akademik\PresensiService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Attendance: the 16-meeting grid, and the QR session that lets a full lecture
 * hall mark itself present without the lecturer reading out fifty names.
 */
class PresensiController extends Controller
{
    public function __construct(private readonly PresensiService $presensi) {}

    public function index(): View
    {
        $dosen = Portal::user();
        $term = Portal::term();

        $this->authorize('viewAny', KelasKuliah::class);

        $kelas = KelasKuliah::query()
            ->with(['mataKuliah', 'pertemuan'])
            ->withCount('krsDetail as jumlah_peserta')
            ->where('tahun_akademik_id', $term->id)
            ->whereHas('dosen', fn ($query) => $query->where('dosen.id', $dosen->id))
            ->get();

        return view('dosen.presensi', [
            'judul' => 'Presensi',
            'konteks' => $term->nama,
            'breadcrumb' => ['Portal Dosen' => route('dosen.dashboard'), 'Presensi'],
            'daftar' => $kelas,
        ]);
    }

    public function show(KelasKuliah $kelas): View
    {
        $this->authorize('attend', $kelas);

        $pertemuan = $this->presensi->siapkanPertemuan($kelas);
        $peserta = $this->presensi->pesertaKelas($kelas);
        $rekap = $this->presensi->rekapKelas($kelas);

        // Marks indexed as [mahasiswa][pertemuan] so the grid is a lookup
        // rather than a query per cell.
        $tanda = Presensi::whereIn('pertemuan_kelas_id', $pertemuan->pluck('id'))
            ->get()
            ->groupBy('mahasiswa_id')
            ->map(fn ($baris) => $baris->keyBy('pertemuan_kelas_id'));

        return view('dosen.presensi-kelas', [
            'judul' => $kelas->mataKuliah->nama,
            'konteks' => 'Kelas '.$kelas->kode.' · '.$peserta->count().' mahasiswa',
            'breadcrumb' => [
                'Portal Dosen' => route('dosen.dashboard'),
                'Presensi' => route('dosen.presensi'),
                $kelas->mataKuliah->nama,
            ],
            'kelas' => $kelas,
            'pertemuan' => $pertemuan,
            'peserta' => $peserta,
            'tanda' => $tanda,
            'rekap' => $rekap,
            'minimum' => (float) config('academic.attendance.min_percent_for_final_exam'),
            'statusPilihan' => AttendanceStatus::cases(),
        ]);
    }

    public function simpan(Request $request, KelasKuliah $kelas, PertemuanKelas $pertemuan): RedirectResponse
    {
        $this->authorize('attend', $kelas);
        $this->pastikanMilikKelas($kelas, $pertemuan);

        $validated = $request->validate([
            'status' => ['required', 'array'],
            'status.*' => ['required', 'string', 'in:H,I,S,A'],
        ]);

        $jumlah = $this->presensi->catat($pertemuan, $validated['status']);

        return back()->with('sukses', "Presensi pertemuan {$pertemuan->pertemuan_ke} tersimpan untuk {$jumlah} mahasiswa.");
    }

    public function bukaQr(KelasKuliah $kelas, PertemuanKelas $pertemuan): RedirectResponse
    {
        $this->authorize('attend', $kelas);
        $this->pastikanMilikKelas($kelas, $pertemuan);

        $this->presensi->bukaSesiQr($pertemuan);

        return back()->with('sukses', 'Sesi presensi mandiri dibuka.');
    }

    public function tutupQr(KelasKuliah $kelas, PertemuanKelas $pertemuan): RedirectResponse
    {
        $this->authorize('attend', $kelas);
        $this->pastikanMilikKelas($kelas, $pertemuan);

        $this->presensi->tutupSesiQr($pertemuan);

        return back()->with('sukses', 'Sesi presensi mandiri ditutup.');
    }

    /**
     * Rejects a meeting that belongs to a different class.
     *
     * The two route parameters are resolved independently, so authorisation on
     * the class says nothing about the meeting. Without this, a lecturer could
     * post their own class id together with a meeting id from a colleague's
     * class and write attendance — or open a self-service QR window — on a
     * class they do not teach. The authorisation check would pass, because it
     * was never asked about the object being written to.
     */
    private function pastikanMilikKelas(KelasKuliah $kelas, PertemuanKelas $pertemuan): void
    {
        abort_unless($pertemuan->kelas_kuliah_id === $kelas->id, 404);
    }
}
