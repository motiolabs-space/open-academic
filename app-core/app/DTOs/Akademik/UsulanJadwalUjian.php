<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Enums\JenisUjianKelas;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Ruang;
use App\Models\Sdm\Dosen;
use App\Support\Format;

/**
 * One session the bulk scheduler proposes, before anything is saved.
 */
final readonly class UsulanJadwalUjian
{
    /**
     * @param list<array{ruang: Ruang, kursi: int}> $ruang
     * @param list<Dosen> $pengawas
     */
    public function __construct(
        public KelasKuliah $kelas,
        public JenisUjianKelas $jenis,
        public string $tanggal,
        public string $jamMulai,
        public string $jamSelesai,
        public int $jumlahPeserta,
        public array $ruang,
        public array $pengawas,
    ) {}

    public function rentangWaktu(): string
    {
        return Format::tanggalHari($this->tanggal).' · '.Format::rentangJam($this->jamMulai, $this->jamSelesai);
    }

    public function totalKursi(): int
    {
        return array_sum(array_map(static fn (array $r): int => $r['kursi'], $this->ruang));
    }

    public function toData(): JadwalUjianData
    {
        return new JadwalUjianData(
            jenis: $this->jenis,
            tanggal: $this->tanggal,
            jamMulai: $this->jamMulai,
            jamSelesai: $this->jamSelesai,
            ruang: array_map(
                static fn (array $r): array => ['ruang_id' => $r['ruang']->id, 'kursi' => $r['kursi']],
                $this->ruang,
            ),
            pengawas: array_map(
                static fn (Dosen $d): array => ['dosen_id' => $d->id, 'staff_id' => null, 'ruang_id' => null],
                $this->pengawas,
            ),
        );
    }
}
