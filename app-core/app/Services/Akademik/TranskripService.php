<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\PerolehanBaris;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Services\Branding\BrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;

/**
 * The official academic transcript.
 *
 * Only finalised grades appear, and a repeated course shows its best attempt —
 * the same rule the IPK uses, so the document and the number on it can never
 * disagree. Provisional grades are deliberately excluded: a transcript is a
 * statement of record, not a progress report.
 */
class TranskripService
{
    public function __construct(
        private readonly BrandingService $brand,
        private readonly PerolehanAkademik $perolehan,
    ) {}

    /** @return array<string, mixed> */
    public function data(Mahasiswa $mahasiswa): array
    {
        $mahasiswa->loadMissing(['prodi.fakultas', 'kurikulum']);

        // Same source as the IPK and the graduation checklist. This method used
        // to work out best-attempt-per-course for itself, which is how a
        // transcript comes to show credits the graduation screen does not count.
        $baris = $this->perolehan->untuk($mahasiswa);
        $angka = $this->perolehan->ringkas($baris);

        return [
            'mahasiswa' => $mahasiswa,
            'institusi' => $this->brand->institutionName(),
            'kodeInstitusi' => $this->brand->institutionCode(),
            'perSemester' => $this->kelompokPerPeriode($baris),
            'totalSks' => $angka['sksLulus'],
            'sksKonversi' => $angka['sksKonversi'],
            'adaKonversi' => $angka['sksKonversi'] > 0,
            'ipk' => $angka['ipk'],
            'predikat' => Yudisium::predikatUntuk($angka['ipk']),
            'diterbitkan' => now(),

            /*
             * This sheet is a copy, and now says so.
             *
             * It used to print a "verification code" — a hash of the student's
             * uuid — beside a sentence claiming the document was valid without
             * a wet signature if the code matched the institution's records.
             * There was nowhere to match it. Nobody could check anything, which
             * is worse than printing nothing: a code next to that sentence
             * invites a reader to believe somebody could.
             *
             * The verifiable version is a numbered, revocable letter of type
             * TranskripLegalisir. This one stays free and instant, and is
             * labelled for what it is.
             */
            'tautanVerifikasi' => route('verifikasi.formulir'),
        ];
    }

    public function pdf(Mahasiswa $mahasiswa): PdfDocument
    {
        return Pdf::loadView('pdf.transkrip', $this->data($mahasiswa))
            ->setPaper('a4', 'portrait');
    }

    public function namaBerkas(Mahasiswa $mahasiswa): string
    {
        return 'Transkrip-'.$mahasiswa->nim.'-'.now()->format('Ymd').'.pdf';
    }

    /**
     * Groups the rows under the heading they were earned in.
     *
     * For taught courses that is the academic term. For recognised credit there
     * is no term — the study happened before this campus was involved — so it
     * groups under the source institution, or the kind of recognition when the
     * source was employment rather than a campus.
     *
     * @param Collection<int, PerolehanBaris> $baris
     * @return Collection<string, Collection<int, PerolehanBaris>>
     */
    private function kelompokPerPeriode(Collection $baris): Collection
    {
        return $baris
            ->sortBy(fn (PerolehanBaris $b): string => sprintf(
                // Conversions sort last: a reader works down the terms and then
                // meets the recognised block, rather than finding it wedged
                // between two semesters it did not happen in.
                '%d-%s-%s',
                $b->konversi ? 1 : 0,
                $b->periode ?? '',
                $b->kode,
            ))
            ->groupBy(fn (PerolehanBaris $b): string => $b->periode ?? 'Lainnya');
    }
}
