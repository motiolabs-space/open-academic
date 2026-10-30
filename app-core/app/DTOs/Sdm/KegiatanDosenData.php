<?php

declare(strict_types=1);

namespace App\DTOs\Sdm;

use App\Enums\BentukKeterlibatanKebijakan;
use App\Enums\IndeksPublikasi;
use App\Enums\JenisBuktiKebijakan;
use App\Enums\JenisLuaran;
use App\Enums\KuartilPublikasi;
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
        public ?IndeksPublikasi $luaranIndeks = null,
        public ?KuartilPublikasi $luaranKuartil = null,
        public ?string $luaranDoi = null,
        public ?string $luaranTautan = null,
        public ?string $luaranPenerbit = null,
        public ?bool $luaranKolaborasiInternasional = null,
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

        // Same for publication facts: only a journal article or proceedings
        // paper has an index, and only a journal in Scopus or WoS a quartile.
        $luaran = JenisLuaran::tryFrom((string) ($data['luaran_jenis'] ?? ''));
        $publikasi = $luaran?->publikasi() ?? false;
        $indeks = $publikasi ? IndeksPublikasi::tryFrom((string) ($data['luaran_indeks'] ?? '')) : null;
        $berkuartil = $luaran === JenisLuaran::Jurnal && ($indeks?->bereputasiInternasional() ?? false);

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
            luaranJenis: $luaran,
            luaranIdentitas: $teks('luaran_identitas'),
            luaranTahun: filled($data['luaran_tahun'] ?? null) ? (int) $data['luaran_tahun'] : null,
            keterangan: $teks('keterangan'),
            kebijakanBentuk: $kebijakan ? BentukKeterlibatanKebijakan::tryFrom((string) ($data['kebijakan_bentuk'] ?? '')) : null,
            kebijakanTingkat: $kebijakan ? TingkatKebijakan::tryFrom((string) ($data['kebijakan_tingkat'] ?? '')) : null,
            kebijakanBuktiJenis: $kebijakan ? JenisBuktiKebijakan::tryFrom((string) ($data['kebijakan_bukti_jenis'] ?? '')) : null,
            kebijakanNomorBukti: $kebijakan ? $teks('kebijakan_nomor_bukti') : null,
            luaranIndeks: $indeks,
            luaranKuartil: $berkuartil ? KuartilPublikasi::tryFrom((string) ($data['luaran_kuartil'] ?? '')) : null,
            luaranDoi: $publikasi ? self::doi($teks('luaran_doi')) : null,
            luaranTautan: $publikasi ? $teks('luaran_tautan') : null,
            luaranPenerbit: $publikasi ? $teks('luaran_penerbit') : null,
            luaranKolaborasiInternasional: $publikasi && array_key_exists('luaran_kolaborasi_internasional', $data)
                ? (bool) $data['luaran_kolaborasi_internasional']
                : null,
        );
    }

    /**
     * A DOI in its bare, lower-case form: "https://doi.org/10.1000/ABC" and
     * "doi:10.1000/abc" are the same article, and two co-authors' rows must
     * compare equal for IKU 6 to count it once.
     */
    public static function doi(?string $doi): ?string
    {
        if ($doi === null) {
            return null;
        }

        $bersih = preg_replace('~^(https?://(dx\.)?doi\.org/|doi:\s*)~i', '', trim($doi));

        return $bersih === '' ? null : mb_strtolower((string) $bersih);
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
            'luaran_indeks' => $this->luaranIndeks,
            'luaran_kuartil' => $this->luaranKuartil,
            'luaran_doi' => $this->luaranDoi,
            'luaran_tautan' => $this->luaranTautan,
            'luaran_penerbit' => $this->luaranPenerbit,
            'luaran_kolaborasi_internasional' => $this->luaranKolaborasiInternasional,
        ];
    }
}
