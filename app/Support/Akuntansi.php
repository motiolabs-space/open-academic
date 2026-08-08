<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whether this installation keeps its books anywhere but here.
 *
 * The accounting bridge is **opt-in and inert until switched on**. A campus that
 * uses a different ledger, or keeps one by hand, should not accumulate a queue
 * of documents nobody will ever send, see a menu item for a system they do not
 * have, or run a scheduled command every five minutes that has nothing to do.
 *
 * One place holds the driver names so the string "nonaktif" is not scattered
 * across a service, a controller, a command, the navigation, and a Blade file —
 * five places that would each have to be found again when the switch changes.
 */
class Akuntansi
{
    public const NONAKTIF = 'nonaktif';

    public const PALSU = 'palsu';

    public const EASYERP = 'easyerp';

    /**
     * The configured driver, treating blank as off.
     *
     * A bare `AKUNTANSI_DRIVER=` in .env yields an empty string, not a missing
     * key, so a plain config default would not catch it. Reading that as "off"
     * rather than as an unknown driver is the safe direction: the failure mode
     * of guessing wrong here is an exception on every invoice issued.
     */
    public static function driver(): string
    {
        $driver = config('akuntansi.driver');

        return blank($driver) ? self::NONAKTIF : (string) $driver;
    }

    /**
     * Whether anything at all should be recorded.
     *
     * False means the module does not exist as far as the rest of the
     * application is concerned: no outbox rows, no menu, no scheduled sends.
     */
    public static function aktif(): bool
    {
        return self::driver() !== self::NONAKTIF;
    }

    /**
     * Whether documents actually leave the machine.
     *
     * `palsu` records and queues but sends nothing — the state for trying the
     * module out, and what the demo campus and the test suite use.
     */
    public static function mengirim(): bool
    {
        return self::driver() === self::EASYERP;
    }

    /** @return array<int, string> */
    public static function driverDikenal(): array
    {
        return [self::NONAKTIF, self::PALSU, self::EASYERP];
    }
}
