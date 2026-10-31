<?php

declare(strict_types=1);

namespace App\Http\Requests\Instalasi;

use App\Enums\EducationLevel;
use App\Enums\SemesterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The setup wizard, validated as one form (ADR-043).
 *
 * The steps are only a way of reading it. Submitting once and validating
 * everything together means a half-finished wizard leaves nothing behind —
 * no institution without an administrator, no administrator without a name
 * for the institution.
 */
class SetupInstalasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Whether setup is still open is checked by the controller.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $warna = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'nama_pt' => ['required', 'string', 'max:255'],
            'singkatan' => ['required', 'string', 'max:32'],

            // Six digits, as PDDIKTI issues them. Every Feeder payload carries
            // it, so a typo files the campus's reports under somebody else.
            'kode_pt' => ['required', 'string', 'regex:/^\d{6}$/'],

            'alamat' => ['required', 'string', 'max:500'],
            'kota' => ['required', 'string', 'max:100'],
            'provinsi' => ['required', 'string', 'max:100'],
            'surel' => ['required', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:32'],
            'situs' => ['nullable', 'url:http,https', 'max:255'],

            // No SVG: it is a document that can carry script, and the logo is
            // served from the same origin as every portal.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],

            'warna_utama' => $warna,
            'warna_aksen' => $warna,

            'admin_nama' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('staff', 'email')],
            'admin_nip' => ['nullable', 'string', 'max:32', Rule::unique('staff', 'nip')],
            'admin_password' => ['required', 'confirmed', Password::defaults()],

            'fakultas_nama' => ['nullable', 'string', 'max:255', 'required_with:prodi_nama'],
            'fakultas_kode' => ['nullable', 'string', 'max:16', 'required_with:fakultas_nama',
                Rule::unique('fakultas', 'kode')],

            'prodi_nama' => ['nullable', 'string', 'max:255'],
            'prodi_kode' => ['nullable', 'string', 'max:16', 'required_with:prodi_nama',
                Rule::unique('prodi', 'kode')],
            'prodi_jenjang' => ['nullable', 'required_with:prodi_nama', Rule::enum(EducationLevel::class)],

            'tahun_mulai' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'semester' => ['nullable', 'required_with:tahun_mulai', Rule::enum(SemesterType::class)],
            'tanggal_mulai' => ['nullable', 'required_with:tahun_mulai', 'date'],
            'tanggal_selesai' => ['nullable', 'required_with:tahun_mulai', 'date', 'after:tanggal_mulai'],

            'telemetri_setuju' => ['nullable', 'boolean'],
            'telemetri_sertakan_url' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kode_pt.regex' => __('instalasi.validasi.kode_pt'),
            'warna_utama.regex' => __('instalasi.validasi.warna'),
            'warna_aksen.regex' => __('instalasi.validasi.warna'),
            'admin_email.unique' => __('instalasi.validasi.admin_email_unik'),
            'fakultas_nama.required_with' => __('instalasi.validasi.prodi_tanpa_fakultas'),
            'tanggal_selesai.after' => __('instalasi.validasi.tanggal_selesai'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect(__('instalasi.field'))
            ->filter(fn ($label): bool => is_string($label))
            ->all();
    }
}
