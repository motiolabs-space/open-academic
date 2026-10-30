<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A URL that will receive something worth stealing: an authorization code, or
 * a signed webhook carrying student data.
 *
 * HTTPS, except on the loopback interface where local development has no
 * certificate and no network path to intercept. Over plain HTTP anyone on the
 * path reads the code and redeems it before the real consumer does — which
 * hands them the account, not just the code.
 */
class UriCallbackAman implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!self::sah((string) $value)) {
            $fail(__('validation.uri_callback_aman'));
        }
    }

    public static function sah(string $uri): bool
    {
        if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $skema = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
        $host = trim(strtolower((string) parse_url($uri, PHP_URL_HOST)), '[]');

        if ($skema === 'https') {
            return true;
        }

        return $skema === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
