<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Enums\JenisCuti;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Services\Berkas\BerkasService;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A student applying for leave from the portal.
 *
 * No student field: the applicant is whoever is signed in. Which terms may be
 * chosen is the service's rule, not this form's.
 */
class AjukanCutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('create', CutiMahasiswa::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')->whereNull('deleted_at')],
            'jenis' => ['required', Rule::enum(JenisCuti::class)],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
            'dokumen' => app(BerkasService::class)->aturan('dokumen', wajib: false),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alasan.required' => __('swalayan.cuti.galat.alasan_wajib'),
            'alasan.min' => __('swalayan.cuti.galat.alasan_singkat'),
            'dokumen.mimes' => __('swalayan.galat.berkas_jenis'),
        ];
    }
}
