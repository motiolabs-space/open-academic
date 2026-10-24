<?php

declare(strict_types=1);

namespace App\Http\Requests\Helpdesk;

use App\DTOs\Helpdesk\TiketBaruData;
use App\Enums\PrioritasTiket;
use App\Models\Helpdesk\HelpdeskTiket;
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
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'helpdesk_kategori_id.required' => __('helpdesk.validasi.kategori'),
            'helpdesk_kategori_id.exists' => __('helpdesk.validasi.kategori'),
            'judul.min' => __('helpdesk.validasi.judul_pendek'),
            'isi.min' => __('helpdesk.validasi.isi_pendek'),
        ];
    }

    public function toDto(): TiketBaruData
    {
        return TiketBaruData::fromRequest($this);
    }
}
