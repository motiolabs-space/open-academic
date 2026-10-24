<?php

declare(strict_types=1);

namespace App\Http\Requests\Helpdesk;

use App\DTOs\Helpdesk\BalasanData;
use App\Services\Berkas\BerkasService;
use Illuminate\Foundation\Http\FormRequest;

class BalasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The policy on the ticket decides; the controller resolves which
        // ability (reply or handle) applies to the writer.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'isi' => ['required', 'string', 'min:2', 'max:10000'],
            'internal' => ['sometimes', 'boolean'],
            'lampiran' => app(BerkasService::class)->aturan('dokumen', wajib: false),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'isi.required' => __('helpdesk.validasi.balasan_kosong'),
        ];
    }

    public function toDto(): BalasanData
    {
        return BalasanData::fromRequest($this);
    }
}
