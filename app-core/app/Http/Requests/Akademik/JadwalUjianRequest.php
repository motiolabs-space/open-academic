<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\DTOs\Akademik\JadwalUjianData;
use App\Enums\JenisUjianKelas;
use App\Models\Akademik\JadwalUjian;
use App\Models\Akademik\KelasKuliah;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * One exam session, new or edited.
 *
 * Rules about the shape of the input live here; rules about the timetable —
 * clashes, the term's dates, a second session of the same type — live in
 * JadwalUjianService, where the bulk scheduler and tests reach them too.
 */
class JadwalUjianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $jadwal = $this->route('jadwal');

        if ($aktor === null) {
            return false;
        }

        return $jadwal instanceof JadwalUjian
            ? Gate::forUser($aktor)->allows('update', $jadwal)
            : Gate::forUser($aktor)->allows('create', JadwalUjian::class);
    }

    /** Spare blank rows at the bottom of the room and invigilator lists are not errors. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ruang' => array_values(array_filter(
                (array) $this->input('ruang', []),
                fn (mixed $r): bool => is_array($r) && filled($r['ruang_id'] ?? null),
            )),
            'pengawas' => array_values(array_filter(
                (array) $this->input('pengawas', []),
                fn (mixed $p): bool => is_array($p) && filled($p['orang'] ?? null),
            )),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $baru = !$this->route('jadwal') instanceof JadwalUjian;

        return [
            'kelas_kuliah_id' => $baru
                ? ['required', 'string', Rule::exists('kelas_kuliah', 'uuid')->whereNull('deleted_at')]
                : ['prohibited'],
            'jenis' => ['required', Rule::enum(JenisUjianKelas::class)],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruang' => ['array', 'max:20'],
            'ruang.*.ruang_id' => ['required', 'integer', 'distinct', Rule::exists('ruang', 'id')->whereNull('deleted_at')],
            'ruang.*.kursi' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'pengawas' => ['array', 'max:40'],
            'pengawas.*.orang' => ['required', 'string', 'distinct', 'regex:/^(dosen|staff):\d+$/'],
            'pengawas.*.ruang_id' => ['nullable', 'integer'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tanggal' => __('jadwal_ujian.field.tanggal'),
            'jam_mulai' => __('jadwal_ujian.field.jam_mulai'),
            'jam_selesai' => __('jadwal_ujian.field.jam_selesai'),
            'ruang.*.ruang_id' => __('jadwal_ujian.field.ruang'),
            'ruang.*.kursi' => __('jadwal_ujian.field.kursi'),
            'pengawas.*.orang' => __('jadwal_ujian.field.pengawas'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'jam_selesai.after' => __('jadwal_ujian.galat.jam'),
            'ruang.*.ruang_id.distinct' => __('jadwal_ujian.galat.ruang_ganda'),
            'pengawas.*.orang.distinct' => __('jadwal_ujian.galat.pengawas_ganda'),
        ];
    }

    public function kelas(): KelasKuliah
    {
        return KelasKuliah::whereUuid((string) $this->validated('kelas_kuliah_id'))->firstOrFail();
    }

    public function isian(): JadwalUjianData
    {
        return JadwalUjianData::fromArray($this->validated());
    }
}
