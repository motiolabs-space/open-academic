<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Enums\JenisPeristiwa;
use App\Enums\SasaranPeristiwa;
use App\Enums\StatusPeristiwa;
use App\Support\Format;
use Illuminate\Support\Carbon;

/**
 * One entry on the academic calendar, whatever table it was read from.
 *
 * A read model: nothing writes back through it. The source row stays the owner
 * of the date, which is why a manual entry carries its uuid (the calendar can
 * edit it) and a derived one does not (its screen is elsewhere — `jenis`
 * names it).
 *
 * `kunci` is stable across reads of the same source row, so an iCalendar
 * client sees an edited date as the same event moved rather than one event
 * deleted and another created.
 */
final readonly class PeristiwaKalender
{
    public Carbon $mulai;

    public Carbon $selesai;

    public function __construct(
        public string $kunci,
        public JenisPeristiwa $jenis,
        public string $judul,
        Carbon|string $mulai,
        Carbon|string|null $selesai,
        public SasaranPeristiwa $sasaran = SasaranPeristiwa::Semua,
        public ?int $prodiId = null,
        public ?string $prodiNama = null,
        public ?string $keterangan = null,
        public ?string $jamMulai = null,
        public ?string $jamSelesai = null,
        public ?string $lokasi = null,
        public ?StatusPeristiwa $status = null,
        public ?string $uuid = null,
        public bool $pribadi = false,
    ) {
        $this->mulai = Carbon::parse($mulai)->startOfDay();
        $this->selesai = Carbon::parse($selesai ?? $mulai)->startOfDay()->max($this->mulai);
    }

    public function manual(): bool
    {
        return $this->uuid !== null;
    }

    public function draf(): bool
    {
        return $this->status === StatusPeristiwa::Draf;
    }

    public function sehari(): bool
    {
        return $this->mulai->isSameDay($this->selesai);
    }

    /** Whether the entry has a clock time, rather than lasting whole days. */
    public function berwaktu(): bool
    {
        return $this->jamMulai !== null && $this->jamSelesai !== null;
    }

    public function jatuhPada(Carbon $hari): bool
    {
        return $hari->betweenIncluded($this->mulai, $this->selesai);
    }

    public function beririsan(Carbon $dari, Carbon $sampai): bool
    {
        return $this->mulai->lte($sampai) && $this->selesai->gte($dari);
    }

    /** "12 Okt 2026" or "12 Okt – 23 Okt 2026", plus the hours when it has them. */
    public function rentang(): string
    {
        $tanggal = $this->sehari()
            ? Format::tanggal($this->mulai)
            : Format::rentangTanggal($this->mulai, $this->selesai);

        return $this->berwaktu()
            ? $tanggal.' · '.Format::rentangJam($this->jamMulai, $this->jamSelesai)
            : $tanggal;
    }

    /** Whether an audience is meant to see the entry. */
    public function untukSasaran(SasaranPeristiwa $sasaran): bool
    {
        return $this->sasaran === SasaranPeristiwa::Semua || $this->sasaran === $sasaran;
    }

    /** Whether the entry applies to a programme; one without a programme applies to all. */
    public function untukProdi(?int $prodiId): bool
    {
        return $this->prodiId === null || $prodiId === null || $this->prodiId === $prodiId;
    }
}
