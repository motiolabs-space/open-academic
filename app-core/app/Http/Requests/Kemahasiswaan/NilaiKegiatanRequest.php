<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan;

use App\Models\Kemahasiswaan\KegiatanLapanganKelompok;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The marks for a whole group at once, keyed by activity UUID.
 *
 * Rows naming an activity outside the group are dropped by the controller,
 * which reads the group's own members — the UUID in the form is never trusted
 * to say whose mark it is.
 */
class NilaiKegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $kelompok = $this->route('kelompok');

        return $aktor !== null
            && $kelompok instanceof KegiatanLapanganKelompok
            && Gate::forUser($aktor)->allows('bimbing', $kelompok);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nilai' => ['required', 'array', 'max:100'],
            'nilai.*.pembimbing' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nilai.*.mitra' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /** @return array<string, array{pembimbing: ?float, mitra: ?float}> */
    public function isian(): array
    {
        $hasil = [];

        foreach ((array) $this->validated('nilai') as $uuid => $baris) {
            $hasil[(string) $uuid] = [
                'pembimbing' => filled($baris['pembimbing'] ?? null) ? (float) $baris['pembimbing'] : null,
                'mitra' => filled($baris['mitra'] ?? null) ? (float) $baris['mitra'] : null,
            ];
        }

        return $hasil;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('kegiatan_lapangan.field');
    }
}
