<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** NIMs to enrol, one per line or separated by commas. */
class PesertaKegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $kegiatan = $this->route('kegiatan');

        return $aktor !== null
            && $kegiatan instanceof KegiatanLapangan
            && Gate::forUser($aktor)->allows('update', $kegiatan);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['nim' => ['required', 'string', 'max:20000']];
    }

    /** @return array<int, string> */
    public function daftarNim(): array
    {
        return preg_split('/[\s,;]+/', (string) $this->validated('nim'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('kegiatan_lapangan.field');
    }
}
