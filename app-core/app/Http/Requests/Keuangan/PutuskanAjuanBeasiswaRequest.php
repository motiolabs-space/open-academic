<?php

declare(strict_types=1);

namespace App\Http\Requests\Keuangan;

use App\Models\Keuangan\BeasiswaAjuan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Deciding an application. A refusal must say why; a grant may carry the
 * decree number and an end term, exactly as a direct award does.
 */
class PutuskanAjuanBeasiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $ajuan = $this->route('ajuan');

        return $aktor !== null
            && $ajuan instanceof BeasiswaAjuan
            && Gate::forUser($aktor)->allows('decide', $ajuan);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tetapkan' => ['required', 'boolean'],
            'tahun_akademik_selesai_id' => ['nullable', 'integer', Rule::exists('tahun_akademik', 'id')->whereNull('deleted_at')],
            'nomor_sk' => ['nullable', 'string', 'max:64'],
            'catatan' => ['nullable', 'required_if:tetapkan,0', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'catatan.required_if' => __('swalayan.beasiswa.galat.catatan_tolak_wajib'),
        ];
    }

    public function catatan(): ?string
    {
        return $this->filled('catatan') ? trim((string) $this->string('catatan')) : null;
    }
}
