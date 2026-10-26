<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use Illuminate\Support\Collection;

/**
 * Splits field-programme participants into groups.
 *
 * Pure: it reads the participants it is given and returns a partition, and
 * writes nothing. KegiatanLapanganService owns the groups it produces.
 *
 * Two rules, both from the programme run:
 *
 *  - **mixing** — with `campur_prodi` on, a group draws from every programme
 *    in proportion; off, each programme forms its own groups. The same for
 *    `campur_jenis_kelamin`. A rule that is off partitions the participants
 *    first and the groups are formed inside each part.
 *  - **size** — the target is a ceiling, and groups are evened out: 23 at a
 *    target of 10 gives 8 + 8 + 7, never 10 + 10 + 3. A three-person group
 *    in a village is a different programme from a ten-person one.
 *
 * Mixing is done by dealing. Participants are ordered by programme, sex and
 * NIM, then dealt round the groups like cards, so each programme and each sex
 * lands as evenly as the numbers allow. Ordering by NIM makes the result
 * reproducible: forming groups twice from the same list gives the same groups.
 */
class PembentukKelompok
{
    /**
     * @param Collection<int, AktivitasMahasiswa> $peserta with mahasiswa loaded
     * @return list<list<AktivitasMahasiswa>>
     */
    public function bentuk(Collection $peserta, int $ukuran, bool $campurProdi, bool $campurJenisKelamin): array
    {
        $ukuran = max(1, $ukuran);

        $bagian = $peserta
            ->groupBy(fn (AktivitasMahasiswa $a): string => implode('|', [
                $campurProdi ? '*' : (string) $a->mahasiswa->prodi_id,
                $campurJenisKelamin ? '*' : $this->jenisKelamin($a),
            ]))
            ->sortKeys();

        $kelompok = [];

        foreach ($bagian as $anggota) {
            $urut = $anggota->sortBy([
                fn (AktivitasMahasiswa $a, AktivitasMahasiswa $b): int => $a->mahasiswa->prodi_id <=> $b->mahasiswa->prodi_id,
                fn (AktivitasMahasiswa $a, AktivitasMahasiswa $b): int => $this->jenisKelamin($a) <=> $this->jenisKelamin($b),
                fn (AktivitasMahasiswa $a, AktivitasMahasiswa $b): int => strcmp((string) $a->mahasiswa->nim, (string) $b->mahasiswa->nim),
            ])->values();

            $jumlah = (int) ceil($urut->count() / $ukuran);
            $tumpukan = array_fill(0, $jumlah, []);

            foreach ($urut as $i => $a) {
                $tumpukan[$i % $jumlah][] = $a;
            }

            array_push($kelompok, ...$tumpukan);
        }

        return $kelompok;
    }

    private function jenisKelamin(AktivitasMahasiswa $a): string
    {
        $jk = $a->mahasiswa->jenis_kelamin;

        return (string) ($jk instanceof \BackedEnum ? $jk->value : ($jk ?? '-'));
    }
}
