<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Akademik\PeriodePerkuliahan;
use App\Models\Akademik\TahunAkademik;
use App\Services\Feeder\SkalaPeriodeProdiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Periode Perkuliahan — one programme's teaching period for one term.
 *
 * PDDIKTI keys this record on the pair (programme, term) and hands no
 * identifier of its own back, so the pair is what gets stored — joined, in
 * the row's `feeder_id` — and split again when an update needs the key. The
 * same arrangement as MatkulKurikulum.
 */
class PeriodePerkuliahanMapper extends FeederMapper
{
    public const PEMISAH = ':';

    public function __construct(private readonly SkalaPeriodeProdiService $turunan) {}

    public function act(): string
    {
        return 'InsertPeriodePerkuliahan';
    }

    /** @return Builder<PeriodePerkuliahan> */
    protected function query(string $termCode): Builder
    {
        return PeriodePerkuliahan::query()
            ->with(['prodi', 'tahunAkademik'])
            ->whereHas('tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode))
            ->orderBy('prodi_id');
    }

    /** Programmes without a period for this term get one derived first. */
    public function rows(string $termCode): Collection
    {
        $term = TahunAkademik::byKode($termCode);

        if ($term !== null) {
            $this->turunan->turunkanPeriode($term);
        }

        return parent::rows($termCode);
    }

    /** @param PeriodePerkuliahan $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_prodi' => $model->prodi->kode_pddikti,
            'id_semester' => $model->tahunAkademik->kode,
            'tanggal_awal_perkuliahan' => $this->tanggal($model->tanggal_awal),
            'tanggal_akhir_perkuliahan' => $this->tanggal($model->tanggal_akhir),
            'jumlah_minggu_pertemuan' => (int) $model->jumlah_minggu_pertemuan,
            'jumlah_target_mahasiswa_baru' => $model->target_mahasiswa_baru,
            'jumlah_pendaftar_ikut_seleksi' => $model->pendaftar_ikut_seleksi,
            'jumlah_pendaftar_lulus_seleksi' => $model->pendaftar_lulus_seleksi,
            'jumlah_daftar_ulang' => $model->daftar_ulang,
            'jumlah_mengundurkan_diri' => $model->mengundurkan_diri,
            'metode_kuliah_tatap_muka' => $model->metode_tatap_muka ? 1 : 0,
            'metode_kuliah_daring' => $model->metode_daring ? 1 : 0,
            'metode_kuliah_campuran' => $model->metode_campuran ? 1 : 0,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function feederIdBawaan(Model $model): ?string
    {
        return $model->prodi->kode_pddikti.self::PEMISAH.$model->tahunAkademik->kode;
    }

    /** Whatever the reply carries, the stored identifier is the pair. */
    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill([
            'feeder_id' => $this->feederIdBawaan($model),
            'feeder_synced_at' => now(),
        ])->saveQuietly();
    }

    public function label(Model $model): string
    {
        /** @var PeriodePerkuliahan $model */
        return $model->prodi->kode.' · '.$model->tahunAkademik->kode;
    }
}
