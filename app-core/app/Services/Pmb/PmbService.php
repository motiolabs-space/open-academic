<?php

declare(strict_types=1);

namespace App\Services\Pmb;

use App\Enums\ApplicantStatus;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Keuangan\TarifResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Admissions, and the one moment that matters in it: an applicant becoming a
 * student.
 *
 * Everything before that is paperwork that can be corrected. Registration
 * creates an identity that will carry a transcript, be reported to PDDIKTI, and
 * outlive the person's time on campus — so it happens once, in one transaction,
 * and never halfway.
 */
class PmbService
{
    public function __construct(
        private readonly NimGenerator $nim,
        private readonly TarifResolver $tarif,
    ) {}

    /** Records a selection decision. */
    public function luluskan(PmbPendaftar $pendaftar, Prodi $prodi, ?float $nilai = null): PmbPendaftar
    {
        if ($pendaftar->status === ApplicantStatus::Mahasiswa) {
            throw new AturanAkademikException('Pendaftar ini sudah menjadi mahasiswa.');
        }

        // Accepting somebody into a programme they never applied for is almost
        // always a mis-click on a long list, not a decision.
        if (!in_array($prodi->id, [$pendaftar->prodi_pilihan_1_id, $pendaftar->prodi_pilihan_2_id], true)) {
            throw new AturanAkademikException(
                "{$prodi->nama} bukan pilihan yang diajukan pendaftar ini.",
            );
        }

        $pendaftar->update([
            'status' => ApplicantStatus::Lulus,
            'prodi_diterima_id' => $prodi->id,
            'nilai_seleksi' => $nilai ?? $pendaftar->nilai_seleksi,
        ]);

        return $pendaftar->refresh();
    }

    public function tidakLuluskan(PmbPendaftar $pendaftar, ?string $catatan = null): PmbPendaftar
    {
        $pendaftar->update([
            'status' => ApplicantStatus::TidakLulus,
            'catatan' => $catatan ?? $pendaftar->catatan,
        ]);

        return $pendaftar->refresh();
    }

    /**
     * Turns an accepted applicant into a student, with an account and a bill.
     *
     * One transaction covering four writes: the student record, the applicant's
     * link to it, the intake invoice, and its line items. A partial success here
     * is the worst outcome available — a student who exists but owes nothing, or
     * a NIM issued to nobody.
     *
     * @return array{mahasiswa: Mahasiswa, kata_sandi: string, tagihan: ?Tagihan}
     */
    public function daftarUlang(PmbPendaftar $pendaftar, TahunAkademik $term): array
    {
        if ($pendaftar->status === ApplicantStatus::Mahasiswa || $pendaftar->mahasiswa_id !== null) {
            throw new AturanAkademikException(
                "{$pendaftar->nama} sudah terdaftar sebagai mahasiswa dengan NIM {$pendaftar->mahasiswa?->nim}.",
            );
        }

        if ($pendaftar->status !== ApplicantStatus::Lulus && $pendaftar->status !== ApplicantStatus::DaftarUlang) {
            throw new AturanAkademikException(
                'Hanya pendaftar yang sudah dinyatakan lulus seleksi yang dapat didaftarkan ulang.',
            );
        }

        $prodi = $pendaftar->prodiDiterima;

        if ($prodi === null) {
            throw new AturanAkademikException('Program studi penerimaan belum ditetapkan.');
        }

        $angkatan = $term->tahun_mulai;
        $kataSandi = $this->kataSandiSementara();

        $hasil = DB::transaction(function () use ($pendaftar, $prodi, $term, $angkatan, $kataSandi): array {
            $mahasiswa = Mahasiswa::create([
                'nim' => $this->nim->untuk($prodi, $angkatan),
                'nama' => $pendaftar->nama,
                'email' => $pendaftar->email,
                'password' => Hash::make($kataSandi),

                'prodi_id' => $prodi->id,
                'kurikulum_id' => $this->kurikulumBerlaku($prodi)?->id,
                'angkatan' => $angkatan,

                // Carried over so the eventual Feeder biodata push is not
                // blocked by data the applicant already provided.
                'nik' => $pendaftar->nik,
                'nisn' => $pendaftar->nisn,
                'tempat_lahir' => $pendaftar->tempat_lahir,
                'tanggal_lahir' => $pendaftar->tanggal_lahir,
                'jenis_kelamin' => $pendaftar->jenis_kelamin,
                'alamat' => $pendaftar->alamat,
                'telepon' => $pendaftar->telepon,

                'status' => StudentStatus::Aktif,
                'is_active' => true,
            ]);

            $mahasiswa->assignRole('mahasiswa');

            $pendaftar->update([
                'status' => ApplicantStatus::Mahasiswa,
                'mahasiswa_id' => $mahasiswa->id,
            ]);

            return [
                'mahasiswa' => $mahasiswa,
                'tagihan' => $this->terbitkanTagihanAwal($mahasiswa, $term),
            ];
        });

        return [...$hasil, 'kata_sandi' => $kataSandi];
    }

    /**
     * The intake invoice, built from whatever tariffs the campus has configured.
     *
     * Returns null rather than an empty invoice when no tariff applies: a bill
     * for zero rupiah looks like a settled debt on every screen that reads it,
     * and a finance office that has not set its rates yet is better served by
     * an obvious absence.
     */
    public function terbitkanTagihanAwal(Mahasiswa $mahasiswa, TahunAkademik $term): ?Tagihan
    {
        // Resolved through the shared rule rather than queried here: this used
        // to sum every matching row, so a student covered by both a general
        // schedule and a programme override was billed the sum of the two.
        $tarif = $this->tarif->untuk($mahasiswa, $term);

        if ($tarif->isEmpty()) {
            return null;
        }

        $total = (int) $tarif->sum('nominal');

        $tagihan = Tagihan::create([
            'nomor' => $this->nomorTagihan($term),
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
            'keterangan' => 'Registrasi & biaya kuliah '.$term->nama,
            'total' => $total,
            'terbayar' => 0,
            'status' => InvoiceStatus::BelumBayar,
            'jatuh_tempo' => ($term->krs_selesai ?? $term->tanggal_mulai)->copy()->addWeeks(2),
        ]);

        foreach ($tarif as $baris) {
            TagihanItem::create([
                'tagihan_id' => $tagihan->id,
                'tarif_id' => $baris->id,
                'nama' => $baris->nama,
                'nominal' => $baris->nominal,
            ]);
        }

        return $tagihan;
    }

    /** The curriculum a new intake is bound to for the whole degree. */
    private function kurikulumBerlaku(Prodi $prodi): ?Kurikulum
    {
        return Kurikulum::query()
            ->where('prodi_id', $prodi->id)
            ->where('is_active', true)
            ->first();
    }

    private function nomorTagihan(TahunAkademik $term): string
    {
        return 'INV-'.$term->kode.'-'.Str::upper(Str::random(8));
    }

    private function kataSandiSementara(): string
    {
        return Str::lower(Str::random(4)).'-'.Str::lower(Str::random(4)).'-'.random_int(100, 999);
    }
}
