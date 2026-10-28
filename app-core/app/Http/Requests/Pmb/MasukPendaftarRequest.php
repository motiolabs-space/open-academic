<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\Support\BatasMasuk;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Signing in to the admission portal.
 *
 * Throttled the same way as the campus sign-in (BatasMasuk): per address + IP,
 * per account and per IP. The keys carry their own prefix so a lockout here
 * never blocks the same address on the main page, or the reverse.
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
        $detik = $this->batas()->sisaDetik();

        if ($detik === null) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('pmb.akun.terlalu_sering', ['seconds' => $detik]),
        ]);
    }

    public function catatGagal(): void
    {
        $this->batas()->catatGagal();
    }

    public function bersihkanBatas(): void
    {
        $this->batas()->bersihkan();
    }

    private function batas(): BatasMasuk
    {
        return new BatasMasuk(
            'pmb-masuk',
            (string) $this->string('email'),
            (string) $this->ip(),
            (int) config('pmb.batas.masuk_percobaan'),
        );
    }
}
