<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use App\Enums\JenisSelisihFeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One disagreement between this application and PDDIKTI, as of one comparison.
 *
 * The sync ledger records what left this building. This table records what the
 * other end actually holds — which is not the same thing, and only one of them
 * is what the ministry reads.
 */
class FeederDiff extends Model
{
    protected $table = 'feeder_diffs';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisSelisihFeeder::class,
            'selisih' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeBatch(Builder $query, string $batchId): Builder
    {
        return $query->where('batch_id', $batchId);
    }

    /** @param Builder<self> $query */
    public function scopeEntity(Builder $query, string $entity): Builder
    {
        return $query->where('entity', $entity);
    }

    /**
     * Fields that disagree, as "field: lokal ≠ feeder" lines.
     *
     * @return array<int, string>
     */
    public function ringkasSelisih(): array
    {
        return collect($this->selisih ?? [])
            ->map(fn (array $sisi, string $field): string => sprintf(
                '%s: %s ≠ %s',
                $field,
                $this->tampilkan($sisi['lokal'] ?? null),
                $this->tampilkan($sisi['feeder'] ?? null),
            ))
            ->values()
            ->all();
    }

    private function tampilkan(mixed $nilai): string
    {
        return blank($nilai) ? '(kosong)' : (string) $nilai;
    }
}
