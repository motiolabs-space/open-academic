<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Enums\TugasAkhirStatus;
use App\Models\TugasAkhir\TugasAkhir;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Aktivitas Mahasiswa, final-project flavour.
 *
 * PDDIKTI records a thesis as an activity of its own kind, with the student
 * as member, the supervisors as "bimbing" and the examiners as "uji". Locally
 * it lives in `tugas_akhir`, not in `aktivitas_mahasiswa`, so it has its own
 * mapper; the act is the same.
 *
 * A project is reported once it is approved: that is when supervisors exist,
 * and an activity without them is a title. Rejected and cancelled projects
 * never leave the building.
 */
class TugasAkhirMapper extends FeederMapper
{
    public const JENIS = 'tugas_akhir';

    public function act(): string
    {
        return 'InsertAktivitasMahasiswa';
    }

    /** @return Builder<TugasAkhir> */
    protected function query(string $termCode): Builder
    {
        return TugasAkhir::query()
            ->with(['mahasiswa.prodi', 'tahunAkademik'])
            ->whereIn('status', [
                TugasAkhirStatus::Disetujui->value,
                TugasAkhirStatus::Dibimbing->value,
                TugasAkhirStatus::Selesai->value,
            ])
            ->whereHas('tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode))
            ->orderBy('id');
    }

    /** @param TugasAkhir $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_jenis_aktivitas' => $this->kode('jenis_aktivitas', self::JENIS),
            'id_prodi' => $model->mahasiswa->prodi->kode_pddikti,
            'id_semester' => $model->tahunAkademik->kode,
            'judul' => $model->judul,
            'keterangan' => $model->bidang_kajian,
            'tanggal_sk_tugas' => $this->tanggal($model->tanggal_disetujui),
            'untuk_kampus_merdeka' => 0,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var TugasAkhir $model */
        return $model->mahasiswa->nim.' · '.$model->sebutan();
    }
}
