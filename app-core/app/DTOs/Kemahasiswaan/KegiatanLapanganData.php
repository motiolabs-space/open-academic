<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\StudentActivityType;
use Illuminate\Http\Request;

/** A field-programme run as staff describe it. */
final readonly class KegiatanLapanganData
{
    public function __construct(
        public int $tahunAkademikId,
        public StudentActivityType $jenis,
        public string $nama,
        public string $tanggalMulai,
        public string $tanggalSelesai,
        public ?string $nomorSk = null,
        public ?string $tanggalSk = null,
        public bool $pendaftaranDibuka = false,
        public int $minSks = 0,
        public int $ukuranKelompok = 10,
        public bool $campurProdi = true,
        public bool $campurJenisKelamin = true,
        public int $bobotMitra = 40,
        public ?string $keterangan = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Absent rules fall back to config/kegiatan_lapangan.php, so a programme
     * created from code without them still gets the campus defaults.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $teks = static fn (string $kunci): ?string => filled($data[$kunci] ?? null) ? trim((string) $data[$kunci]) : null;

        return new self(
            tahunAkademikId: (int) $data['tahun_akademik_id'],
            jenis: $data['jenis'] instanceof StudentActivityType ? $data['jenis'] : StudentActivityType::from((string) $data['jenis']),
            nama: trim((string) $data['nama']),
            tanggalMulai: (string) $data['tanggal_mulai'],
            tanggalSelesai: (string) $data['tanggal_selesai'],
            nomorSk: $teks('nomor_sk'),
            tanggalSk: $teks('tanggal_sk'),
            pendaftaranDibuka: (bool) ($data['pendaftaran_dibuka'] ?? false),
            minSks: (int) ($data['min_sks'] ?? config('kegiatan_lapangan.min_sks')),
            ukuranKelompok: (int) ($data['ukuran_kelompok'] ?? config('kegiatan_lapangan.kelompok.ukuran')),
            campurProdi: (bool) ($data['campur_prodi'] ?? config('kegiatan_lapangan.kelompok.campur_prodi')),
            campurJenisKelamin: (bool) ($data['campur_jenis_kelamin'] ?? config('kegiatan_lapangan.kelompok.campur_jenis_kelamin')),
            bobotMitra: (int) ($data['bobot_mitra'] ?? config('kegiatan_lapangan.bobot_mitra')),
            keterangan: $teks('keterangan'),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'tahun_akademik_id' => $this->tahunAkademikId,
            'jenis' => $this->jenis,
            'nama' => $this->nama,
            'tanggal_mulai' => $this->tanggalMulai,
            'tanggal_selesai' => $this->tanggalSelesai,
            'nomor_sk' => $this->nomorSk,
            'tanggal_sk' => $this->tanggalSk,
            'pendaftaran_dibuka' => $this->pendaftaranDibuka,
            'min_sks' => $this->minSks,
            'ukuran_kelompok' => $this->ukuranKelompok,
            'campur_prodi' => $this->campurProdi,
            'campur_jenis_kelamin' => $this->campurJenisKelamin,
            'bobot_mitra' => $this->bobotMitra,
            'keterangan' => $this->keterangan,
        ];
    }
}
