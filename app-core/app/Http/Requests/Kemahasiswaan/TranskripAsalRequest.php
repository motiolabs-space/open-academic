<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\BarisTranskripData;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Berkas\BerkasService;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The lines of an origin transcript, each matched to a course here.
 */
class TranskripAsalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mahasiswa = $this->route('mahasiswa');
        $aktor = Portal::user();

        return $mahasiswa instanceof Mahasiswa
            && $aktor !== null
            && Gate::forUser($aktor)->allows('update', $mahasiswa);
    }

    /**
     * Rows the operator added and left blank are dropped before validating,
     * so an empty spare row at the bottom is not an error.
     */
    protected function prepareForValidation(): void
    {
        $baris = array_values(array_filter(
            (array) $this->input('baris', []),
            fn (mixed $b): bool => is_array($b) && (filled($b['mata_kuliah_id'] ?? null) || filled($b['asal_nama'] ?? null)),
        ));

        $this->merge(['baris' => $baris]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $mahasiswa = $this->route('mahasiswa');
        $prodiId = $mahasiswa instanceof Mahasiswa ? $mahasiswa->prodi_id : 0;

        return [
            'baris' => ['required', 'array', 'min:1', 'max:60'],
            'baris.*.mata_kuliah_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('mata_kuliah', 'id')->where('prodi_id', $prodiId)->whereNull('deleted_at'),
            ],
            'baris.*.asal_nama' => ['required', 'string', 'max:255'],
            'baris.*.asal_sks' => ['nullable', 'integer', 'min:1', 'max:60'],
            'baris.*.asal_nilai' => ['nullable', 'string', 'max:8'],
            'berkas' => app(BerkasService::class)->aturan('dokumen', wajib: false),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'baris.required' => __('pindahan.validasi.transkrip_kosong'),
            'baris.min' => __('pindahan.validasi.transkrip_kosong'),
            'baris.*.mata_kuliah_id.required' => __('pindahan.validasi.matkul_tujuan'),
            'baris.*.mata_kuliah_id.exists' => __('pindahan.validasi.matkul_tujuan'),
            'baris.*.mata_kuliah_id.distinct' => __('pindahan.validasi.matkul_ganda'),
            'baris.*.asal_nama.required' => __('pindahan.validasi.nama_asal'),
        ];
    }

    /** @return array<int, BarisTranskripData> */
    public function toDto(): array
    {
        return BarisTranskripData::daftar((array) $this->validated('baris'));
    }
}
