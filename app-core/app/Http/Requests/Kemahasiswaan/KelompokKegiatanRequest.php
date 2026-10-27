<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Models\Kemahasiswaan\KegiatanLapanganKelompok;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A group's placement and supervisors, for a new group or an existing one.
 *
 * The location and leader are checked against the programme and the group
 * here and again in the service — the service is what tests and the seeder
 * reach.
 */
class KelompokKegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        if ($aktor === null) {
            return false;
        }

        $kelompok = $this->route('kelompok');

        if ($kelompok instanceof KegiatanLapanganKelompok) {
            return Gate::forUser($aktor)->allows('update', $kelompok);
        }

        $kegiatan = $this->route('kegiatan');

        return $kegiatan instanceof KegiatanLapangan && Gate::forUser($aktor)->allows('update', $kegiatan);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lokasi_id' => ['nullable', 'integer', Rule::exists('kegiatan_lapangan_lokasi', 'id')
                ->where('kegiatan_lapangan_id', $this->kegiatan()?->id)
                ->whereNull('deleted_at')],
            'dosen_pembimbing_id' => ['nullable', 'integer', Rule::exists('dosen', 'id')->whereNull('deleted_at')],
            'pembimbing_mitra_nama' => ['nullable', 'string', 'max:255'],
            'pembimbing_mitra_jabatan' => ['nullable', 'string', 'max:255'],
            'pembimbing_mitra_telepon' => ['nullable', 'string', 'max:32'],
            'ketua_aktivitas_id' => ['nullable', 'integer'],
        ];
    }

    public function kegiatan(): ?KegiatanLapangan
    {
        $kelompok = $this->route('kelompok');

        if ($kelompok instanceof KegiatanLapanganKelompok) {
            return $kelompok->kegiatanLapangan;
        }

        $kegiatan = $this->route('kegiatan');

        return $kegiatan instanceof KegiatanLapangan ? $kegiatan : null;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('kegiatan_lapangan.field');
    }
}
