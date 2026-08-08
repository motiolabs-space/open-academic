<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * One definition of "search these columns for this word".
 *
 * The portability problem it solves is quiet rather than loud. `LIKE` is
 * case-insensitive on MySQL under a `_ci` collation and case-sensitive on
 * PostgreSQL, so a search box written with a raw `like` works on one engine and
 * silently stops finding "budi" when the record says "Budi" on the other.
 * Nothing errors; staff simply conclude the student is not in the system.
 *
 * Laravel's `whereLike($column, $value, caseSensitive: false)` resolves that per
 * driver — `ilike` on PostgreSQL, `like` on MySQL — so every search goes through
 * here and inherits the answer.
 */
trait DapatDicari
{
    /**
     * @param Builder<static> $query
     * @param array<int, string> $kolom Column names; "relasi.kolom" searches a relation.
     * @return Builder<static>
     */
    public function scopeCari(Builder $query, ?string $kata, array $kolom): Builder
    {
        $kata = trim((string) $kata);

        if ($kata === '' || $kolom === []) {
            return $query;
        }

        // Wrapped so the OR group cannot swallow the filters around it — an
        // unwrapped orWhere turns "active students named Budi" into "active
        // students, or anyone named Budi".
        return $query->where(function (Builder $grup) use ($kolom, $kata): void {
            foreach ($kolom as $satu) {
                if (str_contains($satu, '.')) {
                    [$relasi, $kolomRelasi] = explode('.', $satu, 2);

                    $grup->orWhereHas(
                        $relasi,
                        fn (Builder $q) => $q->whereLike($kolomRelasi, "%{$kata}%", false),
                    );

                    continue;
                }

                $grup->orWhereLike($satu, "%{$kata}%", false);
            }
        });
    }
}
