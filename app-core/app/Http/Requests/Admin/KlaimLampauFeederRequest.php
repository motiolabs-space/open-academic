<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The past students an operator ticked for claiming.
 *
 * Whether each one can be claimed is the pre-flight check's question, asked
 * against a fresh read of PDDIKTI; this only makes sure the selection names
 * report rows. The reason, when given, has to say something — it is what
 * stands behind claiming an ambiguous row.
 */
class KlaimLampauFeederRequest extends FormRequest
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
            'baris' => ['required', 'array', 'min:1', 'max:500'],
            'baris.*' => ['required', 'uuid', 'distinct', 'exists:feeder_past_students,uuid'],
            'catatan' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }
}
