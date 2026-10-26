<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\JenisDaftar;
use Illuminate\Http\Request;

/**
 * How a student entered the programme, and where from.
 *
 * The recognised credits are deliberately absent. They are the sum of approved
 * conversions, never a number somebody types: a typed total and the courses
 * behind it would disagree the first time either changed.
 */
final readonly class AsalPendidikanData
{
    public function __construct(
        public JenisDaftar $jenisDaftar,
        public ?string $ptAsal = null,
        public ?string $prodiAsal = null,
        public ?string $termMasukAsal = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['jenis_daftar', 'pt_asal', 'prodi_asal', 'term_masuk_asal']));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $jenis = $data['jenis_daftar'] instanceof JenisDaftar
            ? $data['jenis_daftar']
            : JenisDaftar::from((string) $data['jenis_daftar']);

        $teks = static fn (mixed $nilai): ?string => filled($nilai) ? trim((string) $nilai) : null;

        // An origin recorded for a new entrant would be sent nowhere and read
        // by nobody, until the day somebody changes the kind and the stale
        // name goes to PDDIKTI with it. Dropped here so it is never stored.
        if (!$jenis->butuhAsal()) {
            return new self($jenis);
        }

        return new self(
            jenisDaftar: $jenis,
            ptAsal: $teks($data['pt_asal'] ?? null),
            prodiAsal: $teks($data['prodi_asal'] ?? null),
            termMasukAsal: $teks($data['term_masuk_asal'] ?? null),
        );
    }

    /** @return array<string, mixed> the mahasiswa columns this sets */
    public function kolom(): array
    {
        return [
            'jenis_daftar' => $this->jenisDaftar,
            'pt_asal' => $this->ptAsal,
            'prodi_asal' => $this->prodiAsal,
            'term_masuk_asal' => $this->termMasukAsal,
        ];
    }
}
