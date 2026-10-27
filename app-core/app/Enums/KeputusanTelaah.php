<?php

declare(strict_types=1);

namespace App\Enums;

/** A reviewer's decision on one proposal version. */
enum KeputusanTelaah: string
{
    case Revisi = 'revisi';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return __('tugas_akhir_proposal.keputusan.'.$this->value);
    }

    public function status(): StatusProposal
    {
        return match ($this) {
            self::Revisi => StatusProposal::Revisi,
            self::Diterima => StatusProposal::Diterima,
            self::Ditolak => StatusProposal::Ditolak,
        };
    }

    /** A request for changes or a refusal says why; an acceptance need not. */
    public function wajibCatatan(): bool
    {
        return $this !== self::Diterima;
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
