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
