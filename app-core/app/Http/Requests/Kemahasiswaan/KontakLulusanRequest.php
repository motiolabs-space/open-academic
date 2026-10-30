<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\Alumni;
use App\Services\Kemahasiswaan\DataLulusanService;
use App\Support\Portal;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** A graduate's contact details, as confirmed with the graduate. */
class KontakLulusanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('kelolaKontak', Alumni::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email_pribadi' => ['nullable', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:32', function (string $atribut, mixed $nilai, Closure $gagal): void {
                if (filled($nilai) && !DataLulusanService::teleponSah((string) $nilai)) {
                    $gagal(__('lulusan.tanda.telepon_tidak_sah'));
                }
            }],
            'kontak_tidak_dibagikan' => ['nullable', 'boolean'],
        ];
    }
}
