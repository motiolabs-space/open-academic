<?php

declare(strict_types=1);

namespace App\DTOs\Instalasi;

/** What came back from one attempt to send an installation ping. */
final readonly class HasilLaporan
{
    public function __construct(
        public bool $berhasil,
        public ?int $statusHttp = null,
        public ?string $galat = null,
    ) {}

    public static function berhasil(int $statusHttp): self
    {
        return new self(true, $statusHttp);
    }

    public static function gagal(string $galat, ?int $statusHttp = null): self
    {
        return new self(false, $statusHttp, mb_substr($galat, 0, 500));
    }
}
