<?php

declare(strict_types=1);

namespace App\Services\Dokumen;

use App\Enums\KrsStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Services\Akademik\PresensiService;
use App\Services\Surat\PembuatQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;

/**
 * The four documents a campus prints every single term.
 *
 * Unlike letters and transcripts, none of these is a frozen record: they are
 * *renderings of the current state*, printed to be carried into a room. A
 * register reprinted after a student adds a course should show that student —
 * which is exactly the opposite of the rule governing an issued letter, and the
 * reason they do not share a service with one.
 */
class CetakService
{
    public function __construct(
        private readonly PengaturanDokumen $pengaturan,
        private readonly PresensiService $presensi,
        private readonly PembuatQr $qr,
    ) {}

    /**
     * Student ID card.
     *
     * Carries **NIM, name and programme only**. Not the NIK, not the home
     * address, not the parents — a card is the single most frequently lost
     * document a student owns, and everything printed on it is disclosed with
     * it.
     *
     * The QR encodes the NIM, which is already printed beside it in plain text,
     * so scanning discloses nothing the card does not. It is deliberately *not*
     * a verification URL: that would mean a new public endpoint confirming
     * whether a given person is enrolled here, and a card is exactly the thing
     * that gets lost, photographed, and posted.
     */
    public function ktm(Mahasiswa $mahasiswa): PdfDocument
    {
        $mahasiswa->loadMissing(['prodi.fakultas']);

        return Pdf::loadView('pdf.cetak.ktm', [
            'dok' => $this->pengaturan->untuk('ktm'),
            'mahasiswa' => $mahasiswa,
            'subjudul' => $mahasiswa->prodi?->nama,
            'qr' => $this->qr->dataUri($mahasiswa->nim, 110),
            'margin' => '14mm',

            // The card carries its own signature and footnote on the back.
            'blokSendiri' => true,
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Exam card for one study plan.
     *
     * @throws AturanAkademikException when the student is not eligible
     */
    public function kartuUjian(Krs $krs): PdfDocument
    {
        $krs->loadMissing([
            'mahasiswa.prodi',
            'tahunAkademik',
            'detail.kelasKuliah.mataKuliah',
            'detail.kelasKuliah.jadwal.ruang',
        ]);

        $kelayakan = $this->kelayakanKartuUjian($krs);

        if (!$kelayakan['layak']) {
            throw new AturanAkademikException(
                'Kartu ujian belum dapat dicetak. '.implode(' ', $kelayakan['alasan']),
            );
        }

        return Pdf::loadView('pdf.cetak.kartu-ujian', [
            'dok' => $this->pengaturan->untuk('kartu_ujian'),
            'krs' => $krs,
            'mahasiswa' => $krs->mahasiswa,
            'subjudul' => $krs->tahunAkademik->nama,

            // Courses the student may not sit are listed and marked rather than
            // dropped: a card that is simply shorter than the study plan sends
            // the student to the invigilator to find out why.
            'baris' => $this->barisKartuUjian($krs),

            // NIM again, for the invigilator's scanner.
            'qr' => $this->qr->dataUri($krs->mahasiswa->nim, 90),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Whether an exam card may be printed at all, and why not.
     *
     * Both conditions are **policy, not fact** — some campuses withhold cards
     * over unpaid fees, some do not, and some only for finals. So each is
     * switchable, and each refusal states its reason rather than the card
     * merely failing to appear.
     *
     * @return array{layak: bool, alasan: array<int, string>}
     */
    public function kelayakanKartuUjian(Krs $krs): array
    {
        $alasan = [];

        if ($krs->status !== KrsStatus::Disetujui) {
            $alasan[] = 'Rencana studi belum disetujui dosen wali (status: '
                .$krs->status->label().').';
        }

        if (config('dokumen.kartu_ujian.tahan_bila_menunggak')) {
            $tunggakan = Tagihan::query()
                ->where('mahasiswa_id', $krs->mahasiswa_id)
                ->belumLunas()
                ->get()
                ->sum(fn (Tagihan $t): int => $t->sisa());

            if ($tunggakan > 0) {
                $alasan[] = 'Masih ada tagihan yang belum lunas sebesar Rp '
                    .number_format($tunggakan / 100, 0, ',', '.').'.';
            }
        }

        return ['layak' => $alasan === [], 'alasan' => $alasan];
    }

    /**
     * One row per enrolled class, each marked eligible or not.
     *
     * Eligibility per course is asked of PresensiService rather than recomputed:
     * the same threshold decides who may sit the final elsewhere, and two copies
     * would disagree about the student sitting exactly on the line.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function barisKartuUjian(Krs $krs): Collection
    {
        $tahanKehadiran = (bool) config('dokumen.kartu_ujian.tahan_bila_kehadiran_kurang');

        return $krs->detail
            ->map(function ($detail) use ($krs, $tahanKehadiran): array {
                $kelas = $detail->kelasKuliah;
                $persen = $this->presensi->persenKehadiran($krs->mahasiswa, $kelas);

                $layak = !$tahanKehadiran || $this->presensi->layakUas($krs->mahasiswa, $kelas);

                return [
                    'kelas' => $kelas,
                    'jadwal' => $kelas->jadwal->first(),
                    'persen' => $persen,
                    'layak' => $layak,
                ];
            })
            ->sortBy(fn (array $b): string => $b['kelas']->mataKuliah->kode)
            ->values();
    }

    /**
     * Attendance register for one class — the paper sheet passed around a room.
     *
     * Deliberately blank in the signature columns. This is the sheet a campus
     * still keeps on paper because a signature in ink is the evidence, and
     * printing the digital attendance into it would replace the evidence with a
     * copy of what the system already believes.
     */
    public function absensi(KelasKuliah $kelas): PdfDocument
    {
        $kelas->loadMissing(['mataKuliah', 'dosen', 'jadwal.ruang', 'tahunAkademik']);

        return Pdf::loadView('pdf.cetak.absensi', [
            'dok' => $this->pengaturan->untuk('absensi'),
            'kelas' => $kelas,
            'subjudul' => $kelas->mataKuliah->nama.' · '.$kelas->namaLengkap(),
            'peserta' => $this->presensi->pesertaKelas($kelas),
            'jumlahPertemuan' => (int) config('academic.attendance.meetings_per_term', 16),
            'margin' => '14mm 12mm',
        ])->setPaper('a4', 'landscape');
    }

    /**
     * Teaching journal for one class, as delivered.
     *
     * Prints what was recorded and leaves the rest empty, because the gap is the
     * point: a journal with four of fourteen meetings filled is the finding a
     * monitoring visit is looking for, and a sheet that hid the empty rows would
     * hide it.
     */
    public function jurnal(KelasKuliah $kelas): PdfDocument
    {
        $kelas->loadMissing(['mataKuliah', 'dosen', 'tahunAkademik', 'pertemuan']);

        return Pdf::loadView('pdf.cetak.jurnal', [
            'dok' => $this->pengaturan->untuk('jurnal'),
            'kelas' => $kelas,
            'subjudul' => $kelas->mataKuliah->nama.' · '.$kelas->namaLengkap(),
            'pertemuan' => $kelas->pertemuan->sortBy('pertemuan_ke')->values(),
            'jumlahPertemuan' => (int) config('academic.attendance.meetings_per_term', 16),
            'margin' => '14mm 12mm',
        ])->setPaper('a4', 'landscape');
    }
}
