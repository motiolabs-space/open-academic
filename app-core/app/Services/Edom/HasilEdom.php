<?php

declare(strict_types=1);

namespace App\Services\Edom;

use App\Enums\KategoriEdom;
use App\Enums\TipeJawabanEdom;
use App\Models\Akademik\KelasKuliah;
use App\Models\Edom\EdomJawaban;
use App\Models\Edom\EdomPartisipasi;
use App\Models\Edom\EdomPeriode;
use App\Models\Sdm\Dosen;
use Illuminate\Support\Collection;

/**
 * Reading the results, without reading anybody's mind.
 *
 * Averaging is the easy half. The hard half is the threshold: below a certain
 * number of responses, a result is not anonymous however it is computed. Three
 * responses on a class of four tells a lecturer almost exactly who thought what,
 * and a single free-text comment identifies its author by its content whatever
 * the database does or does not store.
 *
 * So below the threshold this returns nothing at all — not a suppressed row with
 * the count showing, not "insufficient data (n=3)". The count itself is a clue
 * when the roster is four people.
 */
class HasilEdom
{
    /**
     * One lecturer's results for one class.
     *
     * @return array{cukup: bool, responden: int, ambang: int, rerata: float|null,
     *               kategori: array<string, float>, komentar: array<int, string>}
     */
    public function kelas(EdomPeriode $periode, int $kelasId, Dosen $dosen, bool $bolehKomentar): array
    {
        return $this->beberapaKelas($periode, $dosen, [$kelasId], $bolehKomentar)[$kelasId];
    }

    /**
     * The same thing for a whole teaching load, in a fixed number of queries.
     *
     * The lecturer's own results screen lists every class they taught, and doing
     * that one class at a time is the shape of N+1 the query budgets exist to
     * catch: two queries per row, invisible to preventLazyLoading because
     * nothing is being lazily loaded.
     *
     * Every requested class appears in the result, including ones nobody
     * answered — a missing key would read as an error rather than as silence.
     *
     * @param array<int, int> $kelasIds
     * @return array<int, array{cukup: bool, responden: int, ambang: int, rerata: float|null,
     *                          kategori: array<string, float>, komentar: array<int, string>}>
     */
    public function beberapaKelas(EdomPeriode $periode, Dosen $dosen, array $kelasIds, bool $bolehKomentar): array
    {
        $ambang = (int) $periode->min_responden;

        $kosong = [
            'cukup' => false,
            'responden' => 0,
            'ambang' => $ambang,
            'rerata' => null,
            'kategori' => [],
            'komentar' => [],
        ];

        if ($kelasIds === []) {
            return [];
        }

        $responden = EdomPartisipasi::query()
            ->selectRaw('kelas_kuliah_id, COUNT(*) as jumlah')
            ->where('edom_periode_id', $periode->id)
            ->where('dosen_id', $dosen->id)
            ->whereIn('kelas_kuliah_id', $kelasIds)
            ->groupBy('kelas_kuliah_id')
            ->pluck('jumlah', 'kelas_kuliah_id');

        // Only classes that already cleared the threshold are read at all. A
        // class below it contributes nothing to this screen, so fetching its
        // answers would be work done solely to throw away.
        $memenuhi = collect($kelasIds)
            ->filter(fn (int $id): bool => (int) $responden->get($id, 0) >= $ambang)
            ->values();

        $jawaban = $memenuhi->isEmpty()
            ? collect()
            : EdomJawaban::query()
                ->with('pertanyaan')
                ->where('edom_periode_id', $periode->id)
                ->where('dosen_id', $dosen->id)
                ->whereIn('kelas_kuliah_id', $memenuhi->all())
                ->get()
                ->groupBy('kelas_kuliah_id');

        $hasil = [];

        foreach ($kelasIds as $id) {
            if (!$memenuhi->contains($id)) {
                $hasil[$id] = $kosong;

                continue;
            }

            $baris = $jawaban->get($id, collect());

            $skala = $baris->filter(
                fn (EdomJawaban $j): bool => $j->pertanyaan->tipe === TipeJawabanEdom::Skala,
            );

            $hasil[$id] = [
                'cukup' => true,
                'responden' => (int) $responden->get($id, 0),
                'ambang' => $ambang,
                'rerata' => $skala->isEmpty() ? null : round((float) $skala->avg('nilai'), 2),
                'kategori' => $this->perKategori($skala),

                /*
                 * Comments obey a stricter rule than the numbers beside them.
                 *
                 * An average is a statement about a group; a sentence is a
                 * statement by a person, and in a small cohort it points at them
                 * through its content alone. Who may read them is a campus
                 * decision — see config('edom.komentar').
                 */
                'komentar' => $bolehKomentar
                    ? $baris->filter(fn (EdomJawaban $j): bool => filled($j->teks))
                        ->pluck('teks')

                        // Shuffled so the reading order carries no information:
                        // insertion order is submission order, which is a partial
                        // ordering of who answered when.
                        ->shuffle()
                        ->values()
                        ->all()
                    : [],
            ];
        }

        return $hasil;
    }

