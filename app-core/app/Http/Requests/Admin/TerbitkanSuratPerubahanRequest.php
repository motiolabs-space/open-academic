<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Issuing a PDM/PJK request letter: which requests it carries, and the
 * reason printed on it. Whether those requests may share a letter is the
 * service's rule, not a validation rule.
 */
class TerbitkanSuratPerubahanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The policy is checked in the controller, per request.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'perubahan' => ['required', 'array', 'min:1', 'max:20'],
            'perubahan.*' => ['required', 'uuid', Rule::exists('feeder_change_requests', 'uuid')->whereNull('deleted_at')],
            'alasan' => ['required', 'string', 'max:255'],
        ];
    }
}
