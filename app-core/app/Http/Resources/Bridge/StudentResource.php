<?php

declare(strict_types=1);

namespace App\Http\Resources\Bridge;

use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * A student as Campus Bridge exposes them.
 *
 * Deliberately narrower than the model. NIK, home address, parents' names and
 * income are collected for PDDIKTI reporting, not for an engagement platform —
 * a consumer that never receives them cannot leak them. What is here is what
 * Open Campus needs to attribute a post, render a profile, and compute an
 * indicator.
 *
 * @mixin Mahasiswa
 */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusTerm = $this->whenLoaded('statusPerSemester');

        return [
            'uuid' => $this->uuid,
            'nim' => $this->nim,
            'nama' => $this->nama,
            'email' => $this->email,
            'angkatan' => $this->angkatan,
            'status' => [
                'kode' => $this->status->value,
                'label' => $this->status->label(),
                'aktif' => $this->status->canEnroll(),
            ],
            'prodi' => [
                'kode' => $this->prodi->kode,
                'nama' => $this->prodi->nama,
                'jenjang' => $this->prodi->jenjang->label(),
                'fakultas' => $this->whenLoaded('prodi', fn () => $this->prodi->fakultas?->nama),
            ],
            'dosen_wali' => $this->whenLoaded('dosenWali', fn () => $this->dosenWali === null ? null : [
                'uuid' => $this->dosenWali->uuid,
                'nama' => $this->dosenWali->namaLengkap(),
            ]),
            'foto_url' => $this->foto_path ? asset('storage/'.$this->foto_path) : null,

            // Per-term history is what an IKU engine reads; it is only included
            // when the caller asked for it.
            'riwayat_semester' => $this->when(
                $statusTerm !== null && !$statusTerm instanceof MissingValue,
                fn () => $this->statusPerSemester->map(fn ($s): array => [
                    'semester' => $s->tahunAkademik->kode,
                    'semester_ke' => $s->semester_ke,
                    'status' => $s->status->value,
                    'ips' => (float) $s->ips,
                    'ipk' => (float) $s->ipk,
                    'sks_semester' => $s->sks_semester,
                    'sks_kumulatif' => $s->sks_kumulatif,
                    'final' => (bool) $s->is_final,
                ])->values(),
            ),
        ];
    }
}
