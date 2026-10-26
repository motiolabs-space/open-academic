<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use Illuminate\Foundation\Http\FormRequest;

class LupaSandiRequest extends FormRequest
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
        return ['email' => ['required', 'email', 'max:255']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => __('pmb.akun.email')];
    }
}
