<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\AsalPendidikanData;
use App\Enums\JenisDaftar;
use App\Http\Requests\Kemahasiswaan\Concerns\AturanAsalPendidikan;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Correcting how an existing student entered.
 *
 * Every kind is accepted, not only the transfer ones: the correction most
 * often needed is a student registered through the ordinary daftar ulang who
 * was in fact a transfer, and the reverse mistake has to be undoable too.
 */
class PerbaruiAsalRequest extends FormRequest
{
    use AturanAsalPendidikan;

    public function authorize(): bool
    {
        $mahasiswa = $this->route('mahasiswa');
        $aktor = Portal::user();

        return $mahasiswa instanceof Mahasiswa
            && $aktor !== null
            && Gate::forUser($aktor)->allows('update', $mahasiswa);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $mahasiswa = $this->route('mahasiswa');

        return $this->aturanAsal(
            array_keys(JenisDaftar::optionsIntake()),
            $mahasiswa instanceof Mahasiswa ? $mahasiswa->term_masuk : null,
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->pesanAsal();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return $this->atributAsal();
    }

    public function toDto(): AsalPendidikanData
    {
        return AsalPendidikanData::fromRequest($this);
    }
}
