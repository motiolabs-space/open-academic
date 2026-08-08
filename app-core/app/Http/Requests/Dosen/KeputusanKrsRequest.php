<?php

declare(strict_types=1);

namespace App\Http\Requests\Dosen;

use App\DTOs\Akademik\KeputusanWaliData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KeputusanKrsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The policy on the Krs model decides; the route already resolves it.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disetujui' => ['required', 'boolean'],

            // A rejection the student cannot act on is worse than no decision,
            // so the note is mandatory whenever the answer is no.
            'catatan' => [
                Rule::requiredIf(fn (): bool => !$this->boolean('disetujui')),
                'nullable',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'catatan.required' => 'Penolakan wajib disertai catatan agar mahasiswa tahu apa yang harus diperbaiki.',
            'catatan.min' => 'Catatan terlalu singkat untuk bisa ditindaklanjuti mahasiswa.',
        ];
    }

    public function toDto(): KeputusanWaliData
    {
        return KeputusanWaliData::fromRequest($this);
    }
}
