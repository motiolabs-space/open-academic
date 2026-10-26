<?php

declare(strict_types=1);

namespace App\DTOs\Pmb;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final readonly class JadwalSeleksiData
{
    public function __construct(
        public string $nama,
        public CarbonImmutable $mulai,
        public ?CarbonImmutable $selesai = null,
        public ?string $lokasi = null,
        public ?string $keterangan = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $teks = static fn (mixed $nilai): ?string => filled($nilai) ? trim((string) $nilai) : null;

        return new self(
            nama: trim((string) $request->input('nama')),
            mulai: CarbonImmutable::parse((string) $request->input('mulai')),
            selesai: $request->filled('selesai') ? CarbonImmutable::parse((string) $request->input('selesai')) : null,
            lokasi: $teks($request->input('lokasi')),
            keterangan: $teks($request->input('keterangan')),
        );
    }
}
