<?php

declare(strict_types=1);

namespace App\Http\Resources\Bridge;

use App\Models\Sdm\Dosen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lecturer, with the assignment records IKU 3 and IKU 4 are computed from.
 *
 * @mixin Dosen
 */
class LecturerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'nidn' => $this->nidn,
            'nama' => $this->nama,
            'nama_lengkap' => $this->namaLengkap(),
            'email' => $this->email,
            'jabatan_fungsional' => $this->jabatan_fungsional,
            'pendidikan_tertinggi' => $this->pendidikan_tertinggi?->label(),
            'status_kepegawaian' => $this->status_kepegawaian,

            // The IKU 4 population: practitioners brought in from industry.
            'praktisi' => [
                'ya' => (bool) $this->is_praktisi,
                'instansi' => $this->praktisi_instansi,
            ],

            'prodi' => $this->whenLoaded('prodi', fn () => $this->prodi === null ? null : [
                'kode' => $this->prodi->kode,
                'nama' => $this->prodi->nama,
            ]),

            'penugasan' => $this->whenLoaded('penugasan', fn () => $this->penugasan->map(fn ($p): array => [
                'uuid' => $p->uuid,
                'jenis' => $p->jenis->value,
                'jenis_label' => $p->jenis->label(),
                'judul' => $p->judul,
                'mitra' => $p->mitra_nama,
                'mitra_jenis' => $p->mitra_jenis,
                'tanggal_mulai' => $p->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $p->tanggal_selesai?->toDateString(),
                'sks_ekuivalen' => $p->sks_ekuivalen === null ? null : (float) $p->sks_ekuivalen,
                'terverifikasi' => (bool) $p->is_verified,

                // Which indicator this record feeds, decided by the enum rather
                // than by the consumer guessing from the type string.
                'iku' => array_values(array_filter([
                    $p->jenis->countsForIku3() ? 3 : null,
                    $p->jenis->countsForIku4() ? 4 : null,
                ])),
            ])->values()),
        ];
    }
}
