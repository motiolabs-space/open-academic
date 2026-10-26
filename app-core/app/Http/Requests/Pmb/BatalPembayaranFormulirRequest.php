<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\Models\Pmb\PmbPembayaran;
use Illuminate\Foundation\Http\FormRequest;

class BatalPembayaranFormulirRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PmbPembayaran $pembayaran */
        $pembayaran = $this->route('pembayaran');

        return $this->user()?->can('recordPayment', $pembayaran->pendaftar) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['alasan' => ['required', 'string', 'max:255']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['alasan' => __('pmb.admin.alasan_batal')];
    }
}
