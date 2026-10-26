<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use Illuminate\Http\Request;

/**
 * A group's placement and supervisors.
 *
 * The partner supervisor is a name and a phone number, not an account: the
 * person at the village office never signs in, and their mark is entered by
 * the field supervisor or staff.
 */
final readonly class KelompokKegiatanData
{
    public function __construct(
        public ?int $lokasiId = null,
        public ?int $dosenPembimbingId = null,
        public ?string $pembimbingMitraNama = null,
        public ?string $pembimbingMitraJabatan = null,
        public ?string $pembimbingMitraTelepon = null,
        public ?int $ketuaAktivitasId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->validated());
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $teks = static fn (string $kunci): ?string => filled($data[$kunci] ?? null) ? trim((string) $data[$kunci]) : null;
        $angka = static fn (string $kunci): ?int => filled($data[$kunci] ?? null) ? (int) $data[$kunci] : null;

        return new self(
            lokasiId: $angka('lokasi_id'),
            dosenPembimbingId: $angka('dosen_pembimbing_id'),
            pembimbingMitraNama: $teks('pembimbing_mitra_nama'),
            pembimbingMitraJabatan: $teks('pembimbing_mitra_jabatan'),
            pembimbingMitraTelepon: $teks('pembimbing_mitra_telepon'),
            ketuaAktivitasId: $angka('ketua_aktivitas_id'),
        );
    }
}
