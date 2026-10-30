<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Enums\TugasAkhirStatus;
use App\Models\TugasAkhir\TugasAkhir;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** The student as the member of their own final-project activity. */
class AnggotaTugasAkhirMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertAnggotaAktivitasMahasiswa';
    }

    public function medan(): array
    {
        return ['id_aktivitas', 'id_registrasi_mahasiswa', 'jenis_peran'];
    }

    /** @return Builder<TugasAkhir> */
    protected function query(string $termCode): Builder
    {
        return TugasAkhir::query()
            ->with(['mahasiswa'])
            ->whereNotNull('feeder_id')
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
            'id_aktivitas' => $model->feeder_id,
            'id_registrasi_mahasiswa' => $model->mahasiswa->feeder_registrasi_id,
            'jenis_peran' => AnggotaMbkmMapper::PERAN_PERSONAL,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function feederId(Model $model): ?string
    {
        return $model->getAttribute('feeder_anggota_id');
    }

    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill(['feeder_anggota_id' => $feederId])->saveQuietly();
    }

    public function label(Model $model): string
    {
        /** @var TugasAkhir $model */
        return $model->mahasiswa->nim.' · anggota '.$model->sebutan();
    }
}
