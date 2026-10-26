<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\Models\Pmb\PmbBerkas;
use Illuminate\Foundation\Http\FormRequest;

/** Sending a document back to the applicant. The reason is what they read. */
class KembalikanBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PmbBerkas $berkas */
        $berkas = $this->route('berkas');

        return $this->user()?->can('manage', $berkas->pendaftar) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['catatan' => ['required', 'string', 'max:255']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['catatan' => __('pmb.admin.kembalikan_alasan')];
    }
}
