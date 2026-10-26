<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\Gender;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The person, for a student registered directly by the registrar rather than
 * carried over from an admissions application.
 *
 * Holds exactly what Feeder refuses a biodata without, so a student entered
 * here is reportable on the first sync.
 */
final readonly class IdentitasMahasiswaData
{
    public function __construct(
        public string $nama,
        public string $email,
        public string $nik,
        public string $tempatLahir,
        public CarbonImmutable $tanggalLahir,
        public Gender $jenisKelamin,
        public ?string $namaIbu = null,
        public ?string $telepon = null,
        public ?string $alamat = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only([
            'nama', 'email', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
            'nama_ibu', 'telepon', 'alamat',
        ]));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $teks = static fn (mixed $nilai): ?string => filled($nilai) ? trim((string) $nilai) : null;

        return new self(
            nama: trim((string) $data['nama']),
            email: mb_strtolower(trim((string) $data['email'])),
            nik: trim((string) $data['nik']),
            tempatLahir: trim((string) $data['tempat_lahir']),
            tanggalLahir: CarbonImmutable::parse((string) $data['tanggal_lahir']),
            jenisKelamin: $data['jenis_kelamin'] instanceof Gender
                ? $data['jenis_kelamin']
                : Gender::from((string) $data['jenis_kelamin']),
            namaIbu: $teks($data['nama_ibu'] ?? null),
            telepon: $teks($data['telepon'] ?? null),
            alamat: $teks($data['alamat'] ?? null),
        );
    }

    /** @return array<string, mixed> the mahasiswa columns this sets */
    public function kolom(): array
    {
        return [
            'nama' => $this->nama,
            'email' => $this->email,
            'nik' => $this->nik,
            'tempat_lahir' => $this->tempatLahir,
            'tanggal_lahir' => $this->tanggalLahir->toDateString(),
            'jenis_kelamin' => $this->jenisKelamin,
            'nama_ibu' => $this->namaIbu,
            'telepon' => $this->telepon,
            'alamat' => $this->alamat,
        ];
    }
}
