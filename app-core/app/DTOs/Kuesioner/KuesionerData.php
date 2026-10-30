<?php

declare(strict_types=1);

namespace App\DTOs\Kuesioner;

use App\Enums\SasaranKuesioner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The header of a questionnaire: what it is called, who it is put to, and when.
 *
 * `anonim` is read only on creation. KuesionerService::perbarui() ignores it,
 * because the table its answers land in is decided once.
 */
final readonly class KuesionerData
{
    public function __construct(
        public string $kode,
        public string $nama,
        public ?string $deskripsi,
        public SasaranKuesioner $sasaran,
        public ?int $prodiId = null,
        public bool $anonim = true,
        public ?Carbon $mulai = null,
        public ?Carbon $selesai = null,
        public ?int $minResponden = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            kode: trim((string) $request->string('kode')),
            nama: trim((string) $request->string('nama')),
            deskripsi: $request->filled('deskripsi') ? trim((string) $request->string('deskripsi')) : null,
            sasaran: SasaranKuesioner::from((string) $request->string('sasaran')),
            prodiId: $request->filled('prodi_id') ? (int) $request->input('prodi_id') : null,
            anonim: $request->boolean('anonim'),
            mulai: $request->filled('mulai') ? Carbon::parse((string) $request->string('mulai')) : null,
            selesai: $request->filled('selesai') ? Carbon::parse((string) $request->string('selesai')) : null,
            minResponden: $request->filled('min_responden') ? (int) $request->input('min_responden') : null,
        );
    }
}
