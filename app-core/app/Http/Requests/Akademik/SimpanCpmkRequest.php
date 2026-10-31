<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\DTOs\Akademik\RumusanCpmkData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A course's CPMK set as the editor submits it — from the programme's screen
 * or the lecturer's RPS screen, which share one form.
 *
 * Shape only. The rules that span rows (weights closing to 100, CPL belonging
 * to the programme) are the service's, because they hold for every caller.
 */
class SimpanCpmkRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The CpmkVersi policy decides; the controller calls it.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cpmk' => ['present', 'array', 'max:20'],
            'cpmk.*.kode' => ['required', 'string', 'max:16'],
            'cpmk.*.rumusan' => ['required', 'string', 'max:1000'],
            'cpmk.*.cpl' => ['array', 'max:12'],
            'cpmk.*.cpl.*.prodi_cpl_id' => ['nullable', 'integer'],
            'cpmk.*.cpl.*.bobot' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cpmk.*.sub' => ['array', 'max:16'],
            'cpmk.*.sub.*.kode' => ['nullable', 'string', 'max:24'],
            'cpmk.*.sub.*.rumusan' => ['nullable', 'string', 'max:1000'],
            'cpmk.*.sub.*.bobot' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cpmk.*.kode.required' => 'Setiap CPMK harus berkode.',
            'cpmk.*.rumusan.required' => 'Setiap CPMK harus berumusan.',
        ];
    }

    public function rumusan(): RumusanCpmkData
    {
        return RumusanCpmkData::fromArray((array) $this->validated('cpmk', []));
    }
}
