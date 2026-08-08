<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Services\Akademik\PenutupanSemesterService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Closing a semester — freezing the per-term academic record.
 *
 * The screen leads with what is blocking rather than with the button, because
 * the useful question before closing is never "may I close" but "who is not
 * ready and why".
 */
class PenutupanSemesterController extends Controller
{
    public function __construct(private readonly PenutupanSemesterService $penutupan) {}

    public function index(Request $request): View
    {
        // Term administration, same domain as activating and locking a semester
        // — a registrar's job, not a lecturer's. `nilai.manage` would be wrong:
        // lecturers hold it for their own classes.
        $this->izin('master.manage');

        $term = $request->filled('term')
            ? TahunAkademik::findOrFail($request->integer('term'))
            : Portal::term();

        $pratinjau = $term === null
            ? ['siap' => collect(), 'terhalang' => collect(), 'sudah_final' => 0, 'kelas_belum_final' => collect()]
            : $this->penutupan->pratinjau($term);

        return view('admin.tutup-semester', [
            'judul' => 'Penutupan Semester',
            'konteks' => $term?->nama ?? 'Belum ada semester aktif',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Penutupan Semester'],
            'term' => $term,
            'daftarTerm' => TahunAkademik::terbaru()->get(['id', 'kode', 'nama']),
            'pratinjau' => $pratinjau,

            'beku' => $term === null ? collect() : StatusMahasiswa::query()
                ->with('mahasiswa')
                ->where('tahun_akademik_id', $term->id)
                ->where('is_final', true)
                ->join('mahasiswa', 'mahasiswa.id', '=', 'status_mahasiswa.mahasiswa_id')
                ->orderBy('mahasiswa.nim')
                ->select('status_mahasiswa.*')
                ->paginate(20),
        ]);
    }

    public function tutup(Request $request): RedirectResponse
    {
        // Term administration, same domain as activating and locking a semester
        // — a registrar's job, not a lecturer's. `nilai.manage` would be wrong:
        // lecturers hold it for their own classes.
        $this->izin('master.manage');

        $term = TahunAkademik::findOrFail($request->integer('tahun_akademik_id'));

        $hasil = $this->penutupan->tutup($term, Portal::user());

        $pesan = sprintf(
            '%d catatan semester dibekukan. Batas SKS mahasiswa untuk semester berikutnya '
                .'kini dihitung dari IPS mereka.',
            $hasil['dibekukan'],
        );

        if ($hasil['dilewati'] > 0) {
            $pesan .= sprintf(' %d sudah beku sebelumnya dan dilewati.', $hasil['dilewati']);
        }

        if ($hasil['terhalang'] > 0) {
            return back()->with('peringatan', $pesan.sprintf(
                ' %d mahasiswa BELUM dibekukan karena masih ada kelasnya yang nilainya belum '
                    .'difinalisasi dosen — jalankan lagi setelah nilai masuk.',
                $hasil['terhalang'],
            ));
        }

        return back()->with('sukses', $pesan);
    }

    public function bukaKembali(Request $request, StatusMahasiswa $status): RedirectResponse
    {
        // Term administration, same domain as activating and locking a semester
        // — a registrar's job, not a lecturer's. `nilai.manage` would be wrong:
        // lecturers hold it for their own classes.
        $this->izin('master.manage');

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'alasan.required' => 'Membuka kembali catatan semester mengubah KHS yang sudah terbit — '
                .'alasannya wajib dicatat.',
        ]);

        $this->penutupan->bukaKembali($status, Portal::user(), $validated['alasan']);

        return back()->with('sukses', sprintf(
            'Catatan semester %s dibuka kembali dan tercatat pada jejak audit.',
            $status->mahasiswa->nama,
        ));
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
