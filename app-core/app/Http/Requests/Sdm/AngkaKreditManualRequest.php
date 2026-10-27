<?php

declare(strict_types=1);

namespace App\Http\Requests\Sdm;

use App\DTOs\Sdm\AngkaKreditManualData;
use App\Enums\SumberAngkaKredit;
use App\Enums\UnsurAngkaKredit;
use App\Models\Sdm\AngkaKreditDosen;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A credit line entered by the personnel office.
 *
 * Shape only. Whether a converted semester is really before 2026, or already
 * credited from an endorsed BKD, is AngkaKreditService's to refuse.
 */
class AngkaKreditManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('create', AngkaKreditDosen::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'sumber' => ['required', Rule::in([SumberAngkaKredit::KonversiManual->value, SumberAngkaKredit::Penyesuaian->value])],
            'unsur' => ['required', Rule::enum(UnsurAngkaKredit::class)],
            'semester_kode' => ['nullable', 'required_if:sumber,konversi_manual', 'regex:/^\d{4}[123]$/'],
            'sks' => ['nullable', 'required_if:sumber,konversi_manual', 'numeric', 'min:0.01', 'max:40'],
            'nilai' => ['nullable', 'required_if:sumber,penyesuaian', 'numeric', 'min:-2000', 'max:2000', 'not_in:0'],
            'tanggal' => ['nullable', 'required_if:sumber,penyesuaian', 'date'],
            'keterangan' => ['nullable', 'required_if:sumber,penyesuaian', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect(array_keys($this->rules()))
            ->mapWithKeys(fn (string $f): array => [$f => mb_strtolower((string) __('angka_kredit.field.'.$f))])
            ->all();
    }

    public function toDto(): AngkaKreditManualData
    {
        return AngkaKreditManualData::fromRequest($this);
    }
}
