<?php

declare(strict_types=1);

namespace App\Http\Requests\Keuangan;

use App\Models\Keuangan\Tagihan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** A dispensation granted directly by the finance office, by invoice number. */
class BeriDispensasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('grantDispensation', Tagihan::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tagihan' => ['required', 'string', 'max:64', Rule::exists('tagihan', 'nomor')->whereNull('deleted_at')],
            'sampai' => ['required', 'date', 'after:today'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tagihan.exists' => __('keuangan.dispensasi.galat.tagihan_tidak_ada'),
            'sampai.after' => __('keuangan.dispensasi.galat.tanggal_lampau'),
            'alasan.required' => __('keuangan.dispensasi.galat.alasan_wajib'),
            'alasan.min' => __('keuangan.dispensasi.galat.alasan_singkat'),
        ];
    }

    public function tagihan(): Tagihan
    {
        return Tagihan::query()->where('nomor', $this->validated('tagihan'))->firstOrFail();
    }
}
