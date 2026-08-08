<?php

declare(strict_types=1);

namespace App\Http\Resources\Bridge;

use App\Models\Akademik\KelasKuliah;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A course offering, carrying the two flags IKU 7 is computed from and the
 * practitioner assignment IKU 4 counts.
 *
 * @mixin KelasKuliah
 */
class ClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'semester' => $this->tahunAkademik->kode,
            'kode_kelas' => $this->kode,
            'mata_kuliah' => [
                'kode' => $this->mataKuliah->kode,
                'nama' => $this->mataKuliah->nama,
                'sks' => $this->mataKuliah->sks,
            ],
            'prodi' => [
                'kode' => $this->prodi->kode,
                'nama' => $this->prodi->nama,
            ],
            'kuota' => $this->kuota,
            'terisi' => $this->terisi,
            'mode' => $this->mode,

            // IKU 7 evidence, stated as data rather than left for a consumer to
            // infer from a course name.
            'metode_pembelajaran' => [
                'case_method' => (bool) $this->is_case_method,
                'team_based_project' => (bool) $this->is_team_based_project,
                'kolaboratif' => $this->kelasKolaboratif(),
            ],

            'status_nilai' => $this->status_nilai,

            'pengajar' => $this->whenLoaded('dosen', fn () => $this->dosen->map(fn ($d): array => [
                'uuid' => $d->uuid,
                'nama' => $d->namaLengkap(),
                'nidn' => $d->nidn,
                'peran' => $d->pivot->peran,
                'praktisi' => $d->pivot->peran === 'praktisi',
                'instansi' => $d->pivot->praktisi_instansi,
            ])->values()),

            'jadwal' => $this->whenLoaded('jadwal', fn () => $this->jadwal->map(fn ($j): array => [
                'hari' => $j->namaHari(),
                'jam_mulai' => substr((string) $j->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $j->jam_selesai, 0, 5),
                'ruang' => $j->ruang?->kode,
            ])->values()),
        ];
    }
}
