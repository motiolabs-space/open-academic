<?php

declare(strict_types=1);

namespace App\Services\Auth\Idp;

use RuntimeException;

/**
 * A sign-in through the external IdP that cannot complete.
 *
 * `alasan` is a short machine-readable code that goes to the activity log;
 * the message is for operators and never contains a token or a secret.
 */
class IdpException extends RuntimeException
{
    public function __construct(
        public readonly string $alasan,
        string $message,
    ) {
        parent::__construct($message);
    }
}
