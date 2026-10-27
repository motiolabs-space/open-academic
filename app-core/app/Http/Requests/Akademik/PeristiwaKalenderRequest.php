<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\DTOs\Akademik\PeristiwaKalenderData;
use App\Enums\JenisPeristiwa;
use App\Enums\SasaranPeristiwa;
use App\Enums\StatusPeristiwa;
use App\Models\Akademik\KalenderPeristiwa;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A holiday, activity or deadline, new or edited.
 *
 * Only the three kinds the calendar owns are accepted. KalenderService refuses
 * the others too; refusing them here keeps the error on the field.
 */
class PeristiwaKalenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $peristiwa = $this->route('peristiwa');

        if ($aktor === null) {
            return false;
        }

        return $peristiwa instanceof KalenderPeristiwa
            ? Gate::forUser($aktor)->allows('update', $peristiwa)
            : Gate::forUser($aktor)->allows('create', KalenderPeristiwa::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $manual = array_map(fn (JenisPeristiwa $j): string => $j->value, JenisPeristiwa::kasusManual());

        return [
            'judul' => ['required', 'string', 'max:160'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'jenis' => ['required', Rule::in($manual)],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'sasaran' => ['required', Rule::enum(SasaranPeristiwa::class)],
            'prodi_id' => ['nullable', 'integer', Rule::exists('prodi', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(StatusPeristiwa::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'judul' => __('kalender.field.judul'),
            'keterangan' => __('kalender.field.keterangan'),
            'jenis' => __('kalender.field.jenis'),
            'tanggal_mulai' => __('kalender.field.tanggal_mulai'),
            'tanggal_selesai' => __('kalender.field.tanggal_selesai'),
            'sasaran' => __('kalender.field.sasaran'),
            'prodi_id' => __('kalender.field.prodi'),
            'status' => __('kalender.field.status'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tanggal_selesai.after_or_equal' => __('kalender.galat.rentang'),
        ];
    }

    public function isian(): PeristiwaKalenderData
    {
        return PeristiwaKalenderData::fromArray($this->validated());
    }
}
