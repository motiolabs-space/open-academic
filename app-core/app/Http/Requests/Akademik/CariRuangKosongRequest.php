<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\DTOs\Akademik\KriteriaRuangKosong;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A free-room question. The screen opens empty; a question is asked only once
 * a window is given.
 */
class CariRuangKosongRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('viewUtilization', Ruang::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $bertanya = $this->bertanya();

        return [
            'term' => ['nullable', 'integer', Rule::exists('tahun_akademik', 'id')->whereNull('deleted_at')],
            'hari' => [Rule::requiredIf($bertanya && !$this->filled('tanggal')), 'nullable', 'integer', 'between:1,7'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'jam_mulai' => [Rule::requiredIf($bertanya), 'nullable', 'date_format:H:i'],
            'jam_selesai' => [Rule::requiredIf($bertanya), 'nullable', 'date_format:H:i', 'after:jam_mulai'],
            'kapasitas_min' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'gedung' => ['nullable', 'integer', Rule::exists('gedung', 'id')->whereNull('deleted_at')],
            'jenis' => ['nullable', 'string', Rule::in(array_keys((array) __('pemanfaatan_ruang.jenis_ruang')))],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return (array) __('pemanfaatan_ruang.kosong.field');
    }

    /** Whether the request asks anything, or only opens the form. */
    public function bertanya(): bool
    {
        return $this->filled('jam_mulai') || $this->filled('jam_selesai') || $this->filled('tanggal') || $this->filled('hari');
    }

    public function term(): TahunAkademik
    {
        return ($this->filled('term') ? TahunAkademik::find($this->integer('term')) : null)
            ?? Portal::term()
            ?? abort(404);
    }

    public function kriteria(): ?KriteriaRuangKosong
    {
        if (!$this->bertanya()) {
            return null;
        }

        return KriteriaRuangKosong::fromArray($this->term(), [
            'hari' => $this->validated('hari'),
            'tanggal' => $this->validated('tanggal'),
            'jam_mulai' => (string) $this->validated('jam_mulai'),
            'jam_selesai' => (string) $this->validated('jam_selesai'),
            'kapasitas_min' => $this->validated('kapasitas_min'),
            'gedung_id' => $this->validated('gedung'),
            'jenis' => $this->validated('jenis'),
        ]);
    }
}
