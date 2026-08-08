<?php

declare(strict_types=1);

namespace App\Http\Resources\Bridge;

use App\Models\Akademik\TahunAkademik;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An academic term.
 *
 * `kode` is the PDDIKTI encoding (20261 = odd semester of 2026/2027) and is the
 * key every other Bridge resource uses to reference a term — never the internal
 * id, which is meaningless outside this database.
 *
 * @mixin TahunAkademik
 */
class AcademicTermResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kode' => $this->kode,
            'nama' => $this->nama,
            'tahun_mulai' => $this->tahun_mulai,
            'semester' => $this->semester->label(),
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),
            'aktif' => (bool) $this->is_active,
            'terkunci' => (bool) $this->is_locked,
            'jendela' => [
                'krs_dibuka' => $this->krsDibuka(),
                'penilaian_dibuka' => $this->penilaianDibuka(),
            ],
        ];
    }
}
