<?php

declare(strict_types=1);

namespace App\Http\Requests\Helpdesk;

use App\DTOs\Helpdesk\TiketBaruData;
use App\Enums\PrioritasTiket;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Berkas\BerkasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TiketBaruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HelpdeskTiket::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'helpdesk_kategori_id' => [
                'required',
                'integer',
                Rule::exists('helpdesk_kategori', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'judul' => ['required', 'string', 'min:5', 'max:200'],
            'isi' => ['required', 'string', 'min:20', 'max:10000'],
            'prioritas' => ['nullable', Rule::enum(PrioritasTiket::class)],
            'lampiran' => app(BerkasService::class)->aturan('dokumen', wajib: false),

            // Staff typing a ticket in for a student or lecturer. The ability
            // itself is checked by the controller (createOnBehalf).
            'atas_nama' => ['sometimes', 'boolean'],
            'atas_nama_identitas' => ['nullable', 'required_if:atas_nama,1', 'string', 'max:32'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function ($validator): void {
            if ($this->boolean('atas_nama') && filled($this->input('atas_nama_identitas')) && $this->pemohonAtasNama() === null) {
                $validator->errors()->add('atas_nama_identitas', __('helpdesk.staf.atas_nama_tidak_ditemukan'));
            }
        }];
    }

    /**
     * The student (by NIM) or lecturer (by NIDN) a ticket is typed in for, or
     * null when the officer files for themselves.
     */
    public function pemohonAtasNama(): Mahasiswa|Dosen|null
    {
        if (!$this->boolean('atas_nama')) {
            return null;
        }

        $nomor = trim((string) $this->string('atas_nama_identitas'));

        if ($nomor === '') {
            return null;
        }

        return Mahasiswa::query()->where('nim', $nomor)->first()
            ?? Dosen::query()->where('nidn', $nomor)->first();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'helpdesk_kategori_id.required' => __('helpdesk.validasi.kategori'),
            'helpdesk_kategori_id.exists' => __('helpdesk.validasi.kategori'),
            'judul.min' => __('helpdesk.validasi.judul_pendek'),
            'isi.min' => __('helpdesk.validasi.isi_pendek'),
            'atas_nama_identitas.required_if' => __('helpdesk.staf.atas_nama_wajib'),
        ];
    }

    public function toDto(): TiketBaruData
    {
        return TiketBaruData::fromRequest($this);
    }
}
