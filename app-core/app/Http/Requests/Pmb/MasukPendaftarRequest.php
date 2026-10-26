<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signing in to the admission portal.
 *
 * Throttled per address + IP, the same shape as the campus sign-in: one
 * address under attack cannot lock out a whole school's computer lab behind
 * one NAT. The key carries its own prefix so a lockout here never blocks the
 * same address on the main page, or the reverse.
 */
class MasukPendaftarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'ingat' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => __('pmb.akun.email'),
            'password' => __('pmb.akun.password'),
        ];
    }

    public function pastikanTidakDibatasi(): void
    {
        $maks = (int) config('pmb.batas.masuk_percobaan');

        if (!RateLimiter::tooManyAttempts($this->kunciBatas(), $maks)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('pmb.akun.terlalu_sering', ['seconds' => RateLimiter::availableIn($this->kunciBatas())]),
        ]);
    }

    public function catatGagal(): void
    {
        RateLimiter::hit($this->kunciBatas());
    }

    public function bersihkanBatas(): void
    {
        RateLimiter::clear($this->kunciBatas());
    }

    public function kunciBatas(): string
    {
        return 'pmb-masuk|'.Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
    }
}
