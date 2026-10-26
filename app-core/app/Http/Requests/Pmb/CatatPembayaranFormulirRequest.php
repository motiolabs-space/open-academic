<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\DTOs\Pmb\PembayaranFormulirData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatatPembayaranFormulirRequest extends FormRequest
{
    public const CHANNEL = ['transfer', 'tunai', 'qris'];

    public function authorize(): bool
    {
        return $this->user()?->can('recordPayment', $this->route('pendaftar')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nominal' => ['required', 'integer', 'min:1'],
            'channel' => ['required', Rule::in(self::CHANNEL)],
            'referensi' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nominal' => __('pmb.admin.nominal'),
            'channel' => __('pmb.admin.channel'),
            'referensi' => __('pmb.admin.referensi'),
        ];
    }

    public function dto(): PembayaranFormulirData
    {
        return PembayaranFormulirData::fromRequest($this);
    }
}
