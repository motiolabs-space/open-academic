<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Enums\JenisKonversi;
use App\Enums\StatusKonversi;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Recognising credit earned somewhere else.
 *
 * The admissions module has accepted "transfer" and "rpl" intakes since it was
 * built, with nowhere to record what those students already had. A student
 * admitted into their fifth semester therefore started from zero credits and
 * could never reach a graduation requirement written for eight semesters.
 *
 * Recognition is generous by nature, so the rules here are all about the ways
 * generosity goes wrong. Every one of them fails silently if unenforced: nobody
 * complains about a degree that was too easy to obtain.
 */
class KonversiService
{
    public function __construct(private readonly PerolehanAkademik $perolehan) {}

    /**
     * Proposes a conversion. Nothing is granted until somebody approves it.
     */
    public function ajukan(
        Mahasiswa $mahasiswa,
        MataKuliah $mataKuliah,
        JenisKonversi $jenis,
        string $asalNama,
        ?string $asalInstitusi = null,
        ?int $asalSks = null,
        ?string $asalNilai = null,
        ?string $berkasPath = null,
    ): KonversiKredit {
        if ($jenis->perluInstitusi() && blank($asalInstitusi)) {
            throw new AturanAkademikException(
                'Konversi transfer wajib menyebutkan perguruan tinggi asal — transkrip tanpa penerbit '
                    .'tidak membuktikan apa pun.',
            );
        }

        $this->pastikanBelumDitempuh($mahasiswa, $mataKuliah);
        $this->pastikanBelumDikonversi($mahasiswa, $mataKuliah);

        return KonversiKredit::create([
            'mahasiswa_id' => $mahasiswa->id,
            'mata_kuliah_id' => $mataKuliah->id,
            'jenis' => $jenis,
            'status' => StatusKonversi::Diajukan,
            'asal_institusi' => $asalInstitusi,
            'asal_nama' => $asalNama,
            'asal_sks' => $asalSks,
            'asal_nilai' => $asalNilai,

            // A proposal carries the local course's full weight until somebody
            // decides otherwise; approval is where the number is settled.
            'sks_diakui' => (int) $mataKuliah->sks,

            'berkas_path' => $berkasPath,
        ]);
    }

    /**
     * Grants the recognition.
     *
     * @param int|null $sksDiakui null keeps what was proposed
     * @param string|null $huruf null records the credit without a grade,
     *                           which is ordinary for RPL
     */
    public function setujui(
        KonversiKredit $konversi,
        Staff $staff,
        ?int $sksDiakui = null,
        ?string $huruf = null,
        ?string $catatan = null,
    ): KonversiKredit {
        if ($konversi->status !== StatusKonversi::Diajukan) {
            throw new AturanAkademikException(
                'Hanya usulan yang masih menunggu yang dapat diputuskan. Status saat ini: '
                    .$konversi->status->label().'.',
            );
        }

        $konversi->loadMissing(['mahasiswa.prodi', 'mataKuliah']);

        $sks = $sksDiakui ?? (int) $konversi->sks_diakui;

        /*
         * A course cannot be worth more here than it is here.
         *
         * Without this, recognition becomes a way to inflate a credit total: a
         * six-credit course elsewhere granted against a three-credit course
         * here would add three credits nobody's curriculum accounts for.
         */
        if ($sks > (int) $konversi->mataKuliah->sks) {
            throw new AturanAkademikException(sprintf(
                'SKS yang diakui (%d) melebihi bobot mata kuliah %s di kurikulum ini (%d SKS).',
                $sks,
                $konversi->mataKuliah->kode,
                $konversi->mataKuliah->sks,
            ));
        }

        if ($sks < 1) {
            throw new AturanAkademikException('SKS yang diakui harus lebih dari nol.');
        }

        $this->pastikanBelumDitempuh($konversi->mahasiswa, $konversi->mataKuliah);
        $this->pastikanDalamBatas($konversi->mahasiswa, $sks);

        $bobot = $huruf !== null ? $this->bobotUntuk($huruf) : null;

        if ($huruf !== null && $bobot === null) {
            throw new AturanAkademikException(
                "Huruf nilai \"{$huruf}\" tidak ada pada skala penilaian institusi ini.",
            );
        }

        try {
            DB::transaction(function () use ($konversi, $staff, $sks, $huruf, $bobot, $catatan): void {
                $konversi->update([
                    'status' => StatusKonversi::Disetujui,
                    'sks_diakui' => $sks,
                    'nilai_huruf' => $huruf,
                    'bobot' => $bobot,
                    'catatan' => $catatan,
                    'diputus_by_staff_id' => $staff->id,
                    'diputus_at' => now(),

                    // Claims the course. The unique index is what actually
                    // prevents a second grant for it.
                    'kunci_aktif' => KonversiKredit::kunci(
                        (int) $konversi->mahasiswa_id,
                        (int) $konversi->mata_kuliah_id,
                    ),
                ]);

                $konversi->recordActivity('recognised', sprintf(
                    '%s SKS diakui untuk %s dari %s, diputus %s.',
                    $sks,
                    $konversi->mataKuliah->kode,
                    $konversi->asal_institusi ?? $konversi->jenis->label(),
                    $staff->nama,
                ));
            });
        } catch (UniqueConstraintViolationException) {
            throw new AturanAkademikException(
                'Mata kuliah ini sudah memiliki konversi yang disetujui untuk mahasiswa tersebut.',
            );
        }

        return $konversi->refresh();
    }

