<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Enums\JenisPeristiwa;
use App\Enums\SasaranPeristiwa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * What part of the calendar a reader asked for.
 *
 * The range is a closed date interval. A month view asks for whole weeks around
 * the month, so the leading and trailing days of the grid are filled too.
 */
final readonly class FilterKalender
{
    public Carbon $dari;

    public Carbon $sampai;

    public function __construct(
        Carbon|string $dari,
        Carbon|string $sampai,
        public ?SasaranPeristiwa $sasaran = null,
        public ?int $prodiId = null,
        public ?JenisPeristiwa $jenis = null,
    ) {
        $this->dari = Carbon::parse($dari)->startOfDay();
        $this->sampai = Carbon::parse($sampai)->startOfDay();
    }

    /** The weeks a month grid shows, Monday to Sunday. */
    public static function gridBulan(Carbon $bulan, ?SasaranPeristiwa $sasaran = null, ?int $prodiId = null, ?JenisPeristiwa $jenis = null): self
    {
        return new self(
            $bulan->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
            $bulan->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            $sasaran,
            $prodiId,
            $jenis,
        );
    }

    /** The month a request asks for ("2026-10"); this month when absent or malformed. */
    public static function bulanDari(Request $request): Carbon
    {
        $bulan = $request->string('bulan')->toString();

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan) === 1) {
            return Carbon::createFromFormat('!Y-m', $bulan)->startOfMonth();
        }

        return Carbon::today()->startOfMonth();
    }

    public static function fromRequest(Request $request): self
    {
        return self::gridBulan(
            self::bulanDari($request),
            SasaranPeristiwa::tryFrom($request->string('sasaran')->toString()),
            $request->filled('prodi') ? $request->integer('prodi') : null,
            JenisPeristiwa::tryFrom($request->string('jenis')->toString()),
        );
    }

    /** The same filter over another range — the iCalendar feed, say. */
    public function dalamRentang(Carbon|string $dari, Carbon|string $sampai): self
    {
        return new self($dari, $sampai, $this->sasaran, $this->prodiId, $this->jenis);
    }

    public function denganProdi(?int $prodiId): self
    {
        return new self($this->dari, $this->sampai, $this->sasaran, $prodiId, $this->jenis);
    }
}
