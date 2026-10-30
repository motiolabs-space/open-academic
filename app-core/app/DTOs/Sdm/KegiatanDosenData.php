<?php

declare(strict_types=1);

namespace App\DTOs\Sdm;

use App\Enums\BentukKeterlibatanKebijakan;
use App\Enums\JenisBuktiKebijakan;
use App\Enums\JenisLuaran;
use App\Enums\LecturerAssignmentType;
use App\Enums\PeranKegiatan;
use App\Enums\TingkatKebijakan;
use App\Enums\TingkatKegiatan;
use App\Enums\UnsurBkd;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An activity as a lecturer records it on their portfolio.
 *
 * Never carries a verification flag: whatever arrives here is a claim, and
 * only the verification screen turns it into evidence.
 */
final readonly class KegiatanDosenData
{
    public function __construct(
        public LecturerAssignmentType $jenis,
        public UnsurBkd $unsur,
        public string $judul,
        public string $tanggalMulai,
        public ?string $tanggalSelesai = null,
        public ?PeranKegiatan $peran = null,
        public ?TingkatKegiatan $tingkat = null,
        public ?string $mitraNama = null,
        public ?string $mitraJenis = null,
        public ?string $lokasi = null,
        public ?float $sksEkuivalen = null,
        public ?JenisLuaran $luaranJenis = null,
        public ?string $luaranIdentitas = null,
        public ?int $luaranTahun = null,
        public ?string $keterangan = null,
        public ?BentukKeterlibatanKebijakan $kebijakanBentuk = null,
        public ?TingkatKebijakan $kebijakanTingkat = null,
        public ?JenisBuktiKebijakan $kebijakanBuktiJenis = null,
        public ?string $kebijakanNomorBukti = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $teks = fn (string $kunci): ?string => filled($data[$kunci] ?? null) ? trim((string) $data[$kunci]) : null;
        $jenis = LecturerAssignmentType::from($data['jenis']);

        // Policy fields belong to the policy type only. A form switched from
        // "penyusunan kebijakan" to "penelitian" still posts them, and a
        // research row claiming a drafting level would count for IKU 8 the
        // day somebody reads the column without the type.
        $kebijakan = $jenis->untukIku8();

        return new self(
            jenis: $jenis,
            unsur: UnsurBkd::from($data['unsur']),
            judul: trim((string) $data['judul']),
            tanggalMulai: (string) $data['tanggal_mulai'],
            tanggalSelesai: $teks('tanggal_selesai'),
            peran: PeranKegiatan::tryFrom((string) ($data['peran'] ?? '')),
            tingkat: TingkatKegiatan::tryFrom((string) ($data['tingkat'] ?? '')),
            mitraNama: $teks('mitra_nama'),
            mitraJenis: $teks('mitra_jenis'),
            lokasi: $teks('lokasi'),
            sksEkuivalen: filled($data['sks_ekuivalen'] ?? null) ? (float) $data['sks_ekuivalen'] : null,
            luaranJenis: JenisLuaran::tryFrom((string) ($data['luaran_jenis'] ?? '')),
            luaranIdentitas: $teks('luaran_identitas'),
            luaranTahun: filled($data['luaran_tahun'] ?? null) ? (int) $data['luaran_tahun'] : null,
            keterangan: $teks('keterangan'),
            kebijakanBentuk: $kebijakan ? BentukKeterlibatanKebijakan::tryFrom((string) ($data['kebijakan_bentuk'] ?? '')) : null,
            kebijakanTingkat: $kebijakan ? TingkatKebijakan::tryFrom((string) ($data['kebijakan_tingkat'] ?? '')) : null,
            kebijakanBuktiJenis: $kebijakan ? JenisBuktiKebijakan::tryFrom((string) ($data['kebijakan_bukti_jenis'] ?? '')) : null,
            kebijakanNomorBukti: $kebijakan ? $teks('kebijakan_nomor_bukti') : null,
        );
    }

    /**
     * The columns this activity writes.
     *
     * @return array<string, mixed>
     */
    public function kolom(): array
    {
        return [
            'jenis' => $this->jenis,
            'unsur' => $this->unsur,
            'judul' => $this->judul,
            'peran' => $this->peran,
            'tingkat' => $this->tingkat,
            'mitra_nama' => $this->mitraNama,
            'mitra_jenis' => $this->mitraJenis,
            'lokasi' => $this->lokasi,
            'tanggal_mulai' => $this->tanggalMulai,
            'tanggal_selesai' => $this->tanggalSelesai,
            'sks_ekuivalen' => $this->sksEkuivalen,
            'luaran_jenis' => $this->luaranJenis,
            'luaran_identitas' => $this->luaranIdentitas,
            'luaran_tahun' => $this->luaranTahun,
            'keterangan' => $this->keterangan,
            'kebijakan_bentuk' => $this->kebijakanBentuk,
            'kebijakan_tingkat' => $this->kebijakanTingkat,
            'kebijakan_bukti_jenis' => $this->kebijakanBuktiJenis,
            'kebijakan_nomor_bukti' => $this->kebijakanNomorBukti,
        ];
    }
}
