<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Enums\StudentActivityType;
use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** A KKN/PKL programme run, new or edited. */
class KegiatanLapanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $kegiatan = $this->route('kegiatan');

        if ($aktor === null) {
            return false;
        }

        return $kegiatan instanceof KegiatanLapangan
            ? Gate::forUser($aktor)->allows('update', $kegiatan)
            : Gate::forUser($aktor)->allows('create', KegiatanLapangan::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'jenis' => ['required', Rule::enum(StudentActivityType::class)->only(StudentActivityType::kegiatanLapangan())],
            'nama' => ['required', 'string', 'max:255'],
            'nomor_sk' => ['nullable', 'string', 'max:100'],
            'tanggal_sk' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'pendaftaran_dibuka' => ['sometimes', 'boolean'],
            'min_sks' => ['required', 'integer', 'min:0', 'max:300'],
            'ukuran_kelompok' => ['required', 'integer', 'min:2', 'max:50'],
            'campur_prodi' => ['sometimes', 'boolean'],
            'campur_jenis_kelamin' => ['sometimes', 'boolean'],
            'bobot_mitra' => ['required', 'integer', 'min:0', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('kegiatan_lapangan.field');
    }
}
