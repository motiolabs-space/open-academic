<?php

declare(strict_types=1);

namespace App\Http\Requests\Helpdesk;

use App\Enums\StatusTiket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StatusTiket::class)],

            // Closing without a word leaves the applicant guessing whether
            // they were helped or dismissed. The service refuses it too.
            'catatan' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === StatusTiket::Ditutup->value),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'catatan.required' => __('helpdesk.validasi.alasan_tutup'),
        ];
    }

    public function status(): StatusTiket
    {
        return StatusTiket::from((string) $this->input('status'));
    }

    public function catatan(): ?string
    {
        return $this->filled('catatan') ? trim((string) $this->string('catatan')) : null;
    }
}