    public function tolak(KonversiKredit $konversi, Staff $staff, string $alasan): KonversiKredit
    {
        if ($konversi->status !== StatusKonversi::Diajukan) {
            throw new AturanAkademikException('Hanya usulan yang masih menunggu yang dapat ditolak.');
        }

        if (blank($alasan)) {
            throw new AturanAkademikException(
                'Penolakan konversi wajib disertai alasan yang dapat dibaca mahasiswa.',
            );
        }

        $konversi->update([
            'status' => StatusKonversi::Ditolak,
            'catatan' => $alasan,
            'diputus_by_staff_id' => $staff->id,
            'diputus_at' => now(),
        ]);

        return $konversi->refresh();
    }

    /**
     * Withdraws a granted recognition.
     *
     * Refused once the student has graduated. The credits are part of a total
     * that has been printed on a transcript and quoted on a diploma; removing
     * them afterwards would make an issued document disagree with the record it
     * came from, and there is no version of that which ends well.
     */
    public function cabut(KonversiKredit $konversi, Staff $staff, string $alasan): KonversiKredit
    {
        if ($konversi->status !== StatusKonversi::Disetujui) {
            throw new AturanAkademikException('Hanya konversi yang sudah diakui yang dapat dicabut.');
        }

        if (blank($alasan)) {
            throw new AturanAkademikException('Pencabutan konversi wajib disertai alasan.');
        }

        if ($konversi->mahasiswa->status === StudentStatus::Lulus) {
            throw new AturanAkademikException(
                'Mahasiswa ini sudah lulus. Kredit yang diakui sudah masuk ke total yang tercetak '
                    .'pada transkrip dan dikutip pada ijazah — pencabutannya harus melewati '
                    .'pembatalan yudisium lebih dulu.',
            );
        }

        DB::transaction(function () use ($konversi, $staff, $alasan): void {
            $konversi->update([
                'status' => StatusKonversi::Ditolak,
                'catatan' => $alasan,
                'kunci_aktif' => null,
                'diputus_by_staff_id' => $staff->id,
                'diputus_at' => now(),
            ]);

            $konversi->recordActivity('revoked', 'Konversi dicabut. Alasan: '.$alasan);
        });

        return $konversi->refresh();
    }

    /**
     * Credits still available for recognition.
     *
     * The ceiling exists so that nobody can be recognised into a degree. It is
     * the point past which a qualification stops describing study done here.
     */
    public function sisaKuota(Mahasiswa $mahasiswa): int
    {
        return max(0, $this->batas($mahasiswa) - $this->sudahDiakui($mahasiswa));
    }

    public function batas(Mahasiswa $mahasiswa): int
    {
        $syarat = (int) ($mahasiswa->prodi->sks_lulus ?: config('academic.graduation.min_credits'));
        $persen = (int) config('academic.konversi.maks_persen');

        return (int) floor($syarat * $persen / 100);
    }

    public function sudahDiakui(Mahasiswa $mahasiswa): int
    {
        return (int) KonversiKredit::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->diakui()
            ->sum('sks_diakui');
    }

    /**
     * A course actually studied here is not a candidate for recognition.
     *
     * The two together would credit it twice, and nothing downstream would
     * notice: the credit total simply comes out higher than the courses behind
     * it.
     */
    private function pastikanBelumDitempuh(Mahasiswa $mahasiswa, MataKuliah $mataKuliah): void
    {
        $ditempuh = Nilai::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->final()
            ->whereHas('kelasKuliah', fn ($q) => $q->where('mata_kuliah_id', $mataKuliah->id))
            ->exists();

        if ($ditempuh) {
            throw new AturanAkademikException(sprintf(
                'Mata kuliah %s sudah ditempuh dan dinilai di kampus ini, sehingga tidak dapat '
                    .'dikonversi dari tempat lain.',
                $mataKuliah->kode,
            ));
        }
    }

    private function pastikanBelumDikonversi(Mahasiswa $mahasiswa, MataKuliah $mataKuliah): void
    {
        $ada = KonversiKredit::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->whereIn('status', [StatusKonversi::Diajukan->value, StatusKonversi::Disetujui->value])
            ->exists();

        if ($ada) {
            throw new AturanAkademikException(sprintf(
                'Sudah ada usulan atau konversi berjalan untuk mata kuliah %s.',
                $mataKuliah->kode,
            ));
        }
    }

    private function pastikanDalamBatas(Mahasiswa $mahasiswa, int $tambahan): void
    {
        $batas = $this->batas($mahasiswa);
        $sudah = $this->sudahDiakui($mahasiswa);

        if ($sudah + $tambahan > $batas) {
            throw new AturanAkademikException(sprintf(
                'Melebihi batas pengakuan kredit. Batas %d SKS (%d%% dari syarat kelulusan), '
                    .'sudah diakui %d SKS, sisa %d SKS.',
                $batas,
                (int) config('academic.konversi.maks_persen'),
                $sudah,
                max(0, $batas - $sudah),
            ));
        }
    }

    /** Reads the institution's own letter scale rather than a private table. */
    private function bobotUntuk(string $huruf): ?float
    {
        foreach ((array) config('academic.grading.scale') as $baris) {
            if (strcasecmp((string) $baris['letter'], $huruf) === 0) {
                return (float) $baris['weight'];
            }
        }

        return null;
    }
}
