<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a student entered the programme — PDDIKTI's "jenis pendaftaran".
 *
 * It decides what the enrolment record must carry: a transfer or a
 * recognition of prior learning is refused by Feeder without the credits
 * recognised and the institution they came from, and refused again at the
 * diploma-number portal when those credits fall short. The codes themselves
 * come from the mapping table, like every other PDDIKTI code.
 */
enum JenisDaftar: string
{
    case Baru = 'baru';
    case Pindahan = 'pindahan';
    case RplTransfer = 'rpl_transfer';
    case RplPerolehan = 'rpl_perolehan';
    case FastTrack = 'fast_track';
    case NonGelar = 'non_gelar';

    /*
     * A new registration of someone already registered here (ROADMAP #55):
     * an internal programme transfer, or readmission after leaving. Only the
     * re-NIM flow creates these; the codes come from the campus's mapping of
     * the GetJenisPendaftaran pull like every other kind.
     */
    case PindahProdi = 'pindah_prodi';
    case MasukKembali = 'masuk_kembali';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Peserta didik baru',
            self::Pindahan => 'Pindahan',
            self::RplTransfer => 'RPL — transfer SKS',
            self::RplPerolehan => 'RPL — perolehan SKS',
            self::FastTrack => 'Fast track',
            self::NonGelar => 'Non-gelar',
            self::PindahProdi => 'Pindah program studi (internal)',
            self::MasukKembali => 'Masuk kembali',
        };
    }

    /** Whether PDDIKTI requires recognised credits and an origin for this kind. */
    public function butuhAsal(): bool
    {
        return in_array($this, [self::Pindahan, self::RplTransfer, self::RplPerolehan, self::PindahProdi, self::MasukKembali], true);
    }

    /**
     * Whether the origin is this campus: a registration started by the
     * re-NIM flow, never chosen on the transfer intake or edited there.
     */
    public function internal(): bool
    {
        return in_array($this, [self::PindahProdi, self::MasukKembali], true);
    }

    /** @return array<string, string> the kinds a person may pick on an intake screen */
    public static function optionsIntake(): array
    {
        return collect(self::cases())
            ->reject(fn (self $case): bool => $case->internal())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
