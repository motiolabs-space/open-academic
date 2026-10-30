<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\BentukKepesertaan;
use App\Enums\BidangLomba;
use App\Enums\JenisRekognisi;
use App\Enums\ModaPelaksanaan;
use App\Enums\PeringkatPrestasi;
use App\Enums\TingkatSimkatmawa;
use App\Http\Requests\Kemahasiswaan\DataSimkatmawaRequest;
use Illuminate\Http\Request;

/**
 * The SIMKATMAWA details of one achievement, as staff filled them in.
 *
 * Every field may be empty: the form is saved as far as it goes, and the
 * export screen says what is still missing.
 */
final readonly class DataSimkatmawa
{
    public function __construct(
        public ?TingkatSimkatmawa $tingkat = null,
        public ?PeringkatPrestasi $peringkat = null,
        public ?BentukKepesertaan $bentukKepesertaan = null,
        public ?ModaPelaksanaan $modaPelaksanaan = null,
        public ?BidangLomba $bidangLomba = null,
        public ?JenisRekognisi $jenisRekognisi = null,
        public ?string $penyelenggara = null,
        public ?string $tanggalSelesai = null,
        public ?int $jumlahPtPeserta = null,
        public ?int $jumlahNegaraPeserta = null,
        public ?string $nomorSertifikat = null,
        public ?string $tautanSertifikat = null,
        public ?string $tautanBukti = null,
        public ?string $tautanSuratTugas = null,
        public ?int $dosenPembimbingId = null,
    ) {}

    public static function fromRequest(DataSimkatmawaRequest $request): self
    {
        return self::fromInput($request, $request->dosenPembimbingId());
    }

    /**
     * From any request validated against DataSimkatmawaRequest::aturanRincian()
     * — staff's form, or the student's own achievement report, which has no
     * supervising lecturer field.
     */
    public static function fromInput(Request $request, ?int $dosenPembimbingId = null): self
    {
        $teks = fn (string $kunci): ?string => $request->filled($kunci) ? trim((string) $request->string($kunci)) : null;
        $angka = fn (string $kunci): ?int => $request->filled($kunci) ? $request->integer($kunci) : null;

        return new self(
            tingkat: TingkatSimkatmawa::tryFrom((string) $request->input('tingkat')),
            peringkat: PeringkatPrestasi::tryFrom((string) $request->input('peringkat')),
            bentukKepesertaan: BentukKepesertaan::tryFrom((string) $request->input('bentuk_kepesertaan')),
            modaPelaksanaan: ModaPelaksanaan::tryFrom((string) $request->input('moda_pelaksanaan')),
            bidangLomba: BidangLomba::tryFrom((string) $request->input('bidang_lomba')),
            jenisRekognisi: JenisRekognisi::tryFrom((string) $request->input('jenis_rekognisi')),
            penyelenggara: $teks('penyelenggara'),
            tanggalSelesai: $teks('tanggal_selesai'),
            jumlahPtPeserta: $angka('jumlah_pt_peserta'),
            jumlahNegaraPeserta: $angka('jumlah_negara_peserta'),
            nomorSertifikat: $teks('nomor_sertifikat'),
            tautanSertifikat: $teks('tautan_sertifikat'),
            tautanBukti: $teks('tautan_bukti'),
            tautanSuratTugas: $teks('tautan_surat_tugas'),
            dosenPembimbingId: $dosenPembimbingId,
        );
    }

    /** @return array<string, mixed> column => value, for the ledger row */
    public function kolom(): array
    {
        return [
            'tingkat' => $this->tingkat,
            'peringkat' => $this->peringkat,
            'bentuk_kepesertaan' => $this->bentukKepesertaan,
            'moda_pelaksanaan' => $this->modaPelaksanaan,
            'bidang_lomba' => $this->bidangLomba,
            'jenis_rekognisi' => $this->jenisRekognisi,
            'penyelenggara' => $this->penyelenggara,
            'tanggal_selesai' => $this->tanggalSelesai,
            'jumlah_pt_peserta' => $this->jumlahPtPeserta,
            'jumlah_negara_peserta' => $this->jumlahNegaraPeserta,
            'nomor_sertifikat' => $this->nomorSertifikat,
            'tautan_sertifikat' => $this->tautanSertifikat,
            'tautan_bukti' => $this->tautanBukti,
            'tautan_surat_tugas' => $this->tautanSuratTugas,
            'dosen_pembimbing_id' => $this->dosenPembimbingId,
        ];
    }
}
