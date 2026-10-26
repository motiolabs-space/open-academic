<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Marking an MBKM activity as a flagship programme, or clearing the mark.
 * Whether the code is one PDDIKTI lists is the service's rule, not a
 * validation rule, so the console and any later caller share it.
 */
class TandaiFlagshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The permission is checked in the controller.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'flagship_kode' => ['nullable', 'string', 'max:64'],
        ];
    }
}
