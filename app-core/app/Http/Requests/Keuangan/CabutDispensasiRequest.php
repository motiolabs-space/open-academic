<?php

declare(strict_types=1);

namespace App\Http\Requests\Keuangan;

use App\Models\Keuangan\DispensasiTagihan;
use App\Models\Keuangan\Tagihan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CabutDispensasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $dispensasi = $this->route('dispensasi');

        return $aktor !== null
            && $dispensasi instanceof DispensasiTagihan
            && Gate::forUser($aktor)->allows('grantDispensation', Tagihan::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alasan.required' => __('keuangan.dispensasi.galat.alasan_wajib'),
            'alasan.min' => __('keuangan.dispensasi.galat.alasan_singkat'),
        ];
    }

    public function alasan(): string
    {
        return trim((string) $this->string('alasan'));
    }
}
