<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Akademik\KelasKuliah;
use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The class whose course the programme's marks are converted into. */
class KonversiKegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $kegiatan = $this->route('kegiatan');

        return $aktor !== null
            && $kegiatan instanceof KegiatanLapangan
            && Gate::forUser($aktor)->allows('konversi', $kegiatan);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var KegiatanLapangan $kegiatan */
        $kegiatan = $this->route('kegiatan');

        return [
            'kelas_kuliah_id' => ['required', 'uuid', Rule::exists('kelas_kuliah', 'uuid')
                ->where('tahun_akademik_id', $kegiatan->tahun_akademik_id)
                ->whereNull('deleted_at')],
        ];
    }

    public function kelas(): KelasKuliah
    {
        return KelasKuliah::query()->where('uuid', $this->validated('kelas_kuliah_id'))->firstOrFail();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('kegiatan_lapangan.field');
    }
}
