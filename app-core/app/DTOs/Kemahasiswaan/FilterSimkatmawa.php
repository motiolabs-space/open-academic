<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Http\Requests\Kemahasiswaan\FilterSimkatmawaRequest;
use Carbon\CarbonImmutable;

/**
 * Which achievements a SIMKATMAWA report covers: those dated within a period,
 * optionally narrowed to one programme.
 *
 * By the achievement's own date, not by academic term: SIMKATMAWA reports by
 * calendar year, and an off-campus contest in the semester break belongs to
 * no term at all.
 */
final readonly class FilterSimkatmawa
{
    public function __construct(
        public CarbonImmutable $dari,
        public CarbonImmutable $sampai,
        public ?int $prodiId = null,
    ) {}

    /** The calendar year so far, the default a reporting screen opens on. */
    public static function tahunIni(): self
    {
        $hariIni = CarbonImmutable::today();

        return new self($hariIni->startOfYear(), $hariIni->endOfYear()->startOfDay());
    }

    public static function fromRequest(FilterSimkatmawaRequest $request): self
    {
        $bawaan = self::tahunIni();

        return new self(
            dari: $request->filled('dari') ? CarbonImmutable::parse((string) $request->string('dari')) : $bawaan->dari,
            sampai: $request->filled('sampai') ? CarbonImmutable::parse((string) $request->string('sampai')) : $bawaan->sampai,
            prodiId: $request->filled('prodi') ? $request->integer('prodi') : null,
        );
    }

    /** @return array<string, string|int> query-string form, for links that keep the filter */
    public function query(): array
    {
        return array_filter([
            'dari' => $this->dari->toDateString(),
            'sampai' => $this->sampai->toDateString(),
            'prodi' => $this->prodiId,
        ], fn ($v): bool => $v !== null);
    }
}
