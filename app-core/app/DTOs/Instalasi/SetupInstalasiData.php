<?php

declare(strict_types=1);

namespace App\DTOs\Instalasi;

use App\Enums\EducationLevel;
use App\Enums\SemesterType;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Everything the setup wizard collects, already validated (ADR-043).
 *
 * The faculty, programme and term are optional: a campus may prefer to enter
 * its structure from Data Master, or import it from Feeder, afterwards.
 */
final readonly class SetupInstalasiData
{
    /** @param array<string, string>|null $semester dates keyed as TahunAkademikService::buat expects */
    public function __construct(
        public string $namaPt,
        public string $singkatan,
        public string $kodePt,
        public string $alamat,
        public string $kota,
        public string $provinsi,
        public string $surel,
        public ?string $telepon,
        public ?string $situs,
        public ?UploadedFile $logo,
        public string $warnaUtama,
        public string $warnaAksen,
        public string $adminNama,
        public string $adminEmail,
        public string $adminPassword,
        public ?string $adminNip,
        public ?string $fakultasKode,
        public ?string $fakultasNama,
        public ?string $prodiKode,
        public ?string $prodiNama,
        public ?EducationLevel $prodiJenjang,
        public ?int $tahunMulai,
        public ?SemesterType $semester,
        public ?array $kalender,
        public PersetujuanTelemetriData $telemetri,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $teks = fn (string $kunci): ?string => filled($request->input($kunci))
            ? trim((string) $request->input($kunci))
            : null;

        $tahunMulai = filled($request->input('tahun_mulai')) ? (int) $request->input('tahun_mulai') : null;

        return new self(
            namaPt: (string) $teks('nama_pt'),
            singkatan: (string) $teks('singkatan'),
            kodePt: (string) $teks('kode_pt'),
            alamat: (string) $teks('alamat'),
            kota: (string) $teks('kota'),
            provinsi: (string) $teks('provinsi'),
            surel: mb_strtolower((string) $teks('surel')),
            telepon: $teks('telepon'),
            situs: $teks('situs'),
            logo: $request->file('logo'),
            warnaUtama: strtoupper((string) $teks('warna_utama')),
            warnaAksen: strtoupper((string) $teks('warna_aksen')),
            adminNama: (string) $teks('admin_nama'),
            adminEmail: mb_strtolower((string) $teks('admin_email')),
            adminPassword: (string) $request->input('admin_password'),
            adminNip: $teks('admin_nip'),
            fakultasKode: $teks('fakultas_kode'),
            fakultasNama: $teks('fakultas_nama'),
            prodiKode: $teks('prodi_kode'),
            prodiNama: $teks('prodi_nama'),
            prodiJenjang: EducationLevel::tryFrom((string) $request->input('prodi_jenjang')),
            tahunMulai: $tahunMulai,
            semester: $tahunMulai === null ? null : SemesterType::tryFrom((string) $request->input('semester')),
            kalender: $tahunMulai === null ? null : [
                'tahun_mulai' => (string) $tahunMulai,
                'tanggal_mulai' => (string) $request->input('tanggal_mulai'),
                'tanggal_selesai' => (string) $request->input('tanggal_selesai'),
            ],
            telemetri: PersetujuanTelemetriData::fromRequest($request),
        );
    }

    public function adaFakultas(): bool
    {
        return $this->fakultasNama !== null;
    }

    public function adaProdi(): bool
    {
        return $this->adaFakultas() && $this->prodiNama !== null;
    }

    public function adaSemester(): bool
    {
        return $this->tahunMulai !== null && $this->semester !== null;
    }
}
