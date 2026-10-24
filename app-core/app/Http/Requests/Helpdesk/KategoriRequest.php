<?php

declare(strict_types=1);

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KategoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'unit_kerja_id' => [
                'nullable',
                'integer',
                Rule::exists('unit_kerja', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],

            // Null inherits the configured default; zero means no promise.
            'sla_jam' => ['nullable', 'integer', 'min:0', 'max:720'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function nilai(): array
    {
        $data = $this->validated();

        return [
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'unit_kerja_id' => $data['unit_kerja_id'] ?? null,
            'sla_jam' => isset($data['sla_jam']) ? (int) $data['sla_jam'] : null,
            'urutan' => (int) ($data['urutan'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
