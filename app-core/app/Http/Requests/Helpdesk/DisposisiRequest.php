<?php

declare(strict_types=1);

namespace App\Http\Requests\Helpdesk;

use App\DTOs\Helpdesk\DisposisiData;
use App\Enums\PrioritasTiket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisposisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // One of the two must be named — a disposition to nobody is not one.
            'unit_kerja_id' => [
                'required_without:petugas_staff_id',
                'nullable',
                'integer',
                Rule::exists('unit_kerja', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'petugas_staff_id' => [
                'nullable',
                'integer',
                Rule::exists('staff', 'id')->whereNull('deleted_at'),
            ],
            'prioritas' => ['nullable', Rule::enum(PrioritasTiket::class)],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'unit_kerja_id.required_without' => __('helpdesk.validasi.disposisi_kosong'),
        ];
    }

    public function toDto(): DisposisiData
    {
        return DisposisiData::fromRequest($this);
    }
}
