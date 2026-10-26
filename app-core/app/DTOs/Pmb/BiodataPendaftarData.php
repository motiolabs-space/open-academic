<?php

declare(strict_types=1);

namespace App\DTOs\Pmb;

use App\Enums\Agama;
use App\Enums\Gender;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The applicant's identity, as MahasiswaMapper will one day send it.
 *
 * Every column here is one PmbService::daftarUlang() copies onto the student
 * record, so what the applicant types is what PDDIKTI receives.
 */
final readonly class BiodataPendaftarData
{
    public const KOLOM = [
        'nama', 'nik', 'nisn', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama_kode',
        'nama_ibu', 'nama_ayah', 'telepon', 'alamat', 'kelurahan', 'kecamatan', 'kabupaten',
        'provinsi', 'kode_pos', 'asal_sekolah', 'tahun_lulus_sekolah', 'pt_asal', 'prodi_asal',
        'is_calon_kip', 'nomor_kip',
    ];

    public function __construct(
        public string $nama,
        public string $nik,
        public string $tempatLahir,
        public CarbonImmutable $tanggalLahir,
        public Gender $jenisKelamin,
        public Agama $agama,
        public string $namaIbu,
        public string $telepon,
        public string $alamat,
        public string $kelurahan,
        public string $kecamatan,
        public string $kabupaten,
        public string $provinsi,
        public ?string $nisn = null,
        public ?string $namaAyah = null,
        public ?string $kodePos = null,
        public ?string $asalSekolah = null,
        public ?int $tahunLulusSekolah = null,
        public ?string $ptAsal = null,
        public ?string $prodiAsal = null,
        public bool $calonKip = false,
        public ?string $nomorKip = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(self::KOLOM));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $teks = static fn (mixed $nilai): ?string => filled($nilai) ? trim((string) $nilai) : null;
        $calonKip = filter_var($data['is_calon_kip'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new self(
            nama: trim((string) $data['nama']),
            nik: trim((string) $data['nik']),
            tempatLahir: trim((string) $data['tempat_lahir']),
            tanggalLahir: CarbonImmutable::parse((string) $data['tanggal_lahir']),
            jenisKelamin: $data['jenis_kelamin'] instanceof Gender
                ? $data['jenis_kelamin']
                : Gender::from((string) $data['jenis_kelamin']),
            agama: $data['agama_kode'] instanceof Agama
                ? $data['agama_kode']
                : Agama::from((string) $data['agama_kode']),
            namaIbu: trim((string) $data['nama_ibu']),
            telepon: trim((string) $data['telepon']),
            alamat: trim((string) $data['alamat']),
            kelurahan: trim((string) $data['kelurahan']),
            kecamatan: trim((string) $data['kecamatan']),
            kabupaten: trim((string) $data['kabupaten']),
            provinsi: trim((string) $data['provinsi']),
            nisn: $teks($data['nisn'] ?? null),
            namaAyah: $teks($data['nama_ayah'] ?? null),
            kodePos: $teks($data['kode_pos'] ?? null),
            asalSekolah: $teks($data['asal_sekolah'] ?? null),
            tahunLulusSekolah: filled($data['tahun_lulus_sekolah'] ?? null) ? (int) $data['tahun_lulus_sekolah'] : null,
            ptAsal: $teks($data['pt_asal'] ?? null),
            prodiAsal: $teks($data['prodi_asal'] ?? null),
            calonKip: $calonKip,
            // A number without the flag means nothing to the KIP office.
            nomorKip: $calonKip ? $teks($data['nomor_kip'] ?? null) : null,
        );
    }

    /** @return array<string, mixed> the pmb_pendaftar columns this sets */
    public function kolom(): array
    {
        return [
            'nama' => $this->nama,
            'nik' => $this->nik,
            'nisn' => $this->nisn,
            'tempat_lahir' => $this->tempatLahir,
            'tanggal_lahir' => $this->tanggalLahir->toDateString(),
            'jenis_kelamin' => $this->jenisKelamin,
            'agama_kode' => $this->agama,
            'nama_ibu' => $this->namaIbu,
            'nama_ayah' => $this->namaAyah,
            'telepon' => $this->telepon,
            'alamat' => $this->alamat,
            'kelurahan' => $this->kelurahan,
            'kecamatan' => $this->kecamatan,
            'kabupaten' => $this->kabupaten,
            'provinsi' => $this->provinsi,
            'kode_pos' => $this->kodePos,
            'asal_sekolah' => $this->asalSekolah,
            'tahun_lulus_sekolah' => $this->tahunLulusSekolah,
            'pt_asal' => $this->ptAsal,
            'prodi_asal' => $this->prodiAsal,
            'is_calon_kip' => $this->calonKip,
            'nomor_kip' => $this->nomorKip,
        ];
    }
}
