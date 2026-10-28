<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Services\Branding\BrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;

/**
 * Kartu Hasil Studi, on screen and on paper, for the student and for staff.
 *
 * The KHS is not a table of its own: it is the finalised grades of a term read
 * against the enrolment record that froze that term's IPS/IPK. Keeping it
 * derived means a grade correction can never leave a stale KHS behind. Both
 * portals read it from here, so the student's copy and the registrar's copy
 * of the same term cannot disagree.
 */
class KhsService
{
    public function __construct(
        private readonly BrandingService $brand,
    ) {}

    /**
     * Per-term enrolment records, oldest first.
     *
     * @return Collection<int, StatusMahasiswa>
     */
    public function riwayat(Mahasiswa $mahasiswa): Collection
    {
        return $mahasiswa->statusPerSemester()
            ->with('tahunAkademik')
            ->orderBy('tahun_akademik_id')
            ->get();
    }

    /**
     * Finalised grades grouped by term id.
     *
     * @return Collection<int, Collection<int, Nilai>>
     */
    public function nilaiPerTerm(Mahasiswa $mahasiswa): Collection
    {
        return Nilai::query()
            ->with(['kelasKuliah.mataKuliah', 'krsDetail.krs'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->final()
            ->get()
            ->groupBy(fn (Nilai $nilai): int => $nilai->krsDetail->krs->tahun_akademik_id);
    }

    /**
     * One term's KHS. Only a finalised term has one: before the term is
     * closed its IPS is provisional, and a printed card would outlive it.
     *
     * @return array<string, mixed>
     */
    public function data(Mahasiswa $mahasiswa, TahunAkademik $term): array
    {
        $status = StatusMahasiswa::query()
            ->with('tahunAkademik')
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $term->id)
            ->first();

        if ($status === null || !$status->is_final) {
            throw new AturanAkademikException(__('kemahasiswaan.khs.belum_final', ['semester' => $term->nama]));
        }

        $mahasiswa->loadMissing(['prodi.fakultas', 'dosenWali']);

        return [
            'mahasiswa' => $mahasiswa,
            'status' => $status,
            'nilai' => $this->nilaiPerTerm($mahasiswa)->get($term->id, collect())
                ->sortBy(fn (Nilai $n): string => $n->kelasKuliah->mataKuliah->kode)
                ->values(),
            'institusi' => $this->brand->institutionName(),
            'kodeInstitusi' => $this->brand->institutionCode(),
            'diterbitkan' => now(),
        ];
    }

    public function pdf(Mahasiswa $mahasiswa, TahunAkademik $term): PdfDocument
    {
        return Pdf::loadView('pdf.khs', $this->data($mahasiswa, $term))
            ->setPaper('a4', 'portrait');
    }

    public function namaBerkas(Mahasiswa $mahasiswa, TahunAkademik $term): string
    {
        return 'KHS-'.$mahasiswa->nim.'-'.$term->kode.'.pdf';
    }
}
