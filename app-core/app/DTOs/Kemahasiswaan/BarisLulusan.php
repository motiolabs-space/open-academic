<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Models\Kemahasiswaan\Alumni;

/**
 * One graduate on the tracer list, with what is wrong with their contact
 * details. `tanda` keys are in lang/id/lulusan.php under `tanda`.
 */
final readonly class BarisLulusan
{
    public const LENGKAP = 'lengkap';

    public const PERLU_VERIFIKASI = 'perlu_verifikasi';

    public const KOSONG = 'kosong';

    /** @param array<int, string> $tanda */
    public function __construct(
        public Alumni $alumni,
        public string $statusKontak,
        public array $tanda,
    ) {}

    public function perluVerifikasi(): bool
    {
        return $this->statusKontak !== self::LENGKAP;
    }
}
