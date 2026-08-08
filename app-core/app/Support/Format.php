<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Indonesian number and date formatting.
 *
 * Centralised because the design system is explicit about it: decimal comma,
 * thousand dot, "29 Jul 2026", "07.30 WIB". Formatting inline in Blade is how
 * a codebase ends up with three different renderings of the same GPA.
 */
final class Format
{
    /** @var array<int, string> */
    private const BULAN = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /** @var array<int, string> */
    private const BULAN_PANJANG = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** @var array<int, string> */
    private const HARI = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
    ];

    /** "Rp4.850.000" — whole rupiah, never a decimal. */
    public static function rupiah(int|float|null $nominal): string
    {
        return 'Rp'.number_format((float) ($nominal ?? 0), 0, ',', '.');
    }

    /** "3,74" — decimal comma, fixed precision for GPAs and scores. */
    public static function angka(int|float|null $nilai, int $desimal = 2): string
    {
        return number_format((float) ($nilai ?? 0), $desimal, ',', '.');
    }

    /** "12.847" — thousands separator, no decimals. */
    public static function bulat(int|float|null $nilai): string
    {
        return number_format((float) ($nilai ?? 0), 0, ',', '.');
    }

    /** "29 Jul 2026" */
    public static function tanggal(Carbon|string|null $tanggal): string
    {
        $date = self::carbon($tanggal);

        return $date === null
            ? '—'
            : sprintf('%d %s %d', $date->day, self::BULAN[$date->month], $date->year);
    }

    /** "29 Juli 2026" */
    public static function tanggalPanjang(Carbon|string|null $tanggal): string
    {
        $date = self::carbon($tanggal);

        return $date === null
            ? '—'
            : sprintf('%d %s %d', $date->day, self::BULAN_PANJANG[$date->month], $date->year);
    }

    /** "Rabu, 29 Juli 2026" */
    public static function tanggalHari(Carbon|string|null $tanggal): string
    {
        $date = self::carbon($tanggal);

        return $date === null
            ? '—'
            : self::HARI[(int) $date->dayOfWeek].', '.self::tanggalPanjang($date);
    }

    /** "07.30" — Indonesian clock notation uses a dot. */
    public static function jam(Carbon|string|null $waktu): string
    {
        if ($waktu === null) {
            return '—';
        }

        $raw = $waktu instanceof Carbon ? $waktu->format('H:i') : substr($waktu, 0, 5);

        return str_replace(':', '.', $raw);
    }

    /** "07.30 – 09.10 WIB" */
    public static function rentangJam(Carbon|string|null $mulai, Carbon|string|null $selesai): string
    {
        return self::jam($mulai).' – '.self::jam($selesai).' WIB';
    }

    private static function carbon(Carbon|string|null $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
