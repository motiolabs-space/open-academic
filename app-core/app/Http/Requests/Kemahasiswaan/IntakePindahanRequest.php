<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\AsalPendidikanData;
use App\DTOs\Kemahasiswaan\IdentitasMahasiswaData;
use App\Enums\ApplicantStatus;
use App\Enums\Gender;
use App\Http\Requests\Kemahasiswaan\Concerns\AturanAsalPendidikan;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Kemahasiswaan\PindahanService;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Both doors of the transfer intake.
 *
 * With `pmb_pendaftar` the person already exists as an accepted applicant and
 * only the origin is asked for. Without it the registrar is enrolling somebody
 * directly, and the identity fields Feeder refuses a biodata without are
 * required here — the same checks FeederValidator::mahasiswa() makes, so a
 * student entered through this form is reportable on the first sync.
 */
class IntakePindahanRequest extends FormRequest
{
    use AturanAsalPendidikan;

    public function authorize(): bool
    {
        $aktor = Portal::user();

        if ($aktor === null) {
            return false;
        }

        return $this->dariPmb()
            ? Gate::forUser($aktor)->allows('createFromPmb', Mahasiswa::class)
            : Gate::forUser($aktor)->allows('create', Mahasiswa::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $aturan = $this->aturanAsal(array_keys(PindahanService::jenisPilihan()), $this->kodeTermMasuk()) + [
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
        ];

        if ($this->dariPmb()) {
            return $aturan + [
                'pmb_pendaftar' => [
                    'required',
                    'uuid',
                    Rule::exists('pmb_pendaftar', 'uuid')
                        ->whereNull('deleted_at')
                        ->whereNull('mahasiswa_id')
                        ->whereIn('status', [ApplicantStatus::Lulus->value, ApplicantStatus::DaftarUlang->value]),
                ],
            ];
        }

        return $aturan + [
            'prodi_id' => ['required', 'integer', Rule::exists('prodi', 'id')],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('mahasiswa', 'email')],
            'nik' => ['required', 'regex:/^\d{16}$/'],
            'tempat_lahir' => ['required', 'string', 'max:64'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'jenis_kelamin' => ['required', Rule::enum(Gender::class)],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:32'],
            'alamat' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->pesanAsal() + [
            'nik.regex' => __('pindahan.validasi.nik'),
            'nik.required' => __('pindahan.validasi.nik'),
            'email.unique' => __('pindahan.validasi.email_dipakai'),
            'pmb_pendaftar.exists' => __('pindahan.validasi.pendaftar'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return $this->atributAsal() + [
            'tempat_lahir' => __('pindahan.kolom.tempat_lahir'),
            'nama_ibu' => __('pindahan.kolom.nama_ibu'),
        ];
    }

    public function dariPmb(): bool
    {
        return $this->filled('pmb_pendaftar');
    }

    public function pendaftar(): ?PmbPendaftar
    {
        return $this->dariPmb()
            ? PmbPendaftar::where('uuid', (string) $this->input('pmb_pendaftar'))->firstOrFail()
            : null;
    }

    public function asal(): AsalPendidikanData
    {
        return AsalPendidikanData::fromRequest($this);
    }

    public function identitas(): IdentitasMahasiswaData
    {
        return IdentitasMahasiswaData::fromRequest($this);
    }
}
