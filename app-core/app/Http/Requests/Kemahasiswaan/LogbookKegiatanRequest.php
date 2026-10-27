<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Enums\JenisLogbook;
use App\Services\Berkas\BerkasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A field-log entry from the student.
 *
 * Which activity the entry belongs to is resolved from the signed-in student
 * by the controller and checked by the policy there, never taken from the
 * form.
 */
class LogbookKegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'jenis' => ['required', Rule::enum(JenisLogbook::class)],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'judul' => ['required', 'string', 'max:255'],
            'uraian' => ['nullable', 'string', 'max:5000'],
            'berkas' => app(BerkasService::class)->aturan('dokumen', wajib: false),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('kegiatan_lapangan.field');
    }
}
