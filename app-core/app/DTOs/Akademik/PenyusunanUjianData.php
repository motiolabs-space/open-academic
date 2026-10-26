<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Enums\JenisUjianKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The frame a whole exam week is laid out in: which days, which sittings per
 * day, which rooms.
 *
 * Carried by the preview and by the save alike, so what is saved is computed
 * from exactly the parameters the registrar looked at.
 */
final readonly class PenyusunanUjianData
{
    /** Sittings offered when the registrar does not name any. */
    public const SESI_BAWAAN = [['08:00', '10:00'], ['10:30', '12:30'], ['13:30', '15:30']];

    /**
     * @param list<array{0: string, 1: string}> $sesi sittings per day, start and end
     * @param list<int> $ruangIds rooms to use; empty means every active room
     */
    public function __construct(
        public int $tahunAkademikId,
        public JenisUjianKelas $jenis,
        public ?int $prodiId,
        public string $tanggalMulai,
        public string $tanggalSelesai,
        public array $sesi = self::SESI_BAWAAN,
        public array $ruangIds = [],
        public bool $sertakanSabtu = false,
        public bool $pengampuMengawas = true,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $sesi = collect($data['sesi'] ?? [])
            ->filter(fn ($s): bool => is_array($s) && filled($s['mulai'] ?? null) && filled($s['selesai'] ?? null))
            ->map(fn (array $s): array => [substr((string) $s['mulai'], 0, 5), substr((string) $s['selesai'], 0, 5)])
            ->sortBy(fn (array $s): string => $s[0])
            ->values()
            ->all();

        $ruangIds = collect((array) ($data['ruang_ids'] ?? []))
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return new self(
            tahunAkademikId: (int) $data['tahun_akademik_id'],
            jenis: $data['jenis'] instanceof JenisUjianKelas
                ? $data['jenis']
                : JenisUjianKelas::from((string) $data['jenis']),
            prodiId: filled($data['prodi_id'] ?? null) ? (int) $data['prodi_id'] : null,
            tanggalMulai: Carbon::parse((string) $data['tanggal_mulai'])->toDateString(),
            tanggalSelesai: Carbon::parse((string) $data['tanggal_selesai'])->toDateString(),
            sesi: $sesi === [] ? self::SESI_BAWAAN : $sesi,
            ruangIds: $ruangIds,
            sertakanSabtu: filter_var($data['sertakan_sabtu'] ?? false, FILTER_VALIDATE_BOOLEAN),
            pengampuMengawas: filter_var($data['pengampu_mengawas'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /**
     * Every sitting in the window, in order. Sundays never; Saturdays only
     * when asked.
     *
     * @return list<array{tanggal: string, mulai: string, selesai: string}>
     */
    public function slot(): array
    {
        $slot = [];
        $hari = Carbon::parse($this->tanggalMulai);
        $akhir = Carbon::parse($this->tanggalSelesai);

        while ($hari->lte($akhir)) {
            if (!$hari->isSunday() && ($this->sertakanSabtu || !$hari->isSaturday())) {
                foreach ($this->sesi as [$mulai, $selesai]) {
                    $slot[] = ['tanggal' => $hari->toDateString(), 'mulai' => $mulai, 'selesai' => $selesai];
                }
            }

            $hari->addDay();
        }

        return $slot;
    }

    /** @return array<string, mixed> the same parameters, as a query string or hidden form */
    public function toArray(): array
    {
        return [
            'tahun_akademik_id' => $this->tahunAkademikId,
            'jenis' => $this->jenis->value,
            'prodi_id' => $this->prodiId,
            'tanggal_mulai' => $this->tanggalMulai,
            'tanggal_selesai' => $this->tanggalSelesai,
            'sesi' => array_map(static fn (array $s): array => ['mulai' => $s[0], 'selesai' => $s[1]], $this->sesi),
            'ruang_ids' => $this->ruangIds,
            'sertakan_sabtu' => $this->sertakanSabtu ? 1 : 0,
            'pengampu_mengawas' => $this->pengampuMengawas ? 1 : 0,
        ];
    }
}
