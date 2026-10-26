<?php

declare(strict_types=1);

namespace App\Services\Pmb;

use App\Enums\ApplicantStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Dokumen\PengaturanDokumen;
use App\Services\Surat\PembuatQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * The applicant's exam card: who they are, where and when to sit.
 *
 * Same frame as the student exam card (pdf.cetak.layout, letterhead and
 * signatory from PengaturanDokumen), separate from CetakService because the
 * subject is not a student and the eligibility rule is a different one.
 *
 * The QR carries the registration number only — the value an invigilator
 * matches against the score sheet that will be imported afterwards — and no
 * NIK, which is printed nowhere on the card.
 */
class KartuUjianPendaftarService
{
    public function __construct(
        private readonly PengaturanDokumen $pengaturan,
        private readonly PembuatQr $qr,
    ) {}

    /**
     * Whether the card may be printed, and why not.
     *
     * @return array{layak: bool, alasan: array<int, string>}
     */
    public function kelayakan(PmbPendaftar $pendaftar): array
    {
        $pendaftar->loadMissing('pembayaran');
        $alasan = [];

        if ($pendaftar->status === ApplicantStatus::Batal) {
            $alasan[] = __('pmb.kartu.alasan_batal');
        } elseif (!in_array($pendaftar->status, [
            ApplicantStatus::Seleksi, ApplicantStatus::Lulus, ApplicantStatus::TidakLulus,
            ApplicantStatus::DaftarUlang, ApplicantStatus::Mahasiswa,
        ], true)) {
            $alasan[] = __('pmb.kartu.alasan_status');
        }

        if (!$pendaftar->formulirLunas()) {
            $alasan[] = __('pmb.kartu.alasan_bayar');
        }

        return ['layak' => $alasan === [], 'alasan' => $alasan];
    }

    public function pdf(PmbPendaftar $pendaftar): PdfDocument
    {
        $pendaftar->loadMissing(['gelombang.tahunAkademik', 'gelombang.jadwalSeleksi', 'prodiPilihan1', 'prodiPilihan2', 'berkas']);

        $kelayakan = $this->kelayakan($pendaftar);

        if (!$kelayakan['layak']) {
            throw new AturanAkademikException(__('pmb.kartu.belum').' '.implode(' ', $kelayakan['alasan']));
        }

        return Pdf::loadView('pdf.cetak.kartu-ujian-pmb', [
            'dok' => $this->pengaturan->untuk('kartu_ujian_pmb'),
            'pendaftar' => $pendaftar,
            'subjudul' => $pendaftar->gelombang->nama.' · '.$pendaftar->gelombang->tahunAkademik->nama,
            'jadwal' => $pendaftar->gelombang->jadwalSeleksi,
            'qr' => $this->qr->dataUri($pendaftar->nomor_pendaftaran, 90),
        ])->setPaper('a4', 'portrait');
    }
}
