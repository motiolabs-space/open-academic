<?php

declare(strict_types=1);

namespace App\Services\Edom;

use App\Enums\TipeJawabanEdom;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Edom\EdomJawaban;
use App\Models\Edom\EdomPartisipasi;
use App\Models\Edom\EdomPeriode;
use App\Models\Edom\EdomPertanyaan;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Submitting an evaluation, and knowing who still owes one.
 *
 * The write is the interesting part. Two rows go into two tables that share no
 * key, inside one transaction:
 *
 *   - a participation record, which knows the student and nothing about their
 *     opinions;
 *   - the answers, which know the class and the lecturer and nothing about the
 *     student.
 *
 * Anonymity here is not a policy anybody has to remember. There is no column
 * that would let the two be joined, so no query can produce the pairing —
 * including a query written years from now by somebody who never read this.
 */
class EdomService
{
    /**
     * Records one student's evaluation of one lecturer on one class.
     *
     * @param array<int, array{pertanyaan_id: int, nilai?: int|null, teks?: string|null}> $jawaban
     */
    public function isi(
        EdomPeriode $periode,
        Mahasiswa $mahasiswa,
        KelasKuliah $kelas,
        Dosen $dosen,
        array $jawaban,
    ): void {
        if (!$periode->terbuka()) {
            throw new AturanAkademikException(
                'Pengisian EDOM untuk periode ini sedang tidak dibuka.',
            );
        }

        $this->pastikanPeserta($mahasiswa, $kelas);
        $this->pastikanPengampu($dosen, $kelas);

        $pertanyaan = $periode->pertanyaan()->get()->keyBy('id');

        $bersih = $this->validasiJawaban($pertanyaan, $jawaban);

        try {
            DB::transaction(function () use ($periode, $mahasiswa, $kelas, $dosen, $bersih): void {
                /*
                 * Participation is claimed first.
                 *
                 * The unique index refuses a second submission, and claiming it
                 * before writing answers means a race loses the whole
                 * transaction rather than adding a second set of answers to an
                 * average that nobody can trace back and unpick.
                 */
                EdomPartisipasi::create([
                    'edom_periode_id' => $periode->id,
                    'mahasiswa_id' => $mahasiswa->id,
                    'kelas_kuliah_id' => $kelas->id,
                    'dosen_id' => $dosen->id,
                    'diisi_at' => now(),
                ]);

                foreach ($bersih as $baris) {
                    EdomJawaban::create([
                        'edom_periode_id' => $periode->id,
                        'kelas_kuliah_id' => $kelas->id,
                        'dosen_id' => $dosen->id,
                        'edom_pertanyaan_id' => $baris['pertanyaan_id'],
                        'nilai' => $baris['nilai'],
                        'teks' => $baris['teks'],
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw new AturanAkademikException(
                'Anda sudah mengisi EDOM untuk dosen dan kelas ini.',
            );
        }
    }

    /**
     * Everything this student still has to evaluate this period.
     *
     * One entry per (class, lecturer) they were taught by and have not yet
     * submitted for.
     *
     * @return Collection<int, array{kelas: KelasKuliah, dosen: Dosen}>
     */
    public function tertunda(EdomPeriode $periode, Mahasiswa $mahasiswa): Collection
    {
        $sudah = EdomPartisipasi::query()
            ->where('edom_periode_id', $periode->id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->get()
            ->map(fn (EdomPartisipasi $p): string => $p->kelas_kuliah_id.':'.$p->dosen_id)
            ->flip();

        return $this->kelasDiambil($periode, $mahasiswa)
            ->flatMap(fn (KelasKuliah $k): array => $k->dosen
                ->map(fn (Dosen $d): array => ['kelas' => $k, 'dosen' => $d])
                ->all())
            ->reject(fn (array $baris): bool => $sudah->has(
                $baris['kelas']->id.':'.$baris['dosen']->id,
            ))
            ->values();
    }

    /**
     * Why this student is blocked, or null when they are not.
     *
     * Consulted by the KRS gate and the KHS screen depending on
     * config('edom.gerbang'). Returns a sentence a student can act on rather
     * than a boolean, because "you cannot proceed" without a reason sends them
     * to a counter.
     */
    public function penghalang(Mahasiswa $mahasiswa, string $untuk): ?string
    {
        if (config('edom.gerbang') !== $untuk) {
            return null;
        }

        $periode = EdomPeriode::berjalan();

        if ($periode === null || !$periode->terbuka()) {
            return null;
        }

        $sisa = $this->tertunda($periode, $mahasiswa)->count();

        if ($sisa === 0) {
            return null;
        }

        return sprintf(
            'Masih ada %d evaluasi dosen (EDOM) yang belum Anda isi untuk %s. '
                .'Lengkapi lebih dulu melalui menu EDOM.',
            $sisa,
            $periode->tahunAkademik->nama,
        );
    }

    /** Classes this student was actually taught in the period's term. */
    private function kelasDiambil(EdomPeriode $periode, Mahasiswa $mahasiswa): Collection
    {
        return KelasKuliah::query()
            ->with(['dosen', 'mataKuliah'])
            ->where('tahun_akademik_id', $periode->tahun_akademik_id)
            ->whereHas('krsDetail.krs', fn ($q) => $q
                ->where('mahasiswa_id', $mahasiswa->id)
                ->where('status', 'disetujui'))
            ->get();
    }

    /**
     * Only students who sat the class may evaluate it.
     *
     * Without this, an evaluation is an open comment box on a named lecturer
     * that anybody with an account can post to.
     */
    private function pastikanPeserta(Mahasiswa $mahasiswa, KelasKuliah $kelas): void
    {
        $terdaftar = $kelas->krsDetail()
            ->whereHas('krs', fn ($q) => $q
                ->where('mahasiswa_id', $mahasiswa->id)
                ->where('status', 'disetujui'))
            ->exists();

        if (!$terdaftar) {
            throw new AturanAkademikException(
                'Anda tidak terdaftar pada kelas ini, sehingga tidak dapat menilainya.',
            );
        }
    }

    private function pastikanPengampu(Dosen $dosen, KelasKuliah $kelas): void
    {
        if (!$kelas->dosen->contains('id', $dosen->id)) {
            throw new AturanAkademikException(
                'Dosen tersebut tidak mengampu kelas ini.',
            );
        }
    }

    /**
     * Checks the submission covers every question, in the right shape.
     *
     * A partial submission would still release the gate while contributing a
     * skewed average — and because the answers cannot be traced back, it could
     * never be identified and removed afterwards.
     *
     * @param Collection<int, EdomPertanyaan> $pertanyaan
     * @param array<int, array<string, mixed>> $jawaban
     * @return array<int, array{pertanyaan_id: int, nilai: int|null, teks: string|null}>
     */
    private function validasiJawaban(Collection $pertanyaan, array $jawaban): array
    {
        $terisi = collect($jawaban)->keyBy('pertanyaan_id');
        $bersih = [];

        foreach ($pertanyaan as $satu) {
            $baris = $terisi->get($satu->id);

            if ($satu->tipe === TipeJawabanEdom::Skala) {
                $nilai = (int) ($baris['nilai'] ?? 0);

                if ($nilai < 1 || $nilai > 5) {
                    throw new AturanAkademikException(
                        'Setiap pernyataan berskala wajib dijawab dengan nilai 1 sampai 5.',
                    );
                }

                $bersih[] = ['pertanyaan_id' => $satu->id, 'nilai' => $nilai, 'teks' => null];

                continue;
            }

            // Free text is optional — forcing a comment produces "-" and
            // "bagus", which is noise wearing the shape of data.
            $teks = trim((string) ($baris['teks'] ?? ''));

            $bersih[] = [
                'pertanyaan_id' => $satu->id,
                'nilai' => null,
                'teks' => $teks === '' ? null : $teks,
            ];
        }

        return $bersih;
    }
}
