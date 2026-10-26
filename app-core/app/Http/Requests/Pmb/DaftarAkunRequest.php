<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\DTOs\Pmb\AkunPendaftarData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Opening an applicant account. Anonymous by nature; rate-limited by route. */
class DaftarAkunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('pmb_akun', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.unique' => __('pmb.akun.surel_dipakai')];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nama' => __('pmb.akun.nama'),
            'email' => __('pmb.akun.email'),
            'password' => __('pmb.akun.password'),
        ];
    }

    public function dto(): AkunPendaftarData
    {
        return AkunPendaftarData::fromRequest($this);
    }
}
