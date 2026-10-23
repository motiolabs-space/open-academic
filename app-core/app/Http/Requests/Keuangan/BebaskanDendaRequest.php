<?php

declare(strict_types=1);

namespace App\Http\Requests\Keuangan;

use App\Models\Keuangan\Tagihan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BebaskanDendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tagihan = $this->route('tagihan');
        $aktor = Portal::user();

        return $tagihan instanceof Tagihan
            && $aktor !== null
            && Gate::forUser($aktor)->allows('waiveLateFee', $tagihan);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Forgiving money owed is the same class of decision as a
            // discretionary reduction, so the reason is mandatory and must be
            // long enough to mean something to whoever audits it.
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alasan.required' => __('keuangan.denda.alasan_wajib'),
            'alasan.min' => __('keuangan.denda.alasan_singkat'),
        ];
    }

    public function alasan(): string
    {
        return trim((string) $this->string('alasan'));
    }
}
