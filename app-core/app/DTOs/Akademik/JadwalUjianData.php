<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Enums\JenisUjianKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * One exam session as the registrar describes it.
 *
 * Rooms and invigilators are lists because a large class sits in several rooms
 * at once, each with its own invigilator. A room left without a seat count is
 * given the room's own capacity.
 */
final readonly class JadwalUjianData
{
    /**
     * @param list<array{ruang_id: int, kursi: ?int}> $ruang
     * @param list<array{dosen_id: ?int, staff_id: ?int, ruang_id: ?int}> $pengawas
     */
    public function __construct(
        public JenisUjianKelas $jenis,
        public string $tanggal,
        public string $jamMulai,
        public string $jamSelesai,
        public array $ruang = [],
        public array $pengawas = [],
        public ?string $catatan = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $ruang = collect($data['ruang'] ?? [])
            ->filter(fn ($baris): bool => is_array($baris) && filled($baris['ruang_id'] ?? null))
            ->map(fn (array $baris): array => [
                'ruang_id' => (int) $baris['ruang_id'],
                'kursi' => filled($baris['kursi'] ?? null) ? (int) $baris['kursi'] : null,
            ])
            ->values()
            ->all();

        $pengawas = collect($data['pengawas'] ?? [])
            ->map(fn ($baris): ?array => self::pengawasDari($baris))
            ->filter()
            ->values()
            ->all();

        return new self(
            jenis: $data['jenis'] instanceof JenisUjianKelas
                ? $data['jenis']
                : JenisUjianKelas::from((string) $data['jenis']),
            tanggal: Carbon::parse((string) $data['tanggal'])->toDateString(),
            jamMulai: substr((string) $data['jam_mulai'], 0, 5),
            jamSelesai: substr((string) $data['jam_selesai'], 0, 5),
            ruang: $ruang,
            pengawas: $pengawas,
            catatan: filled($data['catatan'] ?? null) ? trim((string) $data['catatan']) : null,
        );
    }

    /**
     * An invigilator row from the form, where the person arrives as one
     * "dosen:12" / "staff:4" value so a single select can offer both kinds.
     *
     * @return array{dosen_id: ?int, staff_id: ?int, ruang_id: ?int}|null
     */
    private static function pengawasDari(mixed $baris): ?array
    {
        if (!is_array($baris)) {
            return null;
        }

        $dosenId = filled($baris['dosen_id'] ?? null) ? (int) $baris['dosen_id'] : null;
        $staffId = filled($baris['staff_id'] ?? null) ? (int) $baris['staff_id'] : null;

        if (filled($baris['orang'] ?? null) && str_contains((string) $baris['orang'], ':')) {
            [$jenis, $id] = explode(':', (string) $baris['orang'], 2);
            $dosenId = $jenis === 'dosen' ? (int) $id : null;
            $staffId = $jenis === 'staff' ? (int) $id : null;
        }

        if ($dosenId === null && $staffId === null) {
            return null;
        }

        return [
            'dosen_id' => $dosenId,
            'staff_id' => $staffId,
            'ruang_id' => filled($baris['ruang_id'] ?? null) ? (int) $baris['ruang_id'] : null,
        ];
    }
}
