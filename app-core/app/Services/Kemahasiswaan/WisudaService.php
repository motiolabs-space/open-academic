<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\Exceptions\AturanAkademikException;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The graduation ceremony: who walks, in what order, holding which diploma.
 *
 * Distinct from yudisium, which is the academic decision that somebody has
 * graduated. A person is a graduate the moment yudisium is confirmed; the
 * ceremony is a separate event they register for, may miss, and may attend a
 * later round of. Collapsing the two would mean a graduate who skipped the
 * ceremony is not recorded as having graduated.
 *
 * The diploma number is the part that has to be right. It is printed on a
 * document the person keeps for life and quotes on every job application, so it
 * is issued once, never reused, and never silently regenerated.
 */
class WisudaService
{
    /**
     * Registers a confirmed graduate for a ceremony.
     *
     * Only confirmed graduations are eligible: registering somebody whose
     * yudisium is still a proposal would put a name on the programme for a
     * degree that has not been awarded.
     */
    public function daftarkan(WisudaPeriode $periode, Yudisium $yudisium): WisudaPeserta
    {
        if (!$periode->is_pendaftaran_dibuka) {
            throw new AturanAkademikException("Pendaftaran {$periode->nama} sudah ditutup.");
        }

        if ($yudisium->status !== 'ditetapkan') {
            throw new AturanAkademikException(
                "Kelulusan {$yudisium->mahasiswa->nama} belum ditetapkan, sehingga belum dapat "
                    .'didaftarkan sebagai peserta wisuda.',
            );
        }

        $sudah = WisudaPeserta::where('yudisium_id', $yudisium->id)->first();

        if ($sudah !== null) {
            throw new AturanAkademikException(sprintf(
                '%s sudah terdaftar pada %s.',
                $yudisium->mahasiswa->nama,
                $sudah->periode->nama,
            ));
        }

        return DB::transaction(function () use ($periode, $yudisium): WisudaPeserta {
            // Locked before counting: two registrations arriving together would
            // otherwise both read the same count and both be admitted past a
            // full ceremony, or take the same queue number.
            $terkunci = WisudaPeriode::whereKey($periode->id)->lockForUpdate()->firstOrFail();

            $terdaftar = WisudaPeserta::where('wisuda_periode_id', $terkunci->id)->count();

            if ($terkunci->kuota !== null && $terdaftar >= (int) $terkunci->kuota) {
                throw new AturanAkademikException(sprintf(
                    'Kuota %s sudah penuh (%d peserta).',
                    $terkunci->nama,
                    $terkunci->kuota,
                ));
            }

            return WisudaPeserta::create([
                'wisuda_periode_id' => $terkunci->id,
                'yudisium_id' => $yudisium->id,
                'nomor_urut' => $terdaftar + 1,
            ]);
        });
    }

    /**
     * Registers every eligible graduate who has not been placed yet.
     *
     * @return array{didaftarkan: int, dilewati: int, gagal: Collection<int, string>}
     */
    public function daftarkanMassal(WisudaPeriode $periode): array
    {
        $kandidat = Yudisium::query()
            ->with('mahasiswa')
            ->where('status', 'ditetapkan')
            ->whereDoesntHave('pesertaWisuda')
            ->orderBy('tanggal_lulus')
            ->get();

        $didaftarkan = 0;
        $gagal = collect();

        foreach ($kandidat as $yudisium) {
            try {
                $this->daftarkan($periode, $yudisium);
                $didaftarkan++;
            } catch (AturanAkademikException $e) {
                // A full quota stops the run; anything else is about this one
                // person and must not abandon the rest.
                $gagal->push($yudisium->mahasiswa->nama.': '.$e->getMessage());

                if (str_contains($e->getMessage(), 'Kuota')) {
                    break;
                }
            }
        }

        return [
            'didaftarkan' => $didaftarkan,
            'dilewati' => $kandidat->count() - $didaftarkan - $gagal->count(),
            'gagal' => $gagal,
        ];
    }

    public function batalkan(WisudaPeserta $peserta): void
    {
        if (filled($peserta->nomor_ijazah)) {
            throw new AturanAkademikException(
                'Peserta ini sudah menerima nomor ijazah dan tidak dapat dikeluarkan dari daftar. '
                    .'Nomor ijazah tercetak pada dokumen yang dipegang seumur hidup.',
            );
        }

        $peserta->delete();
    }

