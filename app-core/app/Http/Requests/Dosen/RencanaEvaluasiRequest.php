<?php

declare(strict_types=1);

namespace App\Http\Requests\Dosen;

use App\Enums\JenisEvaluasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A class's evaluation plan as the lecturer submits it.
 *
 * Every component needs a kind, not only a name and a weight: the kind is
 * what PDDIKTI classifies, and what decides whether a case-method class is
 * recognised for IKU 7. A plan that validates here is one Feeder will accept.
 */
class RencanaEvaluasiRequest extends FormRequest
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
            'komponen' => ['required', 'array', 'min:1', 'max:12'],
            'komponen.*.id' => ['nullable', 'integer'],
            'komponen.*.nama' => ['required', 'string', 'max:64', 'distinct:ignore_case'],
            'komponen.*.bobot' => ['required', 'integer', 'min:0', 'max:100'],
            'komponen.*.jenis' => ['required', Rule::enum(JenisEvaluasi::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'komponen.required' => 'Rencana evaluasi membutuhkan minimal satu komponen.',
            'komponen.*.nama.required' => 'Setiap komponen harus bernama.',
            'komponen.*.nama.distinct' => 'Nama komponen tidak boleh berulang dalam satu kelas.',
            'komponen.*.bobot.required' => 'Setiap komponen harus punya bobot.',
            'komponen.*.jenis.required' => 'Setiap komponen harus punya jenis evaluasi — PDDIKTI mewajibkannya, dan jenis inilah yang menentukan pengakuan IKU 7.',
            'komponen.*.jenis.Illuminate\Validation\Rules\Enum' => 'Jenis evaluasi tidak dikenal.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $total = array_sum(array_map(
                fn (array $k): int => (int) ($k['bobot'] ?? 0),
                (array) $this->input('komponen', []),
            ));

            if ($total !== 100) {
                $v->errors()->add('komponen', "Jumlah bobot seluruh komponen harus 100%, saat ini {$total}%.");
            }
        });
    }

    /**
     * The plan as the service wants it.
     *
     * @return array<int, array{id: int|null, nama: string, bobot: int, jenis: string}>
     */
    public function rencana(): array
    {
        return array_values(array_map(fn (array $k): array => [
            'id' => isset($k['id']) && $k['id'] !== '' ? (int) $k['id'] : null,
            'nama' => trim((string) $k['nama']),
            'bobot' => (int) $k['bobot'],
            'jenis' => (string) $k['jenis'],
        ], $this->validated('komponen')));
    }
}
