<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\KategoriEdom;
use App\Enums\TipeJawabanEdom;
use App\Models\Akademik\TahunAkademik;
use App\Models\Edom\EdomPeriode;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Edom\EdomService;
use App\Services\Edom\KelolaEdom;
use Illuminate\Database\Seeder;

/**
 * An evaluation period mid-flight, which is the only state worth demonstrating.
 *
 * Two things are arranged deliberately:
 *
 *   - Response counts vary, so some classes clear the threshold and some do not.
 *     A demo where every class shows a score hides the rule that matters most.
 *   - The demo student (mahasiswa1@demo.test) has *not* filled theirs in, so the
 *     student screen has work on it and the KRS gate can actually be seen firing.
 *
 * Written through EdomService rather than into the tables, because the write is
 * the part with a rule in it — two rows, two tables, one transaction — and demo
 * data that skips it would not behave like data a student produced.
 */
class EdomSeeder extends Seeder
{
    /** @var array<int, array{0: KategoriEdom, 1: string}> */
    private const PERNYATAAN = [
        [KategoriEdom::Materi, 'Dosen menguasai materi yang diajarkan.'],
        [KategoriEdom::Materi, 'Materi yang disampaikan sesuai dengan rencana pembelajaran.'],
        [KategoriEdom::Penyampaian, 'Dosen menyampaikan materi dengan jelas dan mudah diikuti.'],
        [KategoriEdom::Penyampaian, 'Dosen memberi contoh yang membantu pemahaman.'],
        [KategoriEdom::Disiplin, 'Dosen hadir dan memulai perkuliahan tepat waktu.'],
        [KategoriEdom::Penilaian, 'Penilaian dilakukan secara adil dan sesuai kriteria yang dijelaskan.'],
        [KategoriEdom::Sikap, 'Dosen terbuka terhadap pertanyaan dan pendapat mahasiswa.'],
    ];

    /** @var array<int, string> */
    private const KOMENTAR = [
        'Penjelasan sudah runut, tetapi tempo di pertemuan terakhir agak cepat.',
        'Contoh kasusnya membantu sekali, terutama yang diambil dari industri.',
        'Mohon tugas diumumkan lebih awal supaya bisa diatur dengan mata kuliah lain.',
        'Kelasnya menyenangkan dan pertanyaan selalu dijawab dengan sabar.',
        'Materi bagus, tetapi slide sering baru diunggah setelah pertemuan.',
    ];

    public function run(): void
    {
        $term = TahunAkademik::aktif();

        if ($term === null) {
            return;
        }

        $kelola = app(KelolaEdom::class);
        $edom = app(EdomService::class);

        $periode = $kelola->buatPeriode(
            $term,
            'EDOM '.$term->nama,

            // Opened three weeks ago and closing in three: mid-window is the only
            // moment where both "sudah mengisi" and "belum" exist at once.
            now()->subWeeks(3)->toDateString(),
            now()->addWeeks(3)->toDateString(),

            /*
             * Three, not the default five.
             *
             * The demo campus has fifty students spread over three cohorts, so a
             * class holds about five approved enrolments — at a threshold of five
             * nothing would ever clear it and every results screen would show the
             * same empty state. Lowering it here keeps the *rule* visible, which
             * is the opposite of hiding it: some classes still fall short.
             *
             * A real campus should leave it at five. The migration default and
             * the new-period form both still say five.
             */
            minResponden: 3,
        );

        foreach (self::PERNYATAAN as [$kategori, $teks]) {
            $kelola->tambahPertanyaan($periode, $kategori, $teks, TipeJawabanEdom::Skala);
        }

        $kelola->tambahPertanyaan(
            $periode,
            KategoriEdom::Penyampaian,
            'Saran Anda untuk perkuliahan ini (opsional).',
            TipeJawabanEdom::Teks,
        );

        // Opened before anything is filled in, because the service refuses a
        // submission to a closed window — the same refusal a student would get.
        $kelola->aktifkan($periode);

        $this->isiJawaban($edom, $periode);
    }

    private function isiJawaban(EdomService $edom, EdomPeriode $periode): void
    {
        $pertanyaan = $periode->pertanyaan()->get();

        $mahasiswa = Mahasiswa::query()
            ->where('email', '!=', 'mahasiswa1@demo.test')
            ->orderBy('id')
            ->get();

        foreach ($mahasiswa as $i => $satu) {
            // Four in five respond. The fifth is what makes some classes fall
            // short of the threshold, which is the state the screens have to
            // handle honestly.
            if ($i % 5 === 4) {
                continue;
            }

            foreach ($edom->tertunda($periode, $satu) as $j => $baris) {
                $edom->isi(
                    $periode,
                    $satu,
                    $baris['kelas'],
                    $baris['dosen'],
                    $pertanyaan->map(fn ($p, int $k): array => [
                        'pertanyaan_id' => $p->id,
                        'nilai' => $p->tipe === TipeJawabanEdom::Skala
                            ? $this->nilai($baris['dosen']->id, $i, $k)
                            : null,

                        // Roughly one submission in six leaves a sentence, which
                        // is about what a real period produces.
                        'teks' => $p->tipe === TipeJawabanEdom::Teks && ($i + $j) % 6 === 0
                            ? self::KOMENTAR[($i + $j) % count(self::KOMENTAR)]
                            : null,
                    ])->all(),
                );
            }
        }
    }

    /**
     * A rating between 3 and 5, leaning on the lecturer.
     *
     * Deterministic rather than random so a reseed produces the same demo, and
     * spread across lecturers so the summary table has an order to it instead of
     * seven identical 4.0s.
     */
    private function nilai(int $dosenId, int $mahasiswaIndex, int $pertanyaanIndex): int
    {
        $dasar = 3 + ($dosenId % 3);
        $goyang = ($mahasiswaIndex + $pertanyaanIndex) % 3 === 0 ? -1 : 0;

        return max(1, min(5, $dasar + $goyang));
    }
}
