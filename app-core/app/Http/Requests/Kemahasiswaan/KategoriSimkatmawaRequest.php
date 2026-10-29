<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Enums\KategoriSimkatmawa;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Which SIMKATMAWA table a catalogue entry is reported in; empty takes it off all of them. */
class KategoriSimkatmawaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('eksporSimkatmawa', PoinMahasiswa::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'simkatmawa_kategori' => ['nullable', Rule::in(array_keys(KategoriSimkatmawa::optionsKatalog()))],
        ];
    }

    public function kategori(): ?KategoriSimkatmawa
    {
        return KategoriSimkatmawa::tryFrom((string) $this->input('simkatmawa_kategori'));
    }
}
