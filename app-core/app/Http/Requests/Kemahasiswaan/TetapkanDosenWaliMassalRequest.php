<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Row-by-row assignment: `penetapan[<student uuid>] = <lecturer uuid>`.
 *
 * Rows left on "no change" arrive empty and are dropped here, so the service
 * sees only what the registrar actually picked.
 */
class TetapkanDosenWaliMassalRequest extends FormRequest
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
            'penetapan' => ['required', 'array', 'max:500'],
            'penetapan.*' => ['nullable', 'uuid'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<int, int> mahasiswa id => dosen id */
    public function penetapan(): array
    {
        $pilihan = array_filter((array) $this->validated('penetapan'), fn ($v): bool => filled($v));

        if ($pilihan === []) {
            return [];
        }

        $mahasiswa = Mahasiswa::query()->whereIn('uuid', array_keys($pilihan))->pluck('id', 'uuid');
        $dosen = Dosen::query()->whereIn('uuid', array_values($pilihan))->pluck('id', 'uuid');

        $hasil = [];

        foreach ($pilihan as $mahasiswaUuid => $dosenUuid) {
            if (isset($mahasiswa[$mahasiswaUuid], $dosen[$dosenUuid])) {
                $hasil[(int) $mahasiswa[$mahasiswaUuid]] = (int) $dosen[$dosenUuid];
            }
        }

        return $hasil;
    }

    public function alasan(): ?string
    {
        return $this->filled('alasan') ? trim((string) $this->string('alasan')) : null;
    }
}
