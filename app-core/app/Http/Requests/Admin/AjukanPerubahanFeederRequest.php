<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filing a PDM/PJK request at PDDIKTI admin: the letter number that carries
 * it, and a note for whoever picks it up next.
 */
class AjukanPerubahanFeederRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The policy is checked in the controller.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nomor_surat' => ['nullable', 'string', 'max:96'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
