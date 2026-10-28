<?php

declare(strict_types=1);

namespace App\Http\Requests\Keuangan;

use App\Models\Keuangan\Tagihan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** A student asking for a dispensation on their own invoice. */
class AjukanDispensasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $tagihan = $this->route('tagihan');

        return $aktor !== null
            && $tagihan instanceof Tagihan
            && Gate::forUser($aktor)->allows('requestDispensation', $tagihan);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sampai' => ['required', 'date', 'after:today'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sampai.after' => __('keuangan.dispensasi.galat.tanggal_lampau'),
            'alasan.required' => __('keuangan.dispensasi.galat.alasan_wajib'),
            'alasan.min' => __('keuangan.dispensasi.galat.alasan_singkat'),
        ];
    }
}
