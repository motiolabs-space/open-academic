<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\HasilUjiKompetensi;
use App\Models\Sdm\Staff;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A competency-examination result, recorded from the yudisium screen.
 *
 * The same permission that confirms a yudisium: a passing result graduates
 * the student, which is the same decision taken a second time.
 */
class CatatUjiKompetensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor instanceof Staff && $aktor->hasPermissionTo('wisuda.manage', 'staff');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tanggal_ujian' => ['required', 'date', 'before_or_equal:today'],
            'hasil' => ['required', Rule::enum(HasilUjiKompetensi::class)],
            'nomor_sertifikat' => ['nullable', 'string', 'max:64'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
