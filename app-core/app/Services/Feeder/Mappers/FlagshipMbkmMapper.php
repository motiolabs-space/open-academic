<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Klaim program flagship — an MBKM activity declared as having run under one
 * of the ministry's flagship programmes.
 *
 * The claim is made on an activity PDDIKTI already holds, after its member:
 * the programme recognises a participant, not a title. Hence it depends on
 * anggota_mbkm, and a verified activity whose member has not arrived yet is
 * reported by the validator rather than silently left out.
 *
 * The programme code comes from the GetListAktivitasMBKMFlagship reference
 * pull (ref type program_flagship). It is not seeded and not mapped through
 * an enum: the list is the ministry's, changes per cycle, and a campus picks
 * from it directly.
 *
 * PDDIKTI holds at most one claim per activity, addressed by the activity's
 * id. The identifier recorded is therefore the activity's, whatever the
 * reply carries — a re-run compares hashes and skips, and a changed
 * programme is sent as an update keyed on the same activity.
 */
class FlagshipMbkmMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertAktivitasMBKMFlagship';
    }

    public function medan(): array
    {
        return ['id_aktivitas', 'id_flagship'];
    }

    /** @return Builder<AktivitasMahasiswa> */
    protected function query(string $termCode): Builder
    {
        return AktivitasMahasiswa::query()
            ->with(['mahasiswa'])
            ->terverifikasi()
            ->whereNotNull('flagship_kode')
            ->whereHas('tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode))
            ->orderBy('id');
    }

    /** @param AktivitasMahasiswa $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_aktivitas' => $model->feeder_id,
            'id_flagship' => $model->flagship_kode,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function feederId(Model $model): ?string
    {
        return $model->getAttribute('feeder_flagship_id');
    }

    public function feederIdBawaan(Model $model): ?string
    {
        return $model->getAttribute('feeder_id');
    }

    /** The claim is addressed by its activity, not by whatever the reply named. */
    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill(['feeder_flagship_id' => $model->getAttribute('feeder_id') ?? $feederId])->saveQuietly();
    }

    public function label(Model $model): string
    {
        /** @var AktivitasMahasiswa $model */
        return $model->mahasiswa->nim.' · flagship';
    }
}
