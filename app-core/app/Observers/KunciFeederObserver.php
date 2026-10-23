<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\JenisPerubahanFeeder;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Models\Kemahasiswaan\Yudisium;
use App\Services\Feeder\FeederPerubahanService;
use App\Services\Feeder\KolomTerkunci;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Watches the columns PDDIKTI locks and records a PDM/PJK request the
 * moment one changes on a student who is already reported.
 *
 * Attached to every model that holds such a column — the student row, the
 * yudisium, the ceremony participant (diploma number), the study evaluation
 * — rather than to the screens that edit them: a column changed by an
 * import, a command, or a service nobody thought of is exactly the change
 * that must not slip through.
 */
class KunciFeederObserver
{
    public function __construct(private readonly FeederPerubahanService $perubahan) {}

    public function updated(Model $model): void
    {
        $katalog = KolomTerkunci::untuk($model::class);
        $berubah = array_intersect_key($model->getChanges(), $katalog);

        if ($berubah === []) {
            return;
        }

        $mahasiswa = $this->mahasiswaDari($model);

        if ($mahasiswa === null) {
            return;
        }

        foreach (array_keys($berubah) as $lokal) {
            [$kolomPddikti, $jenis, $jalur] = $katalog[$lokal];

            if (!$this->terkunci($mahasiswa, $jenis)) {
                continue;
            }

            $this->perubahan->catat(
                $mahasiswa,
                $kolomPddikti,
                $jenis,
                $jalur,
                $this->teks($lokal, $model->getOriginal($lokal)),
                $this->teks($lokal, $model->getAttribute($lokal)),
            );
        }
    }

    /** Identity is frozen once the biodata is reported; the exit once Lulus/DO is. */
    private function terkunci(Mahasiswa $mahasiswa, JenisPerubahanFeeder $jenis): bool
    {
        return match ($jenis) {
            JenisPerubahanFeeder::Pdm => filled($mahasiswa->feeder_id),
            JenisPerubahanFeeder::Pjk => filled($mahasiswa->feeder_keluar_id),
        };
    }

    /**
     * The student a changed row belongs to, fetched rather than lazy-loaded:
     * lazy loading is a hard error outside production, and rightly so.
     */
    private function mahasiswaDari(Model $model): ?Mahasiswa
    {
        if ($model instanceof Mahasiswa) {
            return $model;
        }

        if ($model instanceof WisudaPeserta) {
            $mahasiswaId = Yudisium::query()->whereKey($model->yudisium_id)->value('mahasiswa_id');

            return $mahasiswaId !== null ? Mahasiswa::query()->find($mahasiswaId) : null;
        }

        return Mahasiswa::query()->find($model->getAttribute('mahasiswa_id'));
    }

    /**
     * The value as PDDIKTI would see it, for the request row and the letter.
     *
     * A term is named by its code, not our row id; dates by Y-m-d; enums by
     * their value. The text is what the operator copies onto the form.
     */
    private function teks(string $lokal, mixed $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        if ($lokal === 'tahun_akademik_id') {
            return TahunAkademik::query()->whereKey($nilai)->value('kode') ?? (string) $nilai;
        }

        return match (true) {
            $nilai instanceof DateTimeInterface => $nilai->format('Y-m-d'),
            $nilai instanceof BackedEnum => (string) $nilai->value,
            default => (string) $nilai,
        };
    }
}
