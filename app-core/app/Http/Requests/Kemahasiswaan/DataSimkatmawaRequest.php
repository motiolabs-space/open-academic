<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Enums\BentukKepesertaan;
use App\Enums\BidangLomba;
use App\Enums\JenisRekognisi;
use App\Enums\ModaPelaksanaan;
use App\Enums\PeringkatPrestasi;
use App\Enums\TingkatSimkatmawa;
use App\Models\Sdm\Dosen;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The SIMKATMAWA details of one achievement.
 *
 * Nothing is required here: the form saves as far as staff got, and the
 * export screen lists what is still missing. What is filled in must be well
 * formed, though — a link that is not a link is caught now, not by the
 * ministry's assessor.
 */
class DataSimkatmawaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('lengkapiSimkatmawa', $this->route('poin'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tautan = ['nullable', 'string', 'max:500', 'url:http,https'];

        return [
            'tingkat' => ['nullable', Rule::enum(TingkatSimkatmawa::class)],
            'peringkat' => ['nullable', Rule::enum(PeringkatPrestasi::class)],
            'bentuk_kepesertaan' => ['nullable', Rule::enum(BentukKepesertaan::class)],
            'moda_pelaksanaan' => ['nullable', Rule::enum(ModaPelaksanaan::class)],
            'bidang_lomba' => ['nullable', Rule::enum(BidangLomba::class)],
            'jenis_rekognisi' => ['nullable', Rule::enum(JenisRekognisi::class)],
            'penyelenggara' => ['nullable', 'string', 'max:255'],
            'tanggal_selesai' => ['nullable', 'date'],
            'jumlah_pt_peserta' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'jumlah_negara_peserta' => ['nullable', 'integer', 'min:1', 'max:300'],
            'nomor_sertifikat' => ['nullable', 'string', 'max:100'],
            'tautan_sertifikat' => $tautan,
            'tautan_bukti' => $tautan,
            'tautan_surat_tugas' => $tautan,
            'dosen_pembimbing' => ['nullable', 'uuid', Rule::exists('dosen', 'uuid')->whereNull('deleted_at')],
        ];
    }

    public function dosenPembimbingId(): ?int
    {
        if (!$this->filled('dosen_pembimbing')) {
            return null;
        }

        $id = Dosen::query()->where('uuid', (string) $this->string('dosen_pembimbing'))->value('id');

        return $id === null ? null : (int) $id;
    }
}
