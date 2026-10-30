<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Enums\KategoriSimkatmawa;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Support\EksporTabel;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The period and programme a SIMKATMAWA report covers, and which table to download. */
class FilterSimkatmawaRequest extends FormRequest
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
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'kategori' => ['nullable', Rule::enum(KategoriSimkatmawa::class)],
            'format' => ['nullable', Rule::in(EksporTabel::FORMAT)],
        ];
    }

    public function kategori(): KategoriSimkatmawa
    {
        return KategoriSimkatmawa::tryFrom((string) $this->input('kategori')) ?? KategoriSimkatmawa::Kompetisi;
    }

    public function formatBerkas(): string
    {
        return $this->filled('format') ? (string) $this->string('format') : 'csv';
    }
}
