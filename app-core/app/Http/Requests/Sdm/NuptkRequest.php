<?php

declare(strict_types=1);

namespace App\Http\Requests\Sdm;

use App\Models\Sdm\Dosen;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Setting a lecturer's NUPTK from the credit ledger.
 *
 * Lives beside the ledger because that is where its absence shows: a lecturer
 * without one is left out of the penyetaraan sheet, and the fix belongs on the
 * screen that says so rather than three menus away.
 */
class NuptkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $dosen = $this->route('dosen');

        return $aktor !== null && $dosen instanceof Dosen && Gate::forUser($aktor)->allows('update', $dosen);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $dosen = $this->route('dosen');

        return [
            'nuptk' => [
                'nullable', 'string', 'regex:/^\d{16}$/',
                Rule::unique('dosen', 'nuptk')->ignore($dosen instanceof Dosen ? $dosen->id : null)->whereNull('deleted_at'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['nuptk.regex' => __('angka_kredit.pesan.nuptk_format')];
    }
}
