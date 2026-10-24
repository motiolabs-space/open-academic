<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\SkalaNilaiProdi;
use App\Services\Feeder\SkalaPeriodeProdiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Skala Nilai Prodi — one letter of one programme's grading scale.
 *
 * Reference data: due every run regardless of term, with the hash check
 * turning the unchanged rows into skips. A programme that has no rows yet
 * gets them derived from the campus scale on the way in, so the first sync
 * of a fresh installation does not report "nothing to send" for an entity
 * PDDIKTI requires before it will take a single grade.
 */
class SkalaNilaiProdiMapper extends FeederMapper
{
    public function __construct(private readonly SkalaPeriodeProdiService $turunan) {}

    public function act(): string
    {
        return 'InsertSkalaNilaiProdi';
    }

    /** @return Builder<SkalaNilaiProdi> */
    protected function query(string $termCode): Builder
    {
        return SkalaNilaiProdi::query()
            ->with('prodi')
            ->orderBy('prodi_id')
            ->orderByDesc('bobot_minimum');
    }

    /**
     * Derivation happens here and not in query(): the count the monitor shows
     * must stay a plain SELECT, and a validation or sync run is the moment
     * the rows are actually needed.
     */
    public function rows(string $termCode): Collection
    {
        $this->turunan->turunkanSkala();

        return parent::rows($termCode);
    }

    /** @param SkalaNilaiProdi $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_prodi' => $model->prodi->kode_pddikti,
            'nilai_huruf' => $model->nilai_huruf,
            'nilai_indeks' => number_format((float) $model->nilai_indeks, 2, '.', ''),
            'bobot_minimum' => number_format((float) $model->bobot_minimum, 2, '.', ''),
            'bobot_maksimum' => number_format((float) $model->bobot_maksimum, 2, '.', ''),
            'tanggal_mulai_efektif' => $this->tanggal($model->berlaku_mulai),
            'tanggal_akhir_efektif' => $this->tanggal($model->berlaku_sampai),
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var SkalaNilaiProdi $model */
        return $model->prodi->kode.' · '.$model->nilai_huruf;
    }
}
