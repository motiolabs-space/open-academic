<?php

declare(strict_types=1);

namespace App\Http\Resources\Bridge;

use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An off-campus student activity — the transactional source behind IKU 2.
 *
 * The 20-credit threshold is *not* applied here. Open Academic reports how many
 * credits were recognised and whether the record is verified; deciding what
 * clears the indicator belongs to Open Campus, and baking the threshold into
 * this payload would silently freeze a policy that changes by ministerial
 * regulation.
 *
 * @mixin AktivitasMahasiswa
 */
class StudentActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'semester' => $this->tahunAkademik->kode,
            'mahasiswa' => [
                'uuid' => $this->mahasiswa->uuid,
                'nim' => $this->mahasiswa->nim,
                'nama' => $this->mahasiswa->nama,
                'prodi' => $this->mahasiswa->prodi->nama,
            ],
            'jenis' => $this->jenis->value,
            'jenis_label' => $this->jenis->label(),
            'judul' => $this->judul,
            'mitra' => [
                'nama' => $this->mitra_nama,
                'jenis' => $this->mitra_jenis,
            ],
            'lokasi' => $this->lokasi,
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),
            'sks_konversi' => $this->sks_konversi,
            'terverifikasi' => (bool) $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'pembimbing' => $this->whenLoaded('pembimbing', fn () => $this->pembimbing?->namaLengkap()),
        ];
    }
}
