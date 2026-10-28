<?php

declare(strict_types=1);

namespace App\Http\Requests\Keuangan;

use App\Models\Keuangan\DispensasiTagihan;
use App\Models\Keuangan\Tagihan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Deciding a student's request. A refusal must say why; an approval may move
 * the date the student asked for.
 */
class PutuskanDispensasiRequest extends FormRequest
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
            'setujui' => ['required', 'boolean'],
            'sampai' => ['nullable', 'date', 'after:today'],
            'catatan' => ['nullable', 'required_if:setujui,0', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sampai.after' => __('keuangan.dispensasi.galat.tanggal_lampau'),
            'catatan.required_if' => __('keuangan.dispensasi.galat.catatan_tolak_wajib'),
        ];
    }

    public function sampai(): ?Carbon
    {
        return $this->filled('sampai') ? Carbon::parse((string) $this->string('sampai'))->startOfDay() : null;
    }

    public function catatan(): ?string
    {
        return $this->filled('catatan') ? trim((string) $this->string('catatan')) : null;
    }
}
