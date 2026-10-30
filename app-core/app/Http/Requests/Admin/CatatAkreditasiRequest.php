<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\LembagaAkreditasi;
use App\Enums\PeringkatAkreditasi;
use App\Models\Akademik\AkreditasiProdi;
use App\Services\Berkas\BerkasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** One accreditation decree for a programme (ADR-041 §3). */
class CatatAkreditasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AkreditasiProdi::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lembaga' => ['required', Rule::enum(LembagaAkreditasi::class)],
            'peringkat' => ['required', Rule::enum(PeringkatAkreditasi::class)],
            'nomor_sk' => ['required', 'string', 'max:128'],
            'tanggal_sk' => ['required', 'date'],
            'tanggal_berlaku' => ['nullable', 'date', 'after_or_equal:tanggal_sk'],
            'tanggal_kedaluwarsa' => ['required', 'date', 'after:tanggal_sk', 'after:tanggal_berlaku'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'dokumen' => app(BerkasService::class)->aturan('dokumen', wajib: false),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tanggal_kedaluwarsa.after' => __('akreditasi.validasi.kedaluwarsa_sesudah'),
            'tanggal_berlaku.after_or_equal' => __('akreditasi.validasi.berlaku_sesudah_sk'),
        ];
    }
}
