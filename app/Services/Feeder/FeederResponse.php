<?php

declare(strict_types=1);

namespace App\Services\Feeder;

/**
 * One reply from the Neo Feeder web service.
 *
 * Feeder answers HTTP 200 for everything, including failures — the real
 * outcome lives in `error_code`, where 0 means success. Treating the HTTP
 * status as the verdict is the classic way to record a failed push as a
 * successful one, so nothing outside this class ever looks at it.
 */
final readonly class FeederResponse
{
    public function __construct(
        public int $errorCode,
        public string $errorDesc,
        public mixed $data,
        public array $raw = [],
    ) {}

    /** @param array<string, mixed> $body */
    public static function dariBody(array $body): self
    {
        return new self(
            errorCode: (int) ($body['error_code'] ?? -1),
            errorDesc: (string) ($body['error_desc'] ?? ''),
            data: $body['data'] ?? null,
            raw: $body,
        );
    }

    public function berhasil(): bool
    {
        return $this->errorCode === 0;
    }

    public function gagal(): bool
    {
        return !$this->berhasil();
    }

    /** @return array<int, array<string, mixed>> */
    public function rows(): array
    {
        if (is_array($this->data)) {
            return array_is_list($this->data) ? $this->data : [$this->data];
        }

        return [];
    }

    /** The identifier Feeder assigned, for whichever key name it used. */
    public function feederId(): ?string
    {
        $baris = $this->rows()[0] ?? null;

        if (!is_array($baris)) {
            return null;
        }

        foreach ([
            'id_registrasi_mahasiswa', 'id_mahasiswa', 'id_aktivitas_kuliah',
            'id_kelas_kuliah', 'id_dosen', 'id',
        ] as $kunci) {
            if (isset($baris[$kunci]) && $baris[$kunci] !== '') {
                return (string) $baris[$kunci];
            }
        }

        return null;
    }
}
