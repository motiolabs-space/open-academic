<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A score sheet from the campus's CBT.
 *
 * `txt` alongside `csv` because a CSV is plain text, and that is how most
 * servers' content sniffing reports one.
 */
class ImporNilaiSeleksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('gelombang')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'berkas' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['berkas' => __('pmb.admin.berkas_csv')];
    }

    public function isi(): string
    {
        return (string) file_get_contents($this->file('berkas')->getRealPath());
    }
}
