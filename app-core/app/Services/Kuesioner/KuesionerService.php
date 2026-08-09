<?php

declare(strict_types=1);

namespace App\Services\Kuesioner;

use App\Enums\TipePertanyaan;
use App\Exceptions\AturanAkademikException;
use App\Models\Kuesioner\Kuesioner;
use App\Models\Kuesioner\KuesionerJawaban;
use App\Models\Kuesioner\KuesionerJawabanAnonim;
use App\Models\Kuesioner\KuesionerPartisipasi;
use App\Models\Kuesioner\KuesionerPertanyaan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Filling in and reading back a questionnaire.
 *
 * The one thing this class must never do is let an anonymous form's answers be
 * traced. It cannot, structurally: the anonymous answer table has no respondent
 * column, and the choice of table is made from `kuesioner.anonim` in a single
 * place — here.
 */
class KuesionerService
{
    /**
     * Records one respondent's answers.
     *
     * Participation and answers are written in one transaction, but into
     * different tables, and for an anonymous form the two are never joinable.
     * A crash between them would leave a form that can be answered twice, which
     * is recoverable; the alternative ordering would leave answers attributable
     * for as long as it took somebody to notice.
     *
     * @param array<int, array{nilai?: int|null, teks?: string|null}> $jawaban keyed by question id
     */
    public function isi(Kuesioner $kuesioner, object $responden, array $jawaban): void
    {
        if (!$kuesioner->terbuka()) {
            throw new AturanAkademikException('Kuesioner ini sedang tidak dibuka.');
        }

        if ($this->sudahMengisi($kuesioner, $responden)) {
            throw new AturanAkademikException('Anda sudah mengisi kuesioner ini.');
        }

        $pertanyaan = $kuesioner->pertanyaan()->get();

        $this->pastikanLengkap($pertanyaan, $jawaban);

        DB::transaction(function () use ($kuesioner, $responden, $pertanyaan, $jawaban): void {
            KuesionerPartisipasi::create([
                'kuesioner_id' => $kuesioner->id,
                'responden_type' => $responden::class,
                'responden_id' => $responden->getKey(),
                'diisi_at' => now(),
            ]);

            foreach ($pertanyaan as $satu) {
                $isi = $jawaban[$satu->id] ?? null;

                if ($isi === null) {
                    continue;
                }

                $baris = [
                    'kuesioner_id' => $kuesioner->id,
                    'kuesioner_pertanyaan_id' => $satu->id,
                    'nilai' => $satu->tipe->berangka() ? (int) ($isi['nilai'] ?? 0) : null,
                    'teks' => $satu->tipe->berangka() ? null : ($isi['teks'] ?? null),
                ];

                /*
                 * The single place the two shapes diverge.
                 *
                 * Everything upstream is identical, which is the point: there is
                 * no second code path that could forget.
                 */
                if ($kuesioner->anonim) {
                    KuesionerJawabanAnonim::create($baris);
                } else {
                    KuesionerJawaban::create([
                        ...$baris,
                        'responden_type' => $responden::class,
                        'responden_id' => $responden->getKey(),
                    ]);
                }
            }
        });
    }

    public function sudahMengisi(Kuesioner $kuesioner, object $responden): bool
    {
        return KuesionerPartisipasi::query()
            ->where('kuesioner_id', $kuesioner->id)
            ->where('responden_type', $responden::class)
            ->where('responden_id', $responden->getKey())
            ->exists();
    }

    /**
     * @param Collection<int, KuesionerPertanyaan> $pertanyaan
     * @param array<int, mixed> $jawaban
     */
    private function pastikanLengkap(Collection $pertanyaan, array $jawaban): void
    {
        $kurang = $pertanyaan
            ->filter(fn (KuesionerPertanyaan $p): bool => $p->wajib)
            ->filter(function (KuesionerPertanyaan $p) use ($jawaban): bool {
                $isi = $jawaban[$p->id] ?? null;

                return $p->tipe->berangka()
                    ? blank($isi['nilai'] ?? null)
                    : blank($isi['teks'] ?? null);
            });

        if ($kurang->isNotEmpty()) {
            throw new AturanAkademikException(sprintf(
                '%d pertanyaan wajib belum dijawab.',
                $kurang->count(),
            ));
        }
    }

    /**
     * Aggregated results, in the same shape whichever table they came from.
     *
     * Free text is returned as a list and never summarised — a mean of prose is
     * not a thing, and a count of prose tells the reader nothing they can act
     * on.
     *
     * @return array<int, array<string, mixed>>
     */
    public function hasil(Kuesioner $kuesioner): array
    {
        $tabel = $kuesioner->anonim ? 'kuesioner_jawaban_anonim' : 'kuesioner_jawaban';

        $jawaban = DB::table($tabel)
            ->where('kuesioner_id', $kuesioner->id)
            ->get(['kuesioner_pertanyaan_id', 'nilai', 'teks'])
            ->groupBy('kuesioner_pertanyaan_id');

        return $kuesioner->pertanyaan()->get()
            ->map(function (KuesionerPertanyaan $p) use ($jawaban): array {
                $baris = $jawaban[$p->id] ?? collect();

                return [
                    'pertanyaan' => $p,
                    'jumlah' => $baris->count(),

                    'rerata' => $p->tipe === TipePertanyaan::Skala && $baris->isNotEmpty()
                        ? round($baris->avg('nilai'), 2)
                        : null,

                    'sebaran' => $p->tipe === TipePertanyaan::Pilihan
                        ? $baris->groupBy('teks')->map->count()->all()
                        : [],

                    'teks' => $p->tipe === TipePertanyaan::Teks
                        ? $baris->pluck('teks')->filter()->values()->all()
                        : [],
                ];
            })
            ->all();
    }

    /** Response rate needs only the participation table. */
    public function jumlahResponden(Kuesioner $kuesioner): int
    {
        return $kuesioner->partisipasi()->count();
    }
}
