<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Models\Akademik\Nilai;
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
    public function __construct(private readonly BrandingService $brand) {}

    /** @return array<string, mixed> */
    public function data(Mahasiswa $mahasiswa): array
    {
        $mahasiswa->loadMissing(['prodi.fakultas', 'kurikulum']);

        $nilai = $this->nilaiTranskrip($mahasiswa);

        $sks = (int) $nilai->sum(fn (Nilai $n): int => (int) $n->krsDetail->sks);
        $mutu = (float) $nilai->sum(fn (Nilai $n): float => (float) $n->bobot * (int) $n->krsDetail->sks);

        return [
            'mahasiswa' => $mahasiswa,
            'institusi' => $this->brand->institutionName(),
            'kodeInstitusi' => $this->brand->institutionCode(),
            'perSemester' => $this->kelompokPerSemester($nilai),
            'totalSks' => $sks,
            'ipk' => $sks > 0 ? round($mutu / $sks, 2) : 0.0,
            'predikat' => Yudisium::predikatUntuk($sks > 0 ? $mutu / $sks : 0),
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
     * Finalised grades, best attempt per course.
     *
     * @return Collection<int, Nilai>
     */
    private function nilaiTranskrip(Mahasiswa $mahasiswa): Collection
    {
        return Nilai::query()
            ->with(['krsDetail.krs.tahunAkademik', 'kelasKuliah.mataKuliah'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->final()
            ->whereNotNull('nilai_huruf')
            ->get()
            ->groupBy(fn (Nilai $n): int => (int) $n->kelasKuliah->mata_kuliah_id)
            ->map(fn (Collection $percobaan): Nilai => $percobaan->sortByDesc(
                fn (Nilai $n): float => (float) $n->bobot,
            )->first())
            ->values();
    }

    /**
     * @param Collection<int, Nilai> $nilai
     * @return Collection<string, Collection<int, Nilai>>
     */
    private function kelompokPerSemester(Collection $nilai): Collection
    {
        return $nilai
            ->sortBy(fn (Nilai $n): string => $n->krsDetail->krs->tahunAkademik->kode
                .$n->kelasKuliah->mataKuliah->kode)
            ->groupBy(fn (Nilai $n): string => $n->krsDetail->krs->tahunAkademik->nama);
    }
}
