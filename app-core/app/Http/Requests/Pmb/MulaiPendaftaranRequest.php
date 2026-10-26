<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\Models\Akademik\Prodi;
use App\Models\Pmb\PmbAkun;
use App\Models\Pmb\PmbGelombang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Choosing a wave and programmes.
 *
 * Shape only. Whether the wave is open and offers those programmes is
 * PendaftaranPmbService's to refuse, under the lock that also hands out the
 * registration number.
 */
class MulaiPendaftaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof PmbAkun;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'gelombang' => ['required', 'uuid', Rule::exists('pmb_gelombang', 'uuid')->whereNull('deleted_at')],
            'prodi_pilihan_1_id' => ['required', 'integer', Rule::exists('prodi', 'id')],
            'prodi_pilihan_2_id' => ['nullable', 'integer', 'different:prodi_pilihan_1_id', Rule::exists('prodi', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['prodi_pilihan_2_id.different' => __('pmb.validasi.pilihan_sama')];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'gelombang' => __('pmb.kolom.gelombang'),
            'prodi_pilihan_1_id' => __('pmb.kolom.prodi_pilihan_1_id'),
            'prodi_pilihan_2_id' => __('pmb.kolom.prodi_pilihan_2_id'),
        ];
    }

    public function gelombang(): PmbGelombang
    {
        return PmbGelombang::where('uuid', (string) $this->input('gelombang'))->firstOrFail();
    }

    public function pilihan1(): Prodi
    {
        return Prodi::findOrFail($this->integer('prodi_pilihan_1_id'));
    }

    public function pilihan2(): ?Prodi
    {
        return $this->filled('prodi_pilihan_2_id') ? Prodi::findOrFail($this->integer('prodi_pilihan_2_id')) : null;
    }
}
