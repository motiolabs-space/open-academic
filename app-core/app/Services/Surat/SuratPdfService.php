<?php

declare(strict_types=1);

namespace App\Services\Surat;

use App\Enums\JenisSurat;
use App\Exceptions\AturanAkademikException;
use App\Models\Surat\Surat;
use App\Services\Akademik\TranskripService;
use App\Services\Branding\BrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Rendering an issued letter.
 *
 * Every field comes from the frozen snapshot on the record; the only thing
 * computed here is the QR square, and that encodes a URL rather than any fact.
 * A PDF reprinted next year is byte-for-byte about the same moment as the copy
 * the applicant already filed.
 *
 * The legalised transcript is the single exception, and deliberately so: the
 * course list is a report of a record rather than an assertion about a moment,
 * and reprinting one that omitted a grade correction would be reprinting a
 * mistake.
 */
class SuratPdfService
{
    public function __construct(
        private readonly PembuatQr $qr,
        private readonly BrandingService $brand,
        private readonly TranskripService $transkrip,
    ) {}

    public function pdf(Surat $surat): PdfDocument
    {
        if (!$surat->status->dapatDiunduh()) {
            throw new AturanAkademikException(
                'Hanya surat yang berstatus terbit yang dapat diunduh. Status saat ini: '
                    .$surat->status->label().'.',
            );
        }

        return Pdf::loadView($surat->jenis->tampilan(), $this->data($surat))
            ->setPaper('a4', 'portrait');
    }

    /** @return array<string, mixed> */
    public function data(Surat $surat): array
    {
        $tautan = route('verifikasi.surat', $surat->uuid);

        $data = [
            'surat' => $surat,
            'isi' => (array) $surat->konten,
            'logo' => $this->brand->logoUrl(),
            'tautanVerifikasi' => $tautan,

            // Null when generation failed. The templates fall back to printing
            // the URL, so a letter still leaves with a way to check it.
            'qr' => $this->qr->dataUri($tautan),
        ];

        if ($surat->jenis === JenisSurat::TranskripLegalisir) {
            $data['transkrip'] = $this->transkrip->data($surat->mahasiswa);
        }

        return $data;
    }

    public function namaBerkas(Surat $surat): string
    {
        return sprintf(
            '%s-%s-%s.pdf',
            $surat->jenis->kode(),
            $surat->isi('nim', 'tanpa-nim'),
            str_replace('/', '-', (string) $surat->nomor),
        );
    }
}
