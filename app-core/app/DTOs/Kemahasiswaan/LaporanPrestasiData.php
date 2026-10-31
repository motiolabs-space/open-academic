<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Http\Requests\Kemahasiswaan\LaporPrestasiRequest;
use Illuminate\Http\UploadedFile;

/**
 * An achievement as a student reported it: the claim, its evidence, and the
 * SIMKATMAWA details they know. Staff still verify the claim and may correct
 * the details afterwards on the existing Lengkapi screen.
 */
final readonly class LaporanPrestasiData
{
    public function __construct(
        public string $tanggal,
        public string $judul,
        public ?string $keterangan,
        public ?int $tahunAkademikId,
        public ?UploadedFile $bukti,
        public DataSimkatmawa $simkatmawa,
    ) {}

    public static function fromRequest(LaporPrestasiRequest $request): self
    {
        return new self(
            tanggal: (string) $request->string('tanggal'),
            judul: trim((string) $request->string('judul')),
            keterangan: $request->filled('keterangan') ? trim((string) $request->string('keterangan')) : null,
            tahunAkademikId: $request->filled('tahun_akademik_id') ? $request->integer('tahun_akademik_id') : null,
            bukti: $request->file('bukti'),
            simkatmawa: DataSimkatmawa::fromInput($request),
        );
    }

    /** @return array<string, mixed> the shape PoinKemahasiswaanService::catat() reads */
    public function catatan(?string $buktiPath): array
    {
        return [
            'tanggal' => $this->tanggal,
            'judul' => $this->judul,
            'keterangan' => $this->keterangan,
            'tahun_akademik_id' => $this->tahunAkademikId,
            'bukti_path' => $buktiPath,
        ];
    }
}
