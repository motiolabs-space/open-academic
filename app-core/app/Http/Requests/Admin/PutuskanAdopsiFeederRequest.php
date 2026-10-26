<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A person's decision on an ambiguous adoption row.
 *
 * The reason is required and long enough to say something: "ok" is not an
 * answer to "why are these the same student?".
 */
class PutuskanAdopsiFeederRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The policy is checked in the controller.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'catatan' => ['required', 'string', 'min:10', 'max:1000'],
            'id_registrasi_mahasiswa' => [$this->routeIs('*.tautkan') ? 'required' : 'nullable', 'string', 'max:64'],
        ];
    }
}
