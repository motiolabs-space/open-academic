<?php

declare(strict_types=1);

namespace App\Services\Akuntansi;

/**
 * What came back from one attempt at posting a document.
 *
 * A value object rather than a raw array so the difference between "refused"
 * and "unreachable" cannot be lost — they call for different handling, and the
 * distinction disappears the moment somebody reads `$respons['error']`.
 */
readonly class HasilKirim
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public bool $berhasil,
        public ?string $easyerpId = null,
        public ?string $nomor = null,
        public ?string $galat = null,
        public ?int $kodeHttp = null,
        public array $data = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function sukses(?string $id, ?string $nomor = null, array $data = []): self
    {
        return new self(berhasil: true, easyerpId: $id, nomor: $nomor, data: $data);
    }

    public static function gagal(string $galat, ?int $kodeHttp = null): self
    {
        return new self(berhasil: false, galat: $galat, kodeHttp: $kodeHttp);
    }

    /**
     * Whether retrying could plausibly succeed.
     *
     * A timeout or a 5xx is worth another go. A 422 means the payload is wrong
     * — an account code that does not exist on the other side, most often — and
     * retrying it a hundred times just buries the mistake under a counter.
     * A 401 is a bad API key and will not fix itself either.
     */
    public function layakDiulang(): bool
    {
        return match (true) {
            $this->berhasil => false,
            $this->kodeHttp === null => true,           // network, not an answer
            $this->kodeHttp >= 500 => true,
            $this->kodeHttp === 429 => true,
            default => false,
        };
    }
}
