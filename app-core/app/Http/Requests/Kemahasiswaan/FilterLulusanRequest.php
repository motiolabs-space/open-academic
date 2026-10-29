<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\Alumni;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** The graduation year and programme a tracer list covers. */
class FilterLulusanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('viewAny', Alumni::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tahun_lulus' => ['nullable', 'integer', 'between:1950,2100'],
            'prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'hanya_perlu' => ['nullable', 'boolean'],
        ];
    }
}
