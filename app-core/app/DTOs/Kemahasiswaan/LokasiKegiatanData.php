<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\JenisLokasiKegiatan;
use Illuminate\Http\Request;

/** A village or partner institution taking groups. */
final readonly class LokasiKegiatanData
{
    public function __construct(
        public JenisLokasiKegiatan $jenis,
        public string $nama,
        public ?string $alamat = null,
        public ?string $kecamatan = null,
        public ?string $kabupaten = null,
        public ?string $provinsi = null,
        public int $kuotaKelompok = 1,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->validated());
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $teks = static fn (string $kunci): ?string => filled($data[$kunci] ?? null) ? trim((string) $data[$kunci]) : null;

        return new self(
            jenis: $data['jenis'] instanceof JenisLokasiKegiatan ? $data['jenis'] : JenisLokasiKegiatan::from((string) $data['jenis']),
            nama: trim((string) $data['nama']),
            alamat: $teks('alamat'),
            kecamatan: $teks('kecamatan'),
            kabupaten: $teks('kabupaten'),
            provinsi: $teks('provinsi'),
            kuotaKelompok: max(1, (int) ($data['kuota_kelompok'] ?? 1)),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'jenis' => $this->jenis,
            'nama' => $this->nama,
            'alamat' => $this->alamat,
            'kecamatan' => $this->kecamatan,
            'kabupaten' => $this->kabupaten,
            'provinsi' => $this->provinsi,
            'kuota_kelompok' => $this->kuotaKelompok,
        ];
    }
}
