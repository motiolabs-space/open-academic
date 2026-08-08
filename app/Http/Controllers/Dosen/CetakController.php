<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Sdm\Dosen;
use App\Services\Dokumen\CetakService;
use App\Support\Portal;
use Illuminate\Http\Response;

/**
 * The two sheets a lecturer carries into a room.
 *
 * Both are scoped to classes this lecturer actually teaches — a register lists
 * every student in a class by name, which is precisely the kind of list that
 * should not be printable by whoever guesses a class id.
 */
class CetakController extends Controller
{
    public function __construct(private readonly CetakService $cetak) {}

    public function absensi(KelasKuliah $kelas): Response
    {
        $this->pastikanMengampu($kelas);

        return $this->cetak->absensi($kelas)
            ->download('Absensi-'.$kelas->mataKuliah->kode.'-'.$kelas->kode.'.pdf');
    }

    public function jurnal(KelasKuliah $kelas): Response
    {
        $this->pastikanMengampu($kelas);

        return $this->cetak->jurnal($kelas)
            ->download('Jurnal-'.$kelas->mataKuliah->kode.'-'.$kelas->kode.'.pdf');
    }

    private function pastikanMengampu(KelasKuliah $kelas): void
    {
        $dosen = Portal::user();

        abort_unless($dosen instanceof Dosen, 403);
        abort_unless($kelas->dosen->contains('id', $dosen->id), 403);
    }
}
