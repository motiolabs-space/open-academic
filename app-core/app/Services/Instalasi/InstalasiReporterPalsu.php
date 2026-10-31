<?php

declare(strict_types=1);

namespace App\Services\Instalasi;

use App\DTOs\Instalasi\HasilLaporan;
use App\Services\Instalasi\Contracts\InstalasiReporterInterface;

/**
 * Keeps every ping in memory instead of sending it. Bound as a singleton, so a
 * test can read back exactly what would have left the building.
 */
class InstalasiReporterPalsu implements InstalasiReporterInterface
{
    /** @var list<array<string, string>> */
    public array $terkirim = [];

    private ?string $galat = null;

    public function kirim(array $muatan): HasilLaporan
    {
        if ($this->galat !== null) {
            return HasilLaporan::gagal($this->galat, 503);
        }

        $this->terkirim[] = $muatan;

        return HasilLaporan::berhasil(202);
    }

    /** Makes every following send fail, as an unreachable endpoint would. */
    public function gagalkan(string $galat = 'Endpoint tidak dapat dihubungi'): self
    {
        $this->galat = $galat;

        return $this;
    }
}