    /**
     * Issues diploma numbers for everyone in the ceremony who lacks one.
     *
     * Never regenerated. A number already issued stays as it is even if the
     * ceremony is re-run, because it is already printed on a document in
     * somebody's hands.
     *
     * @return array{diterbitkan: int, dilewati: int}
     */
    public function terbitkanNomorIjazah(WisudaPeriode $periode, Staff $staff, string $pola): array
    {
        if (!$this->penomoranLokal()) {
            throw new AturanAkademikException(
                'Nomor ijazah kampus ini berasal dari Penomoran Ijazah Nasional (PIN) dan tidak '
                    .'dibangkitkan di sini. Reservasi nomornya di portal PIN, lalu catat hasilnya '
                    .'pada periode wisuda. Ubah ACADEMIC_IJAZAH_SUMBER=lokal hanya bila institusi '
                    .'berada di luar skema nasional.',
            );
        }

        $peserta = WisudaPeserta::query()
            ->with('yudisium.mahasiswa.prodi')
            ->where('wisuda_periode_id', $periode->id)
            ->orderBy('nomor_urut')
            ->get();

        $diterbitkan = 0;
        $dilewati = 0;

        foreach ($peserta as $baris) {
            if (filled($baris->nomor_ijazah)) {
                $dilewati++;

                continue;
            }

            $baris->update(['nomor_ijazah' => $this->nomorIjazah($pola, $periode, $baris)]);
            $diterbitkan++;
        }

        if ($diterbitkan > 0) {
            $periode->recordActivity('diploma_numbers_issued', sprintf(
                '%d nomor ijazah diterbitkan oleh %s.',
                $diterbitkan,
                $staff->nama,
            ));
        }

        return ['diterbitkan' => $diterbitkan, 'dilewati' => $dilewati];
    }

    /** Whether this campus generates its own diploma numbers. */
    public function penomoranLokal(): bool
    {
        return config('academic.ijazah.sumber', 'pin') === 'lokal';
    }

    /**
     * Records diploma numbers reserved at the national service (PIN).
     *
     * Input is what the portal gives back: one graduate per line, NIM and
     * number, separated by a comma, a semicolon, a tab or spaces. Each line
     * is judged on its own — a bad line is reported, the good ones land —
     * because the alternative, all-or-nothing, means an operator retypes the
     * forty good lines to fix the one typo.
     *
     * A number already recorded is never overwritten: it may be printed on a
     * document by now. A line that repeats the same number for the same
     * graduate is a harmless re-import and counted as skipped.
     *
     * @return array{dicatat: int, dilewati: int, ditolak: array<int, string>}
     */
    public function catatNomorIjazah(WisudaPeriode $periode, Staff $staff, string $isian): array
    {
        $peserta = WisudaPeserta::query()
            ->with('yudisium.mahasiswa')
            ->where('wisuda_periode_id', $periode->id)
            ->get()
            ->keyBy(fn (WisudaPeserta $p): string => $p->yudisium->mahasiswa->nim);

        $dicatat = 0;
        $dilewati = 0;
        $ditolak = [];
        $terpakai = [];

        foreach (preg_split('/\R/', trim($isian)) ?: [] as $nomorBaris => $baris) {
            $baris = trim($baris);

            if ($baris === '' || str_starts_with($baris, '#')) {
                continue;
            }

            $bagian = preg_split('/[,;\t]+|\s{1,}/', $baris, 2) ?: [];
            $nim = trim($bagian[0] ?? '');
            $nomor = trim($bagian[1] ?? '');
            $label = 'Baris '.($nomorBaris + 1).' ('.$nim.')';

            if ($nim === '' || $nomor === '') {
                $ditolak[] = "{$label}: butuh NIM dan nomor ijazah, dipisah koma, titik koma, atau spasi.";

                continue;
            }

            $p = $peserta->get($nim);

            if ($p === null) {
                $ditolak[] = "{$label}: NIM tidak terdaftar pada periode wisuda ini.";

                continue;
            }

            if (filled($p->nomor_ijazah)) {
                if ($p->nomor_ijazah === $nomor) {
                    $dilewati++;
                } else {
                    $ditolak[] = "{$label}: sudah tercatat {$p->nomor_ijazah}; nomor yang sudah tercatat tidak ditimpa.";
                }

                continue;
            }

            if (isset($terpakai[$nomor]) || WisudaPeserta::query()->where('nomor_ijazah', $nomor)->exists()) {
                $ditolak[] = "{$label}: nomor {$nomor} sudah dipakai lulusan lain.";

                continue;
            }

            $p->update(['nomor_ijazah' => $nomor]);
            $terpakai[$nomor] = true;
            $dicatat++;
        }

        if ($dicatat > 0) {
            $periode->recordActivity('diploma_numbers_recorded', sprintf(
                '%d nomor ijazah PIN dicatat oleh %s.',
                $dicatat,
                $staff->nama,
            ));
        }

        return ['dicatat' => $dicatat, 'dilewati' => $dilewati, 'ditolak' => $ditolak];
    }

    /**
     * Builds one diploma number from the campus's pattern.
     *
     * Placeholders mirror the NIM generator so an operator only learns one
     * convention: {tahun}, {prodi}, {urut}.
     */
    private function nomorIjazah(string $pola, WisudaPeriode $periode, WisudaPeserta $peserta): string
    {
        $prodi = $peserta->yudisium->mahasiswa->prodi;

        return str_replace(
            ['{tahun}', '{prodi}', '{urut}'],
            [
                (string) $periode->tanggal->year,
                $prodi->kode_pddikti ?: $prodi->kode,
                str_pad((string) $peserta->nomor_urut, 4, '0', STR_PAD_LEFT),
            ],
            $pola,
        );
    }

    public function bukaPendaftaran(WisudaPeriode $periode): WisudaPeriode
    {
        $periode->update(['is_pendaftaran_dibuka' => true]);

        return $periode->refresh();
    }

    public function tutupPendaftaran(WisudaPeriode $periode): WisudaPeriode
    {
        $periode->update(['is_pendaftaran_dibuka' => false]);

        return $periode->refresh();
    }
}
