<?php

declare(strict_types=1);

namespace App\Services\Surat;

use App\Enums\JenisSurat;
use App\Enums\StatusSurat;
use App\Models\Surat\Surat;

/**
 * Answering "is this piece of paper real?" for somebody outside the campus.
 *
 * The audience is a bank clerk, an embassy officer, an employer — people with no
 * account here and no reason to get one. So the page is public, and everything
 * about this class follows from that.
 *
 * **It says as little as it can.** The reader is holding the document; the name
 * and number on the screen exist so they can compare the two, and nothing beyond
 * that is any of their business. No national identity number, no address, no
 * grades, no financial standing.
 *
 * **It distinguishes three answers, not two.** Genuine-and-current,
 * genuine-but-no-longer-current, and not-found. Collapsing the middle case into
 * either of the others is what makes verification useless: an expired
 * certificate of enrolment is not a forgery, and a revoked letter is not valid.
 */
class VerifikasiSurat
{
    /** Looks up by the unguessable handle printed in the QR. */
    public function lewatUuid(string $uuid): ?Surat
    {
        return Surat::query()
            ->with('mahasiswa.prodi')
            ->where('uuid', $uuid)
            ->whereIn('status', [StatusSurat::Diterbitkan->value, StatusSurat::Dicabut->value])
            ->first();
    }

    /**
     * Manual lookup, for a paper whose QR will not scan.
     *
     * Requires the number **and** the student number, both of which are printed
     * on the document. Either alone would be guessable — the sequence is
     * predictable by design, and student numbers follow a published pattern —
     * and a public endpoint that confirms names from a guessed number is a
     * directory of everyone the campus has ever written to.
     */
    public function lewatNomor(string $nomor, string $nim): ?Surat
    {
        return Surat::query()
            ->with('mahasiswa.prodi')
            ->where('nomor', trim($nomor))
            ->whereHas('mahasiswa', fn ($q) => $q->where('nim', trim($nim)))
            ->whereIn('status', [StatusSurat::Diterbitkan->value, StatusSurat::Dicabut->value])
            ->first();
    }

    /**
     * What the verification page shows.
     *
     * @return array<string, mixed>
     */
    public function laporan(Surat $surat): array
    {
        return [
            'asli' => true,
            'berlaku' => $surat->berlaku(),
            'dicabut' => $surat->status === StatusSurat::Dicabut,
            'kedaluwarsa' => $surat->kedaluwarsa(),

            'jenis' => $surat->jenis->label(),
            'nomor' => $surat->nomor,
            'nama' => $surat->isi('nama'),
            'nim' => $surat->isi('nim'),
            'prodi' => $surat->isi('prodi'),
            'institusi' => $surat->isi('institusi'),
            'diterbitkan' => $surat->diterbitkan_at,
            'berlaku_sampai' => $surat->berlaku_sampai,
            'dicabut_pada' => $surat->dicabut_at,

            'catatan' => $this->catatan($surat),
        ];
    }

    /**
     * The sentence that does the actual work.
     *
     * A verification result of "genuine" alone is misleading for a document
     * that asserts a state: the certificate of enrolment in the reader's hand
     * may be authentic and describe a student who left last term. This says
     * which, in words a non-specialist can act on.
     */
    private function catatan(Surat $surat): string
    {
        if ($surat->status === StatusSurat::Dicabut) {
            return 'Dokumen ini asli, tetapi telah dicabut oleh penerbitnya dan tidak lagi berlaku.';
        }

        if ($surat->kedaluwarsa()) {
            return 'Dokumen ini asli dan diterbitkan secara sah, tetapi masa berlakunya sudah lewat. '
                .'Isinya menggambarkan keadaan pada tanggal penerbitan, bukan keadaan saat ini.';
        }

        return match ($surat->jenis) {
            JenisSurat::AktifKuliah => 'Dokumen ini asli dan masih berlaku. Keterangan di dalamnya '
                .'menggambarkan keadaan pada tanggal penerbitan.',
            JenisSurat::Skpi => 'Dokumen ini asli. Surat Keterangan Pendamping Ijazah tidak memiliki '
                .'masa berlaku.',
            default => 'Dokumen ini asli dan masih berlaku.',
        };
    }
}
