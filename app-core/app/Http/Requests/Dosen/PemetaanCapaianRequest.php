<?php

declare(strict_types=1);

namespace App\Http\Requests\Dosen;

use App\DTOs\Akademik\PemetaanCapaianData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The component × outcome matrix of a class's evaluation plan.
 *
 * `porsi[komponen id][target]` — target "s:{sub_cpmk}" or "c:{cpmk}". Whether
 * each row closes to 100 and each target belongs to the class's version is
 * the service's rule.
 */
class PemetaanCapaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The class policy decides; the controller calls it.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'porsi' => ['present', 'array'],
            'porsi.*' => ['array'],
            'porsi.*.*' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'porsi.*.*.integer' => 'Porsi pemetaan berupa bilangan bulat persen.',
            'porsi.*.*.max' => 'Porsi pemetaan tidak boleh lebih dari 100%.',
        ];
    }

    public function pemetaan(): PemetaanCapaianData
    {
        return PemetaanCapaianData::fromArray((array) $this->validated('porsi', []));
    }
}
