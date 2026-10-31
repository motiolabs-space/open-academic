<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Enums\JenisPoin;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Services\Berkas\BerkasService;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A student reporting their own achievement.
 *
 * Only achievement categories may be chosen — nobody reports a violation
 * against themselves — and the SIMKATMAWA details follow the staff form's
 * rules exactly. Whether evidence is required is the category's rule, which
 * the service enforces.
 */
class LaporPrestasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('lapor', PoinMahasiswa::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kategori' => [
                'required', 'uuid',
                Rule::exists('poin_kategori', 'uuid')
                    ->where('jenis', JenisPoin::Prestasi->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'judul' => ['required', 'string', 'min:5', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'tahun_akademik_id' => ['nullable', 'integer', Rule::exists('tahun_akademik', 'id')->whereNull('deleted_at')],
            'bukti' => app(BerkasService::class)->aturan('dokumen', wajib: false),
            ...DataSimkatmawaRequest::aturanRincian(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kategori.exists' => __('swalayan.prestasi.galat.kategori'),
            'tanggal.before_or_equal' => __('swalayan.prestasi.galat.tanggal'),
            'bukti.mimes' => __('swalayan.galat.berkas_jenis'),
        ];
    }
}
