<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Support\Portal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** An even split of one cohort across the lecturers ticked on the screen. */
class BagiDosenWaliRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('assignAdvisor', Mahasiswa::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prodi' => ['required', 'integer', Rule::exists('prodi', 'id')],
            'angkatan' => ['nullable', 'integer', 'min:1900', 'max:2999'],
            'hanya_tanpa_wali' => ['sometimes', 'boolean'],
            'dosen' => ['required', 'array', 'min:1'],
            'dosen.*' => ['uuid', Rule::exists('dosen', 'uuid')->where('prodi_id', $this->integer('prodi'))->whereNull('deleted_at')],
            'alasan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dosen.required' => __('dosen_wali.galat.tanpa_dosen'),
            'dosen.*.exists' => __('dosen_wali.galat.dosen_bukan_prodi'),
        ];
    }

    public function prodi(): Prodi
    {
        return Prodi::query()->findOrFail($this->integer('prodi'));
    }

    /** @return Collection<int, Dosen> */
    public function dosen(): Collection
    {
        return Dosen::query()->whereIn('uuid', (array) $this->validated('dosen'))->get();
    }
}
