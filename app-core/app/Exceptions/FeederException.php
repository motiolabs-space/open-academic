<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The Neo Feeder service refused or could not be reached.
 *
 * Carries the Feeder error code so the sync ledger can record *why* a row
 * failed, not merely that it did — an operator chasing a reporting deadline
 * needs the reason, and "sync failed" is not one.
 */
class FeederException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $feederErrorCode = null,
        public readonly ?string $act = null,
    ) {
        parent::__construct($message);
    }

    public static function tidakTersambung(string $url, string $sebab): self
    {
        return new self(
            "Tidak dapat menghubungi Neo Feeder di {$url}. {$sebab}",
            act: 'connect',
        );
    }

    public static function gagalToken(string $desc): self
    {
        return new self(
            "Gagal memperoleh token Neo Feeder: {$desc}. Periksa kredensial pada .env.",
            act: 'GetToken',
        );
    }

    public static function ditolak(string $act, int $code, string $desc): self
    {
        return new self(
            "Neo Feeder menolak aksi {$act} (kode {$code}): {$desc}",
            feederErrorCode: $code,
            act: $act,
        );
    }

    public static function entitasTidakDikenal(string $entity): self
    {
        return new self("Entitas sinkronisasi \"{$entity}\" tidak terdaftar pada config/feeder.php.");
    }

    /** @param array<int, string> $belum */
    public static function dependensiBelumSinkron(string $entity, array $belum): self
    {
        return new self(sprintf(
            'Entitas %s tidak dapat dikirim sebelum %s berhasil disinkronkan pada semester yang sama.',
            $entity,
            implode(', ', $belum),
        ));
    }

    public static function adaBarisTidakValid(string $entity, int $jumlah): self
    {
        return new self(
            "Sinkronisasi {$entity} dibatalkan: {$jumlah} baris tidak lolos validasi pra-kirim. "
            .'Perbaiki data tersebut terlebih dahulu.',
        );
    }

    public static function dinonaktifkan(): self
    {
        return new self('Integrasi Neo Feeder sedang dinonaktifkan (FEEDER_ENABLED=false).');
    }
}
