<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\Akademik\KoreksiNilaiData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A correction to a finalised grade: the total, or the component scores.
 *
 * The reason is the one field that is never optional — it is the only thing
 * left explaining the change when somebody asks about it a year later.
 */
class KoreksiNilaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // nilai.manage is checked by the controller, as before.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nilai_angka' => ['nullable', 'required_without:komponen', 'numeric', 'min:0', 'max:100'],
            'komponen' => ['nullable', 'array'],
            'komponen.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Koreksi nilai final wajib disertai alasan — inilah satu-satunya '
                .'keterangan yang tersisa bila suatu saat perubahan ini dipertanyakan.',
            'alasan.min' => 'Alasan terlalu singkat untuk berguna enam bulan dari sekarang.',
            'komponen.*.max' => 'Nilai komponen tidak boleh lebih dari 100.',
            'komponen.*.min' => 'Nilai komponen tidak boleh kurang dari 0.',
        ];
    }

    public function koreksi(): KoreksiNilaiData
    {
        return KoreksiNilaiData::fromRequest($this);
    }
}
