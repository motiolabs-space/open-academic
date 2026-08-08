<?php

declare(strict_types=1);

namespace App\Services\Edom;

use App\Enums\KategoriEdom;
use App\Enums\TipeJawabanEdom;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Edom\EdomJawaban;
use App\Models\Edom\EdomPeriode;
use App\Models\Edom\EdomPertanyaan;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Opening a period, and writing the questions it will ask.
 *
 * Small module, one non-obvious rule: once a single answer exists, the questions
 * are frozen. Everything here exists to enforce that.
 */
class KelolaEdom
{
    public function buatPeriode(
        TahunAkademik $tahunAkademik,
        string $nama,
        string $mulai,
        string $selesai,
        int $minResponden,
    ): EdomPeriode {
        if ($minResponden < 1) {
            throw new AturanAkademikException('Ambang responden minimal 1.');
        }

        try {
            return EdomPeriode::create([
                'tahun_akademik_id' => $tahunAkademik->id,
                'nama' => $nama,
                'mulai' => $mulai,
                'selesai' => $selesai,
                'min_responden' => $minResponden,
                'is_active' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new AturanAkademikException(
                'Semester ini sudah memiliki periode EDOM.',
            );
        }
    }

    /**
     * Opens the window.
     *
     * Refuses an empty questionnaire, because an open period with no questions
     * shows students a blank form they cannot submit — and, where the gate is
     * on, blocks their KRS behind something they cannot clear.
     */
    public function aktifkan(EdomPeriode $periode): void
    {
        if ($periode->pertanyaan()->count() === 0) {
            throw new AturanAkademikException(
                'Periode belum memiliki pertanyaan, sehingga belum dapat dibuka.',
            );
        }

        $periode->update(['is_active' => true]);
    }

    public function nonaktifkan(EdomPeriode $periode): void
    {
        $periode->update(['is_active' => false]);
    }

    public function tambahPertanyaan(
        EdomPeriode $periode,
        KategoriEdom $kategori,
        string $teks,
        TipeJawabanEdom $tipe,
    ): EdomPertanyaan {
        $this->pastikanBelumDijawab($periode);

        return $periode->pertanyaan()->create([
            'kategori' => $kategori,
            'teks' => $teks,
            'tipe' => $tipe,
            'urutan' => (int) $periode->pertanyaan()->max('urutan') + 1,
        ]);
    }

    public function hapusPertanyaan(EdomPertanyaan $pertanyaan): void
    {
        $this->pastikanBelumDijawab($pertanyaan->periode);

        $pertanyaan->delete();
    }

    /**
     * Copies a previous period's questionnaire into a new one.
     *
     * The common case by a wide margin — most campuses ask the same thing every
     * term — and doing it by copy rather than by sharing rows is the point:
     * last term's answers keep pointing at last term's wording, so a comparison
     * across years compares like with like.
     */
    public function salinPertanyaan(EdomPeriode $dari, EdomPeriode $ke): int
    {
        $this->pastikanBelumDijawab($ke);

        if ($ke->pertanyaan()->exists()) {
            throw new AturanAkademikException(
                'Periode tujuan sudah memiliki pertanyaan.',
            );
        }

        $baris = $dari->pertanyaan()->get()->map(fn (EdomPertanyaan $p): array => [
            'edom_periode_id' => $ke->id,
            'kategori' => $p->kategori->value,
            'teks' => $p->teks,
            'tipe' => $p->tipe->value,
            'urutan' => $p->urutan,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($baris === []) {
            return 0;
        }

        EdomPertanyaan::insert($baris);

        return count($baris);
    }

    /**
     * A questionnaire stops being editable the moment anybody answers it.
     *
     * Changing the wording afterwards would silently rewrite what the stored
     * numbers mean, and deleting a question would drop answers that can never be
     * collected again — the students who gave them cannot be identified and
     * asked, by design.
     */
    private function pastikanBelumDijawab(EdomPeriode $periode): void
    {
        $ada = EdomJawaban::query()
            ->where('edom_periode_id', $periode->id)
            ->exists();

        if ($ada) {
            throw new AturanAkademikException(
                'Pertanyaan tidak dapat diubah karena sudah ada jawaban yang masuk. '
                    .'Buat periode baru bila instrumennya perlu direvisi.',
            );
        }
    }
}
