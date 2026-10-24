<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Feeder\FeederMapping;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * The query selecting this entity's rows for the given term, eager loads
     * included. Everything else — the rows themselves, and how many there are
     * — derives from it.
     *
     * @return Builder<Model>
     */
    abstract protected function query(string $termCode): Builder;

    /**
     * The Feeder act that removes a row of this entity, or null when Feeder
     * offers none for it.
     *
     * Derived from the insert act by convention (InsertX → DeleteX). An entity
     * carried by an upserting act has no insert to mirror and is reported as
     * not removable rather than guessed at.
     */
    public function actHapus(): ?string
    {
        $act = $this->act();

        return str_starts_with($act, 'Insert') ? 'Delete'.substr($act, 6) : null;
    }

    /** Rows of this entity that belong to the given term. */
    public function rows(string $termCode): Collection
    {
        return $this->query($termCode)->get();
    }

    /**
     * How many rows are due, without loading one of them.
     *
     * The monitor screen asks this for every entity on every visit. Loading
     * each entity's rows with their relations to count them cost nine queries
     * per entity; a count costs one.
     */
    public function jumlah(string $termCode): int
    {
        return $this->query($termCode)->count();
    }

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

    /**
     * The identifier to record when Feeder's reply carries none.
     *
     * Most entities get a fresh id back from an insert. A few are keyed on an
     * identifier the local row already holds, and Feeder answers those with
     * nothing worth storing. Without this hook such a row would be inserted
     * again on every run — and rejected as a duplicate from the second one.
     */
    public function feederIdBawaan(Model $model): ?string
    {
        return null;
    }

    /**
     * The key Feeder wants on every call for this entity, or null.
     *
     * Most entities are inserted once and updated by the identifier Feeder
     * handed back. A few expose a single act that upserts by a composite of
     * identifiers the local row already knows; for those, every push is an
     * update keyed on this, and nothing is ever "inserted".
     *
     * @return array<string, string|null>|null
     */
    public function kunciAlami(Model $model): ?array
    {
        return null;
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
