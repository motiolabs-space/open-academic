<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\DTOs\Akademik\PenyusunanUjianData;
use App\Enums\JenisUjianKelas;
use App\Models\Akademik\JadwalUjian;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The frame of a bulk exam layout, for the preview and the save alike.
 */
class SusunJadwalUjianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('create', JadwalUjian::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sesi' => array_values(array_filter(
                (array) $this->input('sesi', []),
                fn (mixed $s): bool => is_array($s) && (filled($s['mulai'] ?? null) || filled($s['selesai'] ?? null)),
            )),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')->whereNull('deleted_at')],
            'jenis' => ['required', Rule::enum(JenisUjianKelas::class)],
            'prodi_id' => ['nullable', 'integer', Rule::exists('prodi', 'id')],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai', 'before_or_equal:'.$this->batasAkhir()],
            'sesi' => ['array', 'max:8'],
            'sesi.*.mulai' => ['required', 'date_format:H:i'],
            'sesi.*.selesai' => ['required', 'date_format:H:i'],
            'ruang_ids' => ['array'],
            'ruang_ids.*' => ['integer', Rule::exists('ruang', 'id')],
            'sertakan_sabtu' => ['boolean'],
            'pengampu_mengawas' => ['boolean'],
        ];
    }

    /** A sitting that ends before it starts, checked row by row. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            foreach ((array) $this->input('sesi', []) as $i => $s) {
                if (($s['selesai'] ?? '') <= ($s['mulai'] ?? '')) {
                    $v->errors()->add("sesi.{$i}.selesai", __('jadwal_ujian.galat.jam'));
                }
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tanggal_mulai' => __('jadwal_ujian.field.tanggal_mulai'),
            'tanggal_selesai' => __('jadwal_ujian.field.tanggal_selesai'),
            'sesi.*.mulai' => __('jadwal_ujian.field.jam_mulai'),
            'sesi.*.selesai' => __('jadwal_ujian.field.jam_selesai'),
        ];
    }

    public function penyusunan(): PenyusunanUjianData
    {
        return PenyusunanUjianData::fromArray($this->validated());
    }

    /**
     * Three weeks after the start, at most. A window that long is already more
     * than any exam period; a typo'd year would otherwise make the scheduler
     * walk a thousand days of empty sittings.
     */
    private function batasAkhir(): string
    {
        $mulai = strtotime((string) $this->input('tanggal_mulai')) ?: time();

        return date('Y-m-d', $mulai + 21 * 86400);
    }
}
