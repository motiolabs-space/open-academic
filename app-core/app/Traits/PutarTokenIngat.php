<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * A new password retires every "ingat saya" cookie issued under the old one.
 *
 * The remember cookie carries the remember token, not the password, so it
 * survives a password change untouched — a stolen cookie kept working after
 * the victim changed their password, and after an administrator reset it.
 * Cycling the token on the model, rather than in each place a password is
 * set, means a future password screen cannot forget to.
 */
trait PutarTokenIngat
{
    public static function bootPutarTokenIngat(): void
    {
        static::saving(function (self $model): void {
            if ($model->exists && $model->isDirty('password')) {
                $model->setRememberToken(Str::random(60));
            }
        });
    }
}
