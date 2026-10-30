<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\JenisRegistrasiUlang;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use Carbon\CarbonImmutable;

/**
 * One re-NIM, readmission or reactivation, as the registrar records it.
 *
 * The programme and the account fields only matter when the event starts a
 * new registration; fromArray() drops them for a reactivation so they can
 * never be stored against one.
 */
final readonly class RegistrasiUlangData
{
    public function __construct(
        public JenisRegistrasiUlang $jenis,
        public TahunAkademik $term,
        public CarbonImmutable $tanggal,
        public string $alasan,
        public ?Prodi $prodiBaru = null,
        public ?string $nimBaru = null,
        public ?string $email = null,
        public ?string $nomorSk = null,
        public ?CarbonImmutable $tanggalSk = null,
    ) {}

    /** @param array<string, mixed> $data validated input, with `prodi` and `term` already resolved */
    public static function fromArray(array $data): self
    {
        $jenis = $data['jenis'] instanceof JenisRegistrasiUlang ? $data['jenis'] : JenisRegistrasiUlang::from((string) $data['jenis']);
        $teks = static fn (mixed $nilai): ?string => filled($nilai) ? trim((string) $nilai) : null;
        $baru = $jenis->registrasiBaru();

        return new self(
            jenis: $jenis,
            term: $data['term'],
            tanggal: CarbonImmutable::parse((string) $data['tanggal']),
            alasan: trim((string) $data['alasan']),
            prodiBaru: $baru ? ($data['prodi'] ?? null) : null,
            nimBaru: $baru ? $teks($data['nim'] ?? null) : null,
            email: $baru && $teks($data['email'] ?? null) !== null ? mb_strtolower((string) $teks($data['email'])) : null,
            nomorSk: $teks($data['nomor_sk'] ?? null),
            tanggalSk: filled($data['tanggal_sk'] ?? null) ? CarbonImmutable::parse((string) $data['tanggal_sk']) : null,
        );
    }
}
