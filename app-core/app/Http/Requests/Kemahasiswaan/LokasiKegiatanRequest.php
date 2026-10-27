<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Enums\JenisLokasiKegiatan;
use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** A village or partner institution. */
class LokasiKegiatanRequest extends FormRequest
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
        return [
            'jenis' => ['required', Rule::enum(JenisLokasiKegiatan::class)],
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kabupaten' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kuota_kelompok' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('kegiatan_lapangan.field');
    }
}
