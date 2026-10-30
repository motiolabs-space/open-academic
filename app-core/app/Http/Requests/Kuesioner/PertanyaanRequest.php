<?php

declare(strict_types=1);

namespace App\Http\Requests\Kuesioner;

use App\DTOs\Kuesioner\PertanyaanData;
use App\Enums\TipePertanyaan;
use App\Models\Kuesioner\Kuesioner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PertanyaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Kuesioner::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'teks' => ['required', 'string', 'max:1000'],
            'tipe' => ['required', Rule::enum(TipePertanyaan::class)],
            'opsi' => [Rule::requiredIf($this->input('tipe') === TipePertanyaan::Pilihan->value), 'nullable', 'string', 'max:2000'],
            'wajib' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['opsi.required' => __('kuesioner.galat.opsi_kurang')];
    }

    public function toDto(): PertanyaanData
    {
        return PertanyaanData::fromRequest($this);
    }
}
