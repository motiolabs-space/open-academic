<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Feeder\FeederMapping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Base for the per-entity payload mappers.
 *
 * Every mapper answers three questions: which Feeder action carries this
 * entity, which local rows are due for a term, and what the payload looks like.
 * Local enum values are translated through FeederMapping rather than assumed to
 * equal PDDIKTI codes — an institution on an older Feeder build changes a row
 * in a table, not a line of code.
 */
abstract class FeederMapper
{
    /** The Feeder act that carries this entity, e.g. InsertBiodataMahasiswa. */
    abstract public function act(): string;

    /** Rows of this entity that belong to the given term. */
    abstract public function rows(string $termCode): Collection;

    /** @return array<string, mixed> the record Feeder receives */
    abstract public function payload(Model $model): array;

    /** Human label for the ledger and the monitor screen. */
    public function label(Model $model): string
    {
        foreach (['nim', 'nidn', 'kode', 'nama'] as $atribut) {
            if (filled($model->getAttribute($atribut))) {
                return (string) $model->getAttribute($atribut);
            }
        }

        return class_basename($model).'#'.$model->getKey();
    }

    /** Feeder's own identifier for a row we have already pushed. */
    public function feederId(Model $model): ?string
    {
        return $model->getAttribute('feeder_id');
    }

    /** Records the identifier Feeder assigned, so later pushes update in place. */
    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill([
            'feeder_id' => $feederId,
            'feeder_synced_at' => now(),
        ])->saveQuietly();
    }

    /** Translates a local enum value into the Feeder code for the same concept. */
    protected function kode(string $group, ?string $localValue, ?string $fallback = null): ?string
    {
        if ($localValue === null) {
            return $fallback;
        }

        return FeederMapping::toFeeder($group, $localValue) ?? $fallback;
    }

    /** Feeder expects Y-m-d and rejects nulls formatted as empty strings. */
    protected function tanggal(mixed $tanggal): ?string
    {
        if (blank($tanggal)) {
            return null;
        }

        return $tanggal instanceof \DateTimeInterface
            ? $tanggal->format('Y-m-d')
            : (string) $tanggal;
    }
}
