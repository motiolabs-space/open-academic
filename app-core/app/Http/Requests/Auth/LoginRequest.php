<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\BatasMasuk;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // One field for every portal: an email, a NIM, a NIDN or a NIP.
            // Which of those it is decides the guard, so the person signing in
            // never has to know what an auth guard is.
            'identitas' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'ingat' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identitas' => 'NIM / NIDN / email',
            'password' => 'kata sandi',
        ];
    }

    /**
     * Throttled per identity + IP, per account and per address — see
     * BatasMasuk for why one counter was not enough.
     */
    public function ensureIsNotRateLimited(): void
    {
        $seconds = $this->batas()->sisaDetik();

        if ($seconds === null) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'identitas' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    public function hitRateLimiter(): void
    {
        $this->batas()->catatGagal();
    }

    public function clearRateLimiter(): void
    {
        $this->batas()->bersihkan();
    }

    private function batas(): BatasMasuk
    {
        return new BatasMasuk('masuk', (string) $this->string('identitas'), (string) $this->ip());
    }
}
