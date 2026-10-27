<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\DTOs\Akademik\FilterPemanfaatanRuang;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The filter of the room-utilisation report and its CSV export.
 */
class PemanfaatanRuangRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('viewUtilization', Ruang::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'term' => ['nullable', 'integer', Rule::exists('tahun_akademik', 'id')->whereNull('deleted_at')],
            'gedung' => ['nullable', 'integer', Rule::exists('gedung', 'id')->whereNull('deleted_at')],
            'jenis' => ['nullable', 'string', Rule::in(array_keys((array) __('pemanfaatan_ruang.jenis_ruang')))],
            'urut' => ['nullable', Rule::in(FilterPemanfaatanRuang::URUTAN)],
            'ruang' => ['nullable', 'uuid'],
        ];
    }

    /** The selected term, or the portal's current one. */
    public function term(): TahunAkademik
    {
        return ($this->filled('term') ? TahunAkademik::find($this->integer('term')) : null)
            ?? Portal::term()
            ?? abort(404);
    }

    public function filter(): FilterPemanfaatanRuang
    {
        return FilterPemanfaatanRuang::fromArray($this->term(), [
            'gedung_id' => $this->validated('gedung'),
            'jenis' => $this->validated('jenis'),
            'urut' => $this->validated('urut'),
        ]);
    }
}
