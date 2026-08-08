<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Support\Portal;
use Illuminate\View\View;

/**
 * The advisor's caseload.
 *
 * Sorted so the students who need attention surface first: an advisor with
 * twelve advisees should not have to read twelve GPA histories to find the two
 * that are slipping.
 */
class BimbinganController extends Controller
{
    public function __invoke(): View
    {
        $dosen = Portal::user();
        $term = Portal::term();

        $this->authorize('viewAny', Mahasiswa::class);

        $mahasiswa = Mahasiswa::query()
            ->with(['prodi', 'statusPerSemester.tahunAkademik'])
            ->where('dosen_wali_id', $dosen->id)
            ->orderBy('nim')
            ->get()
            ->map(function (Mahasiswa $m) use ($term): array {
                $riwayat = $m->statusPerSemester
                    ->where('is_final', true)
                    ->sortBy(fn ($s): string => $s->tahunAkademik->kode)
                    ->values();

                $terakhir = $riwayat->last();
                $sebelumnya = $riwayat->count() > 1 ? $riwayat[$riwayat->count() - 2] : null;

                return [
                    'mahasiswa' => $m,
                    'riwayat' => $riwayat,
                    'ips' => $terakhir?->ips === null ? null : (float) $terakhir->ips,
                    'ipk' => $terakhir?->ipk === null ? null : (float) $terakhir->ipk,
                    'tren' => $terakhir && $sebelumnya
                        ? round((float) $terakhir->ips - (float) $sebelumnya->ips, 2)
                        : null,
                    'krs' => $m->krs()->where('tahun_akademik_id', $term->id)->first(),
                    'peringatan' => $this->peringatan($terakhir?->ipk, $terakhir?->ips),
                ];
            })
            ->sortByDesc(fn (array $b): int => count($b['peringatan']))
            ->values();

        return view('dosen.bimbingan', [
            'judul' => 'Mahasiswa Bimbingan',
            'konteks' => $mahasiswa->count().' mahasiswa · '
                .$mahasiswa->filter(fn (array $b): bool => $b['peringatan'] !== [])->count().' perlu perhatian',
            'breadcrumb' => ['Portal Dosen' => route('dosen.dashboard'), 'Mahasiswa Bimbingan'],
            'daftar' => $mahasiswa,
            'ipsMaksimum' => 4.0,
        ]);
    }

    /** @return array<int, string> */
    private function peringatan(mixed $ipk, mixed $ips): array
    {
        $peringatan = [];

        if ($ipk !== null && (float) $ipk < 2.0) {
            $peringatan[] = 'IPK di bawah 2,00';
        }

        if ($ips !== null && (float) $ips < 2.0) {
            $peringatan[] = 'IPS semester terakhir di bawah 2,00';
        }

        return $peringatan;
    }
}
