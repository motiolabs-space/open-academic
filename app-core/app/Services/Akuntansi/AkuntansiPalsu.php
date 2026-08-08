<?php

declare(strict_types=1);

namespace App\Services\Akuntansi;

use App\Services\Akuntansi\Contracts\AkuntansiClientInterface;

/**
 * An accounting system that lives in memory.
 *
 * What the test suite and the demo campus talk to. The point is not merely to
 * avoid the network — it is that a test which accidentally posts a journal
 * entry into somebody's real books is not a failure anybody can undo.
 *
 * It enforces idempotency the same way easyERP does, because a fake that is
 * more forgiving than the real thing lets a duplicate-key bug pass the suite
 * and surface in production.
 */
class AkuntansiPalsu implements AkuntansiClientInterface
{
    /** @var array<int, array{endpoint: string, payload: array<string, mixed>, kunci: string}> */
    public array $terkirim = [];

    /** @var array<string, HasilKirim> keyed by idempotency key */
    private array $jawaban = [];

    /** Set to make the next sends fail, for exercising the retry path. */
    public ?HasilKirim $paksaGagal = null;

    public bool $sedangTersedia = true;

    private int $urutan = 0;

    public function kirim(string $endpoint, array $payload, string $kunciIdempotensi): HasilKirim
    {
        // Replayed key: the first answer, without recording a second call.
        // Exactly what easyERP does per tenant.
        if (isset($this->jawaban[$kunciIdempotensi])) {
            return $this->jawaban[$kunciIdempotensi];
        }

        if ($this->paksaGagal !== null) {
            // Deliberately not memoised — a failure must stay retryable.
            return $this->paksaGagal;
        }

        $this->terkirim[] = [
            'endpoint' => $endpoint,
            'payload' => $payload,
            'kunci' => $kunciIdempotensi,
        ];

        $id = (string) (++$this->urutan);

        return $this->jawaban[$kunciIdempotensi] = HasilKirim::sukses(
            id: $id,
            nomor: strtoupper(substr($endpoint, 0, 3)).'-'.str_pad($id, 5, '0', STR_PAD_LEFT),
            data: ['id' => $id],
        );
    }

    public function tersedia(): bool
    {
        return $this->sedangTersedia;
    }

    /** @return array<int, array<string, mixed>> payloads posted to one endpoint */
    public function payloadUntuk(string $endpoint): array
    {
        return collect($this->terkirim)
            ->where('endpoint', $endpoint)
            ->pluck('payload')
            ->all();
    }

    public function jumlahKirim(?string $endpoint = null): int
    {
        return $endpoint === null
            ? count($this->terkirim)
            : count($this->payloadUntuk($endpoint));
    }
}
