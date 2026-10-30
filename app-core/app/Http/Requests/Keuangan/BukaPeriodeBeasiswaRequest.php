<?php

declare(strict_types=1);

namespace App\Http\Requests\Keuangan;

use App\Models\Keuangan\BeasiswaAjuan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The finance office opening a call for applications under one scheme. */
class BukaPeriodeBeasiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('decide', BeasiswaAjuan::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'beasiswa' => ['required', 'uuid', Rule::exists('beasiswa', 'uuid')->whereNull('deleted_at')],
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')->whereNull('deleted_at')],
            'nama' => ['required', 'string', 'max:255'],
            'dibuka_mulai' => ['required', 'date'],
            'dibuka_sampai' => ['required', 'date', 'after_or_equal:dibuka_mulai', 'after_or_equal:today'],
            'syarat' => ['nullable', 'string', 'max:2000'],
            'ipk_minimum' => ['nullable', 'numeric', 'min:0', 'max:4'],
            // One requested document per line.
            'berkas_diminta' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dibuka_sampai.after_or_equal' => __('swalayan.beasiswa.galat.tanggal_periode'),
        ];
    }

    /** @return array<int, string> */
    public function berkasDiminta(): array
    {
        if (!$this->filled('berkas_diminta')) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (string $baris): string => mb_substr(trim($baris), 0, 120), preg_split('/\R/', (string) $this->string('berkas_diminta')) ?: []),
            fn (string $baris): bool => $baris !== '',
        ));
    }
}
