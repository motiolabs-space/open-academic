<?php

declare(strict_types=1);

namespace App\Http\Requests\Pmb;

use App\DTOs\Pmb\JadwalSeleksiData;
use Illuminate\Foundation\Http\FormRequest;

class JadwalSeleksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('gelombang')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'mulai' => ['required', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nama' => __('pmb.jadwal.nama'),
            'mulai' => __('pmb.jadwal.mulai'),
            'selesai' => __('pmb.jadwal.selesai'),
            'lokasi' => __('pmb.jadwal.lokasi'),
        ];
    }

    public function dto(): JadwalSeleksiData
    {
        return JadwalSeleksiData::fromRequest($this);
    }
}
