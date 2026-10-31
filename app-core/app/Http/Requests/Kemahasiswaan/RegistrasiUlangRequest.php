<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\RegistrasiUlangData;
use App\Enums\JenisRegistrasiUlang;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Re-NIM, readmission or reactivation of one student.
 *
 * Which statuses each kind starts from, and whether the new programme
 * differs, are RegistrasiUlangService's rules — they hold for any caller,
 * not just this form. Here: the shape of the input, and the NIM and e-mail
 * the new account will log in with.
 */
class RegistrasiUlangRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mahasiswa = $this->route('mahasiswa');

        return $mahasiswa instanceof Mahasiswa && ($this->user()?->can('registrasiUlang', $mahasiswa) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $baru = Rule::requiredIf(fn (): bool => JenisRegistrasiUlang::tryFrom((string) $this->input('jenis'))?->registrasiBaru() ?? false);

        return [
            'jenis' => ['required', Rule::enum(JenisRegistrasiUlang::class)],
            'term' => ['required', 'string', Rule::exists('tahun_akademik', 'uuid')],
            'tanggal' => ['required', 'date'],
            'prodi' => [$baru, 'nullable', 'string', Rule::exists('prodi', 'uuid')->whereNull('deleted_at')],
            'nim' => ['nullable', 'string', 'max:32', 'regex:/^[0-9A-Za-z.\-]+$/', Rule::unique('mahasiswa', 'nim')],
            'email' => [$baru, 'nullable', 'email:rfc', 'max:255', Rule::unique('mahasiswa', 'email')],
            'nomor_sk' => ['nullable', 'string', 'max:64'],
            'tanggal_sk' => ['nullable', 'date'],
            'alasan' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('registrasi_ulang.form.atribut');
    }

    public function toData(): RegistrasiUlangData
    {
        $data = $this->validated();

        return RegistrasiUlangData::fromArray([
            ...$data,
            'term' => TahunAkademik::query()->where('uuid', $data['term'])->firstOrFail(),
            'prodi' => filled($data['prodi'] ?? null) ? Prodi::query()->where('uuid', $data['prodi'])->firstOrFail() : null,
        ]);
    }
}
