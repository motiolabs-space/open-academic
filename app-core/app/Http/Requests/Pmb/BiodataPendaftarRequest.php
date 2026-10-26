<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\DTOs\Pmb\BiodataPendaftarData;
use App\Enums\Agama;
use App\Enums\Gender;
use App\Models\Pmb\PmbPendaftar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The applicant's biodata, checked the way Feeder will check it.
 *
 * FeederValidator::mahasiswa() refuses a biodata without a 16-digit NIK,
 * place and date of birth, and sex, and warns on a missing mother's name and
 * religion. All five are required here, together with the address
 * MahasiswaMapper sends — an accepted applicant then becomes a student who is
 * reportable on the first sync, without anybody chasing a missing field.
 */
class BiodataPendaftarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->pendaftar()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $pindahan = $this->pendaftar()->gelombang?->jalurPindahan() ?? false;

        return [
            'nama' => ['required', 'string', 'max:255'],
            'nik' => ['required', 'regex:/^\d{16}$/'],
            'nisn' => ['nullable', 'regex:/^\d{10}$/'],
            'tempat_lahir' => ['required', 'string', 'max:64'],
            'tanggal_lahir' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'jenis_kelamin' => ['required', Rule::enum(Gender::class)],
            'agama_kode' => ['required', Rule::enum(Agama::class)],
            'nama_ibu' => ['required', 'string', 'max:255'],
            'nama_ayah' => ['nullable', 'string', 'max:255'],
            'telepon' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{8,}$/'],
            'alamat' => ['required', 'string', 'max:255'],
            'kelurahan' => ['required', 'string', 'max:96'],
            'kecamatan' => ['required', 'string', 'max:96'],
            'kabupaten' => ['required', 'string', 'max:96'],
            'provinsi' => ['required', 'string', 'max:96'],
            'kode_pos' => ['nullable', 'regex:/^\d{5}$/'],
            'asal_sekolah' => ['nullable', 'string', 'max:255'],
            'tahun_lulus_sekolah' => ['nullable', 'integer', 'between:1950,'.(now()->year + 1)],
            'pt_asal' => [Rule::requiredIf($pindahan), 'nullable', 'string', 'max:255'],
            'prodi_asal' => [Rule::requiredIf($pindahan), 'nullable', 'string', 'max:255'],
            'is_calon_kip' => ['nullable', 'boolean'],
            'nomor_kip' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nik.required' => __('pmb.validasi.nik'),
            'nik.regex' => __('pmb.validasi.nik'),
            'nisn.regex' => __('pmb.validasi.nisn'),
            'kode_pos.regex' => __('pmb.validasi.kode_pos'),
            'pt_asal.required' => __('pmb.validasi.asal_kurang'),
            'prodi_asal.required' => __('pmb.validasi.asal_kurang'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect(BiodataPendaftarData::KOLOM)
            ->mapWithKeys(fn (string $k): array => [$k => __('pmb.kolom.'.$k)])
            ->all();
    }

    public function pendaftar(): PmbPendaftar
    {
        /** @var PmbPendaftar $pendaftar */
        $pendaftar = $this->route('pendaftar');

        return $pendaftar;
    }

    public function dto(): BiodataPendaftarData
    {
        return BiodataPendaftarData::fromRequest($this);
    }
}
