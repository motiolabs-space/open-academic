<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\Enums\JenisPoin;
use App\Exceptions\AturanAkademikException;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Sdm\Staff;
use Illuminate\Support\Collection;

/**
 * Poin kemahasiswaan — achievements and violations, kept apart.
 *
 * **There is no method on this class that returns a net figure, and that is
 * deliberate.** Netting the two ledgers would let a student pay off a sanction
 * with a competition win. No student-affairs office means that by either
 * number, and the moment such a method exists somebody will call it and print
 * the result on something.
 *
 * `rekap()` returns the two totals side by side. Whoever reads them decides what
 * they mean together — which is the only place that judgement belongs.
 */
class PoinKemahasiswaanService
{
    /**
     * Records a claim. Nothing counts until it is verified.
     *
     * @param array<string, mixed> $data
     */
    public function catat(
        Mahasiswa $mahasiswa,
        PoinKategori $kategori,
        array $data,
        ?Staff $staff = null,
    ): PoinMahasiswa {
        if (!$kategori->is_active) {
            throw new AturanAkademikException(sprintf(
                'Kategori "%s" sudah tidak aktif dan tidak dapat dipakai untuk catatan baru.',
                $kategori->nama,
            ));
        }

        if ($kategori->wajib_bukti && blank($data['bukti_path'] ?? null)) {
            throw new AturanAkademikException(sprintf(
                'Kategori "%s" mensyaratkan bukti terlampir.',
                $kategori->nama,
            ));
        }

        return PoinMahasiswa::create([
            'mahasiswa_id' => $mahasiswa->id,
            'poin_kategori_id' => $kategori->id,
            'tahun_akademik_id' => $data['tahun_akademik_id'] ?? null,
            'tanggal' => $data['tanggal'],
            'judul' => $data['judul'],
            'keterangan' => $data['keterangan'] ?? null,
            'bukti_path' => $data['bukti_path'] ?? null,

            // Frozen from the catalogue, not referenced. See the model.
            'poin' => $kategori->poin,
            'jenis' => $kategori->jenis,

            'dicatat_by_staff_id' => $staff?->id,
        ]);
    }

    public function verifikasi(PoinMahasiswa $baris, Staff $staff): PoinMahasiswa
    {
        if ($baris->is_verified) {
            throw new AturanAkademikException('Catatan ini sudah diverifikasi.');
        }

        $baris->update([
            'is_verified' => true,
            'verified_by_staff_id' => $staff->id,
            'verified_at' => now(),
            'alasan_tolak' => null,
        ]);

        return $baris->refresh();
    }

    /**
     * Refuses a claim, with a reason.
     *
     * Kept rather than deleted. A rejected achievement claim that vanishes
     * leaves the student unable to tell whether it was seen and refused or
     * simply lost — and a rejected allegation that vanishes leaves no record
     * that the campus looked into it and found nothing.
     */
    public function tolak(PoinMahasiswa $baris, Staff $staff, string $alasan): PoinMahasiswa
    {
        if ($baris->is_verified) {
            throw new AturanAkademikException(
                'Catatan yang sudah diverifikasi tidak dapat ditolak; batalkan verifikasinya lebih dulu.',
            );
        }

        if (blank($alasan)) {
            throw new AturanAkademikException('Penolakan wajib disertai alasan.');
        }

        $baris->update([
            'is_verified' => false,
            'verified_by_staff_id' => $staff->id,
            'verified_at' => now(),
            'alasan_tolak' => $alasan,
        ]);

        return $baris->refresh();
    }

    /**
     * The two totals, side by side and never combined.
     *
     * @return array{prestasi: int, pelanggaran: int, minimum: int, memenuhi: bool, temuan: ?string}
     */
    public function rekap(Mahasiswa $mahasiswa): array
    {
        $terhitung = PoinMahasiswa::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->diakui()
            ->get(['jenis', 'poin'])
            ->groupBy(fn (PoinMahasiswa $b): string => $b->jenis->value)
            ->map(fn (Collection $baris): int => (int) $baris->sum('poin'));

        $prestasi = (int) ($terhitung[JenisPoin::Prestasi->value] ?? 0);
        $pelanggaran = (int) ($terhitung[JenisPoin::Pelanggaran->value] ?? 0);

        $minimum = (int) config('kemahasiswaan.prestasi.minimum_lulus', 0);

        return [
            'prestasi' => $prestasi,
            'pelanggaran' => $pelanggaran,
            'minimum' => $minimum,

            // Achievements only. Violations do not reduce this, and there is a
            // test that keeps it that way.
            'memenuhi' => $minimum <= 0 || $prestasi >= $minimum,

            'temuan' => $this->temuanPelanggaran($pelanggaran),
        ];
    }

    /**
     * What the accumulated violation total crosses, if anything.
     *
     * A **finding**, not a sanction. Same stance as evaluasi studi: the system
     * may observe that somebody passed a threshold; what follows is a decision
     * a person makes, with a reason, on a screen built for it.
     */
    public function temuanPelanggaran(int $poin): ?string
    {
        foreach ((array) config('kemahasiswaan.pelanggaran.ambang', []) as $ambang) {
            if ($poin >= (int) $ambang['poin']) {
                return sprintf('%s (%d poin, ambang %d)', $ambang['sebutan'], $poin, (int) $ambang['poin']);
            }
        }

        return null;
    }

    /** @return Collection<int, PoinMahasiswa> */
    public function riwayat(Mahasiswa $mahasiswa, ?JenisPoin $jenis = null): Collection
    {
        return PoinMahasiswa::query()
            ->with(['kategori', 'tahunAkademik', 'diverifikasiOleh'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->when($jenis, fn ($q) => $q->jenis($jenis))
            ->orderByDesc('tanggal')
            ->get();
    }

    /** Claims waiting for somebody to look at them. */
    public function antrean(): Collection
    {
        return PoinMahasiswa::query()
            ->with(['mahasiswa.prodi', 'kategori'])
            ->menunggu()
            ->orderBy('tanggal')
            ->get();
    }
}
