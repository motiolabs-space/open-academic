<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** One student's advisor, from the advisor screen or the student's page. */
class TetapkanDosenWaliRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $mahasiswa = $this->route('mahasiswa');

        return $aktor !== null
            && $mahasiswa instanceof Mahasiswa
            && Gate::forUser($aktor)->allows('assignAdvisor', $mahasiswa);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dosen' => ['required', 'uuid', Rule::exists('dosen', 'uuid')->whereNull('deleted_at')],
            'alasan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function dosen(): Dosen
    {
        return Dosen::query()->where('uuid', $this->validated('dosen'))->firstOrFail();
    }

    public function alasan(): ?string
    {
        return $this->filled('alasan') ? trim((string) $this->string('alasan')) : null;
    }
}
