<?php

declare(strict_types=1);

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'helpdesk_kategori_id' => [
                'nullable',
                'integer',
                Rule::exists('helpdesk_kategori', 'id')->whereNull('deleted_at'),
            ],
            'pertanyaan' => ['required', 'string', 'max:255'],
            'jawaban' => ['required', 'string', 'max:10000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function nilai(): array
    {
        $data = $this->validated();

        return [
            'helpdesk_kategori_id' => $data['helpdesk_kategori_id'] ?? null,
            'pertanyaan' => $data['pertanyaan'],
            'jawaban' => $data['jawaban'],
            'urutan' => (int) ($data['urutan'] ?? 0),
            'is_published' => (bool) ($data['is_published'] ?? false),
        ];
    }
}
