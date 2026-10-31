<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Models\Akademik\KonversiKredit;
use App\Services\Berkas\BerkasService;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** A student sending the document the assessor asked for. */
class UnggahDokumenKonversiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $konversi = $this->route('konversi');

        return $aktor !== null
            && $konversi instanceof KonversiKredit
            && Gate::forUser($aktor)->allows('unggahDokumen', $konversi);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'berkas' => app(BerkasService::class)->aturan('dokumen'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'berkas.mimes' => __('swalayan.galat.berkas_jenis'),
        ];
    }
}
