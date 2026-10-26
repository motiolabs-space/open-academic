<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\KegiatanLapanganKelompok;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Moving a participant into another group of the same programme, or out of
 * every group. An empty target means "not in a group".
 */
class PindahAnggotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $aktivitas = $this->route('aktivitas');

        return $aktor !== null
            && $aktivitas instanceof AktivitasMahasiswa
            && $aktivitas->kegiatanLapangan !== null
            && Gate::forUser($aktor)->allows('update', $aktivitas->kegiatanLapangan);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var AktivitasMahasiswa $aktivitas */
        $aktivitas = $this->route('aktivitas');

        return [
            'kelompok' => ['nullable', 'uuid', Rule::exists('kegiatan_lapangan_kelompok', 'uuid')
                ->where('kegiatan_lapangan_id', $aktivitas->kegiatan_lapangan_id)
                ->whereNull('deleted_at')],
        ];
    }

    public function tujuan(): ?KegiatanLapanganKelompok
    {
        $uuid = $this->validated('kelompok');

        return filled($uuid) ? KegiatanLapanganKelompok::query()->where('uuid', $uuid)->firstOrFail() : null;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('kegiatan_lapangan.field');
    }
}
