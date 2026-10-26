<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Which programme and intake term to read back from PDDIKTI. */
class JalankanAdopsiFeederRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The policy is checked in the controller.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prodi' => ['required', 'uuid', 'exists:prodi,uuid'],
            'periode' => ['required', 'string', 'regex:/^\d{4}[123]$/'],
        ];
    }
}