    /**
     * Per-lecturer summary across every class they taught in the period.
     *
     * This is what the results screen lists and what Campus Bridge publishes.
     * Classes below the threshold contribute nothing — including their response
     * count, which is why the totals here can be lower than the participation
     * table would suggest.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ringkasanDosen(EdomPeriode $periode, ?int $dosenId = null): Collection
    {
        $ambang = (int) $periode->min_responden;

        $responden = EdomPartisipasi::query()
            ->selectRaw('dosen_id, kelas_kuliah_id, COUNT(*) as jumlah')
            ->where('edom_periode_id', $periode->id)
            ->when($dosenId !== null, fn ($q) => $q->where('dosen_id', $dosenId))
            ->groupBy('dosen_id', 'kelas_kuliah_id')
            ->get();

        $memenuhi = $responden->filter(fn ($b): bool => (int) $b->jumlah >= $ambang);

        if ($memenuhi->isEmpty()) {
            return collect();
        }

        // Averaged over answers, not over class averages: a class of forty and a
        // class of six should not weigh the same in a lecturer's figure.
        $skor = EdomJawaban::query()
            ->selectRaw('edom_jawaban.dosen_id, AVG(edom_jawaban.nilai) as rerata, COUNT(*) as jumlah')
            ->join('edom_pertanyaan', 'edom_pertanyaan.id', '=', 'edom_jawaban.edom_pertanyaan_id')
            ->where('edom_jawaban.edom_periode_id', $periode->id)
            ->where('edom_pertanyaan.tipe', TipeJawabanEdom::Skala->value)
            ->whereNotNull('edom_jawaban.nilai')
            ->whereIn('edom_jawaban.kelas_kuliah_id', $memenuhi->pluck('kelas_kuliah_id')->unique())
            ->when($dosenId !== null, fn ($q) => $q->where('edom_jawaban.dosen_id', $dosenId))
            ->groupBy('edom_jawaban.dosen_id')
            ->get()
            ->keyBy('dosen_id');

        $dosen = Dosen::whereIn('id', $memenuhi->pluck('dosen_id')->unique())->get()->keyBy('id');

        return $memenuhi
            ->groupBy('dosen_id')
            ->map(fn (Collection $baris, int|string $id): array => [
                'dosen' => $dosen->get((int) $id),
                'kelas_dinilai' => $baris->count(),
                'responden' => (int) $baris->sum('jumlah'),
                'rerata' => $skor->has((int) $id)
                    ? round((float) $skor[(int) $id]->rerata, 2)
                    : null,
            ])
            ->filter(fn (array $b): bool => $b['dosen'] !== null)
            ->values();
    }

    /**
     * Response counts per (class, lecturer), for the people chasing them.
     *
     * Deliberately *not* subject to the threshold. Who has filled the form in is
     * already visible to an administrator — it is the whole basis of the gate —
     * and hiding the count would only stop them noticing a class where nobody
     * has answered. What the threshold protects is the link between a person and
     * an opinion, and no count reveals that.
     *
     * @return Collection<int, array{kelas: KelasKuliah, dosen: Dosen, responden: int, cukup: bool}>
     */
    public function partisipasiKelas(EdomPeriode $periode): Collection
    {
        $ambang = (int) $periode->min_responden;

        $baris = EdomPartisipasi::query()
            ->selectRaw('kelas_kuliah_id, dosen_id, COUNT(*) as jumlah')
            ->where('edom_periode_id', $periode->id)
            ->groupBy('kelas_kuliah_id', 'dosen_id')
            ->get();

        if ($baris->isEmpty()) {
            return collect();
        }

        $kelas = KelasKuliah::with('mataKuliah')
            ->whereIn('id', $baris->pluck('kelas_kuliah_id')->unique())
            ->get()->keyBy('id');

        $dosen = Dosen::whereIn('id', $baris->pluck('dosen_id')->unique())->get()->keyBy('id');

        return $baris
            ->map(fn ($b): ?array => $kelas->has($b->kelas_kuliah_id) && $dosen->has($b->dosen_id)
                ? [
                    'kelas' => $kelas[$b->kelas_kuliah_id],
                    'dosen' => $dosen[$b->dosen_id],
                    'responden' => (int) $b->jumlah,
                    'cukup' => (int) $b->jumlah >= $ambang,
                ]
                : null)
            ->filter()
            ->sortBy(fn (array $b): string => $b['dosen']->nama.$b['kelas']->nama)
            ->values();
    }

    /**
     * @param Collection<int, EdomJawaban> $skala
     * @return array<string, float>
     */
    private function perKategori(Collection $skala): array
    {
        return $skala
            ->groupBy(fn (EdomJawaban $j): string => $j->pertanyaan->kategori->value)
            ->map(fn (Collection $baris): float => round((float) $baris->avg('nilai'), 2))
            ->mapWithKeys(fn (float $nilai, string $kategori): array => [
                KategoriEdom::from($kategori)->label() => $nilai,
            ])
            ->all();
    }
}
