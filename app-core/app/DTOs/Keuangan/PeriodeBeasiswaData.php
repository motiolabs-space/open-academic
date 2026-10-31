<?php

declare(strict_types=1);

namespace App\DTOs\Keuangan;

use App\Http\Requests\Keuangan\BukaPeriodeBeasiswaRequest;
use Illuminate\Support\Carbon;

/**
 * A call for scholarship applications as the finance office opened it.
 *
 * `ipkMinimum` is the one requirement the application checks for itself;
 * everything else in `syarat` is read and judged by a person.
 */
final readonly class PeriodeBeasiswaData
{
    /** @param array<int, string> $berkasDiminta */
    public function __construct(
        public int $tahunAkademikId,
        public string $nama,
        public Carbon $dibukaMulai,
        public Carbon $dibukaSampai,
        public ?string $syarat = null,
        public ?float $ipkMinimum = null,
        public array $berkasDiminta = [],
    ) {}

    public static function fromRequest(BukaPeriodeBeasiswaRequest $request): self
    {
        return new self(
            tahunAkademikId: $request->integer('tahun_akademik_id'),
            nama: trim((string) $request->string('nama')),
            dibukaMulai: Carbon::parse((string) $request->string('dibuka_mulai'))->startOfDay(),
            dibukaSampai: Carbon::parse((string) $request->string('dibuka_sampai'))->startOfDay(),
            syarat: $request->filled('syarat') ? trim((string) $request->string('syarat')) : null,
            ipkMinimum: $request->filled('ipk_minimum') ? (float) $request->input('ipk_minimum') : null,
            berkasDiminta: $request->berkasDiminta(),
        );
    }
}
