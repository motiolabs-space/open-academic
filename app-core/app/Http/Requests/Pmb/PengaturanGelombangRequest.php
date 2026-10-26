<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\DTOs\Pmb\PengaturanGelombangData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PengaturanGelombangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('gelombang')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_daring' => ['nullable', 'boolean'],
            'dokumen_wajib' => ['nullable', 'array'],
            'dokumen_wajib.*' => [Rule::in(array_keys((array) config('berkas.pmb')))],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'dokumen_wajib' => __('pmb.admin.dokumen_wajib'),
            'keterangan' => __('pmb.admin.keterangan'),
        ];
    }

    public function dto(): PengaturanGelombangData
    {
        return PengaturanGelombangData::fromRequest($this);
    }
}
