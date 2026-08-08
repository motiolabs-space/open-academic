<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

/**
 * The graduation checklist for one student.
 *
 * Every requirement carries its own verdict and the numbers behind it, so a
 * screen can show *why* someone is not eligible rather than a bare "belum
 * memenuhi syarat" that sends them to three different offices to find out.
 */
final readonly class SyaratKelulusan
{
    /** @param array<int, array{kode: string, label: string, terpenuhi: bool, keterangan: string}> $rincian */
    public function __construct(
        public int $sksLulus,
        public int $sksSyarat,
        public float $ipk,
        public float $ipkSyarat,
        public int $tunggakan,
        public bool $adaNilaiBelumFinal,
        public array $rincian,
    ) {}

    public function memenuhi(): bool
    {
        return collect($this->rincian)->every(fn (array $s): bool => $s['terpenuhi']);
    }

    /** @return array<int, string> labels of the requirements not yet met */
    public function belumTerpenuhi(): array
    {
        return collect($this->rincian)
            ->reject(fn (array $s): bool => $s['terpenuhi'])
            ->pluck('label')
            ->values()
            ->all();
    }

    public function persenSelesai(): int
    {
        $total = count($this->rincian);

        if ($total === 0) {
            return 0;
        }

        $terpenuhi = collect($this->rincian)->where('terpenuhi', true)->count();

        return (int) round($terpenuhi / $total * 100);
    }
}
