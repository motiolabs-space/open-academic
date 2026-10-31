<?php

declare(strict_types=1);

namespace App\Http\Requests\Keuangan;

use App\Models\Keuangan\BeasiswaAjuan;
use App\Models\Keuangan\BeasiswaPeriode;
use App\Services\Berkas\BerkasService;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * A student applying to an open call. Every document the call lists is
 * required; the applicant is whoever is signed in.
 */
class AjukanBeasiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('create', BeasiswaAjuan::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $periode = $this->route('periode');
        $jumlah = $periode instanceof BeasiswaPeriode ? count($periode->daftarBerkas()) : 0;
        $aturan = app(BerkasService::class)->aturan('dokumen');

        $rules = [
            'motivasi' => ['required', 'string', 'min:20', 'max:2000'],
            'berkas' => [$jumlah > 0 ? 'required' : 'nullable', 'array', 'max:'.max($jumlah, 0)],
        ];

        for ($i = 0; $i < $jumlah; $i++) {
            $rules["berkas.{$i}"] = $aturan;
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivasi.required' => __('swalayan.beasiswa.galat.motivasi_wajib'),
            'motivasi.min' => __('swalayan.beasiswa.galat.motivasi_singkat'),
            'berkas.*.required' => __('swalayan.beasiswa.galat.berkas_wajib'),
            'berkas.*.mimes' => __('swalayan.galat.berkas_jenis'),
        ];
    }
}
