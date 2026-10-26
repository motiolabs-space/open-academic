<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\DTOs\Pmb\TawaranProdiData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TawaranProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('gelombang')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prodi_id' => ['required', 'integer', Rule::exists('prodi', 'id')],
            'daya_tampung' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'biaya_formulir' => ['nullable', 'integer', 'min:0'],
            'ambang_lulus' => ['nullable', 'numeric', 'min:0', 'max:'.(int) config('pmb.impor.nilai_maks')],
            'dokumen_wajib' => ['nullable', 'array'],
            'dokumen_wajib.*' => [Rule::in(array_keys((array) config('berkas.pmb')))],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'prodi_id' => __('pmb.admin.prodi'),
            'daya_tampung' => __('pmb.admin.daya_tampung'),
            'biaya_formulir' => __('pmb.admin.biaya_formulir'),
            'ambang_lulus' => __('pmb.admin.ambang_lulus'),
        ];
    }

    public function dto(): TawaranProdiData
    {
        return TawaranProdiData::fromRequest($this);
    }
}
