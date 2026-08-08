<?php

declare(strict_types=1);

namespace App\Services\Akuntansi;

use RuntimeException;

/**
 * A document could not be sent because something it depends on could not be.
 *
 * Carries the underlying result rather than just a message, so the sender can
 * still ask whether the cause was worth retrying. Without it a dropped
 * connection while creating a contact would permanently fail the invoice behind
 * it — a transient fault turned into a document somebody has to notice and
 * requeue by hand.
 */
class DependensiGagal extends RuntimeException
{
    public function __construct(public readonly HasilKirim $hasil)
    {
        parent::__construct($hasil->galat ?? 'Dependensi ditolak tanpa keterangan.');
    }
}
