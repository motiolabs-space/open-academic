<?php

declare(strict_types=1);

namespace App\Http\Resources\Bridge;

use App\Models\Kemahasiswaan\Yudisium;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A confirmed graduate — the population an IKU 1 tracer study starts from.
 *
 * @mixin Yudisium
 */
class GraduateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'mahasiswa' => [
                'uuid' => $this->mahasiswa->uuid,
                'nim' => $this->mahasiswa->nim,
                'nama' => $this->mahasiswa->nama,
                'email' => $this->mahasiswa->email,
                'prodi' => [
                    'kode' => $this->mahasiswa->prodi->kode,
                    'nama' => $this->mahasiswa->prodi->nama,
                    'jenjang' => $this->mahasiswa->prodi->jenjang->label(),
                ],
            ],
            'nomor_sk' => $this->nomor_sk,
            'tanggal_lulus' => $this->tanggal_lulus?->toDateString(),
            'tanggal_yudisium' => $this->tanggal_yudisium?->toDateString(),
            'total_sks' => $this->total_sks,
            'ipk' => (float) $this->ipk,
            'predikat' => $this->predikat,
            'judul_tugas_akhir' => $this->judul_tugas_akhir,

            'alumni' => $this->whenLoaded('mahasiswa', fn () => $this->mahasiswa->alumni === null ? null : [
                'email_pribadi' => $this->mahasiswa->alumni->email_pribadi,
                'status_pekerjaan' => $this->mahasiswa->alumni->status_pekerjaan,
                'instansi' => $this->mahasiswa->alumni->instansi,
            ]),
        ];
    }
}
