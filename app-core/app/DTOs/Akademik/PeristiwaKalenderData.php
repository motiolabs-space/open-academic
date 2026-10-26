<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Enums\JenisPeristiwa;
use App\Enums\SasaranPeristiwa;
use App\Enums\StatusPeristiwa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** A holiday, activity or deadline as the registrar writes it. */
final readonly class PeristiwaKalenderData
{
    public function __construct(
        public string $judul,
        public JenisPeristiwa $jenis,
        public string $tanggalMulai,
        public string $tanggalSelesai,
        public SasaranPeristiwa $sasaran = SasaranPeristiwa::Semua,
        public ?int $prodiId = null,
        public StatusPeristiwa $status = StatusPeristiwa::Draf,
        public ?string $keterangan = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $mulai = Carbon::parse((string) $data['tanggal_mulai'])->toDateString();

        return new self(
            judul: trim((string) $data['judul']),
            jenis: $data['jenis'] instanceof JenisPeristiwa ? $data['jenis'] : JenisPeristiwa::from((string) $data['jenis']),
            tanggalMulai: $mulai,
            tanggalSelesai: filled($data['tanggal_selesai'] ?? null)
                ? Carbon::parse((string) $data['tanggal_selesai'])->toDateString()
                : $mulai,
            sasaran: ($data['sasaran'] ?? null) instanceof SasaranPeristiwa
                ? $data['sasaran']
                : (SasaranPeristiwa::tryFrom((string) ($data['sasaran'] ?? '')) ?? SasaranPeristiwa::Semua),
            prodiId: filled($data['prodi_id'] ?? null) ? (int) $data['prodi_id'] : null,
            status: ($data['status'] ?? null) instanceof StatusPeristiwa
                ? $data['status']
                : (StatusPeristiwa::tryFrom((string) ($data['status'] ?? '')) ?? StatusPeristiwa::Draf),
            keterangan: filled($data['keterangan'] ?? null) ? trim((string) $data['keterangan']) : null,
        );
    }

    /** @return array<string, mixed> the columns of kalender_peristiwa */
    public function toAttributes(): array
    {
        return [
            'judul' => $this->judul,
            'jenis' => $this->jenis,
            'tanggal_mulai' => $this->tanggalMulai,
            'tanggal_selesai' => $this->tanggalSelesai,
            'sasaran' => $this->sasaran,
            'prodi_id' => $this->prodiId,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
        ];
    }
}
