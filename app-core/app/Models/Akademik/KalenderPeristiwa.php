<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\JenisPeristiwa;
use App\Enums\SasaranPeristiwa;
use App\Enums\StatusPeristiwa;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A calendar entry nothing else owns: a holiday, a campus activity, or an
 * administrative deadline.
 *
 * Not the KRS window, not an exam, not graduation day — those are read from
 * their own tables by KalenderService. See the migration for why.
 *
 * @property JenisPeristiwa $jenis
 * @property SasaranPeristiwa $sasaran
 * @property StatusPeristiwa $status
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 */
class KalenderPeristiwa extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'kalender_peristiwa';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPeristiwa::class,
            'sasaran' => SasaranPeristiwa::class,
            'status' => StatusPeristiwa::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function scopeTerbit(Builder $query): Builder
    {
        return $query->where('status', StatusPeristiwa::Terbit->value);
    }

    public function scopeLibur(Builder $query): Builder
    {
        return $query->where('jenis', JenisPeristiwa::Libur->value);
    }

    /** Entries touching any day between two dates, inclusive. */
    public function scopeBeririsan(Builder $query, Carbon|string $dari, Carbon|string $sampai): Builder
    {
        return $query
            ->whereDate('tanggal_mulai', '<=', Carbon::parse($sampai)->toDateString())
            ->whereDate('tanggal_selesai', '>=', Carbon::parse($dari)->toDateString());
    }

    public function sudahTerbit(): bool
    {
        return $this->status === StatusPeristiwa::Terbit;
    }

    /**
     * Published holidays between two dates, by day.
     *
     * The one question other modules ask of the calendar, answered without
     * going through KalenderService — the exam timetable asks it, and the
     * calendar in turn reads the exam timetable, so a service dependency
     * between the two would be circular. One query, whatever the range.
     *
     * A holiday for one programme only applies to that programme's classes;
     * a holiday aimed only at staff is not a day off for an exam.
     *
     * @return array<string, list<array{judul: string, prodi_id: ?int}>> keyed by Y-m-d
     */
    public static function tanggalLibur(Carbon|string $dari, Carbon|string $sampai): array
    {
        $dari = Carbon::parse($dari)->startOfDay();
        $sampai = Carbon::parse($sampai)->startOfDay();

        $hari = [];

        $libur = self::query()
            ->terbit()
            ->libur()
            ->where('sasaran', '!=', SasaranPeristiwa::Staf->value)
            ->beririsan($dari, $sampai)
            ->get(['judul', 'tanggal_mulai', 'tanggal_selesai', 'prodi_id']);

        foreach ($libur as $l) {
            $tanggal = $l->tanggal_mulai->copy()->max($dari);
            $akhir = $l->tanggal_selesai->copy()->min($sampai);

            while ($tanggal->lte($akhir)) {
                $hari[$tanggal->toDateString()][] = ['judul' => $l->judul, 'prodi_id' => $l->prodi_id];
                $tanggal->addDay();
            }
        }

        return $hari;
    }

    /**
     * The holiday falling on a day for a programme's classes, if any.
     *
     * @param array<string, list<array{judul: string, prodi_id: ?int}>> $libur from tanggalLibur()
     */
    public static function liburUntuk(array $libur, string $tanggal, ?int $prodiId): ?string
    {
        foreach ($libur[$tanggal] ?? [] as $l) {
            if ($l['prodi_id'] === null || $l['prodi_id'] === $prodiId) {
                return $l['judul'];
            }
        }

        return null;
    }
}
