<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\KegiatanLapanganLogbook;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** The field supervisor signing off a log entry. */
class PeriksaLogbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $logbook = $this->route('logbook');

        return $aktor !== null
            && $logbook instanceof KegiatanLapanganLogbook
            && Gate::forUser($aktor)->allows('periksa', $logbook);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['catatan' => ['nullable', 'string', 'max:2000']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('kegiatan_lapangan.field');
    }
}
