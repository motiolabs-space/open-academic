<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Enums\SumberStatusPekerjaan;
use App\Models\Kemahasiswaan\Alumni;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** An employment-status file to check, with where its answers came from. */
class ImporStatusPekerjaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('imporStatus', Alumni::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'berkas' => ['required', 'file', 'max:2048', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel'],
            'sumber' => ['required', Rule::enum(SumberStatusPekerjaan::class)],
        ];
    }

    public function sumber(): SumberStatusPekerjaan
    {
        return SumberStatusPekerjaan::from((string) $this->string('sumber'));
    }
}
