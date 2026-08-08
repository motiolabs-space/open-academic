<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Dokumen\CetakService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The same four documents, printed by the office on someone else's behalf.
 *
 * Separate from the student and lecturer controllers rather than shared with
 * them, because the authorisation is the part that differs and sharing a
 * controller would mean one method deciding which of three rules applies —
 * which is how the wrong one eventually gets picked.
 */
class CetakController extends Controller
{
    public function __construct(private readonly CetakService $cetak) {}

    public function ktm(Mahasiswa $mahasiswa): Response
    {
        $this->izin('mahasiswa.view');

        return $this->cetak->ktm($mahasiswa)
            ->download('KTM-'.$mahasiswa->nim.'.pdf');
    }

    /**
     * The office prints the same card, under the same rules.
     *
     * **No override, on purpose.** An earlier draft had `?paksa=1` so the
     * counter could print past a financial hold — for the student who paid in
     * cash five minutes ago and whose receipt is not keyed in yet. But a
     * download leaves no audit row, so that override would let a hold be
     * bypassed with no trace at all, which is worse than the inconvenience it
     * removes. The two real fixes both leave trails: record the payment, or
     * approve the plan.
     */
    public function kartuUjian(Krs $krs): Response|RedirectResponse
    {
        $this->izin('krs.view');

        $kelayakan = $this->cetak->kelayakanKartuUjian($krs);

        if (!$kelayakan['layak']) {
            return back()->with('galat', 'Kartu ujian tertahan: '
                .implode(' ', $kelayakan['alasan']));
        }

        return $this->cetak->kartuUjian($krs)
            ->download('Kartu-Ujian-'.$krs->mahasiswa->nim.'.pdf');
    }

    public function absensi(KelasKuliah $kelas): Response
    {
        $this->izin('kelas.view');

        return $this->cetak->absensi($kelas)
            ->download('Absensi-'.$kelas->mataKuliah->kode.'-'.$kelas->kode.'.pdf');
    }

    public function jurnal(KelasKuliah $kelas): Response
    {
        $this->izin('kelas.view');

        return $this->cetak->jurnal($kelas)
            ->download('Jurnal-'.$kelas->mataKuliah->kode.'-'.$kelas->kode.'.pdf');
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
