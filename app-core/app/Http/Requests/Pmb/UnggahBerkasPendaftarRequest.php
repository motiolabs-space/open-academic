<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\Models\Pmb\PmbPendaftar;
use App\Services\Berkas\BerkasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One document from the applicant. The type and size limits are the ones
 * every upload in the system inherits from BerkasService.
 */
class UnggahBerkasPendaftarRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PmbPendaftar $pendaftar */
        $pendaftar = $this->route('pendaftar');

        return $this->user()?->can('update', $pendaftar) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'jenis' => ['required', Rule::in(array_keys((array) config('berkas.pmb')))],
            'berkas' => app(BerkasService::class)->aturan('dokumen'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'berkas.mimes' => 'Hanya PDF atau gambar (JPG/PNG) yang diterima.',
            'berkas.max' => 'Ukuran berkas melebihi batas '.config('berkas.maks_kb').' KB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'jenis' => __('pmb.kolom.jenis'),
            'berkas' => __('pmb.kolom.berkas'),
        ];
    }
}
