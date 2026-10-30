<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\Alumni;
use App\Support\EksporTabel;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Downloading the tracer list. A stated purpose is required: it goes into
 * the activity log next to who downloaded what, which is the only trace the
 * file leaves once it is out of the system.
 */
class EksporLulusanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('ekspor', Alumni::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tahun_lulus' => ['nullable', 'integer', 'between:1950,2100'],
            'prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'format' => ['required', Rule::in(EksporTabel::FORMAT)],
            'keperluan' => ['required', 'string', 'min:10', 'max:200'],
        ];
    }
}
