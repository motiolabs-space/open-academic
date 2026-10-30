<?php

declare(strict_types=1);

namespace App\Http\Requests\Kuesioner;

use App\DTOs\Kuesioner\KuesionerData;
use App\Enums\SasaranKuesioner;
use App\Models\Kuesioner\Kuesioner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Creating or editing a questionnaire's header. */
class KuesionerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Kuesioner::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Kuesioner|null $kuesioner */
        $kuesioner = $this->route('kuesioner');

        return [
            'kode' => [
                'required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('kuesioner', 'kode')->ignore($kuesioner?->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'sasaran' => ['required', Rule::enum(SasaranKuesioner::class)],
            'prodi_id' => ['nullable', 'integer', Rule::exists('prodi', 'id')],
            'anonim' => ['sometimes', 'boolean'],
            'min_responden' => ['nullable', 'integer', 'min:1', 'max:500'],
            'mulai' => ['nullable', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kode.regex' => __('kuesioner.validasi.kode'),
            'kode.unique' => __('kuesioner.validasi.kode_dipakai'),
            'selesai.after_or_equal' => __('kuesioner.validasi.periode'),
        ];
    }

    public function toDto(): KuesionerData
    {
        return KuesionerData::fromRequest($this);
    }
}
