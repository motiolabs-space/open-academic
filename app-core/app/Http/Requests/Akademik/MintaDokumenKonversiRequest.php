<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Models\Akademik\KonversiKredit;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** The assessor asking the student for a document on a pending conversion. */
class MintaDokumenKonversiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $konversi = $this->route('konversi');

        return $aktor !== null
            && $konversi instanceof KonversiKredit
            && Gate::forUser($aktor)->allows('mintaDokumen', $konversi);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dokumen_diminta' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
