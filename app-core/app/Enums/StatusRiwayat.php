<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a personnel-history row stands with the staff who vouch for it.
 *
 * Three states and no draft. A lecturer's entry is a claim the moment it is
 * saved; what changes is whether somebody with the documents in front of them
 * has agreed. A rejected row stays — with its reason — so the lecturer sees
 * what to correct instead of an entry that silently vanished.
 */
enum StatusRiwayat: string
{
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return __('kepegawaian.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Diajukan => 'info',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
        };
    }

    /**
     * Whether the lecturer may still change it.
     *
     * An approved row is the campus vouching for a document. Letting its owner
     * edit it afterwards would keep the approval and replace what was approved.
     */
    public function dapatDisunting(): bool
    {
        return $this !== self::Disetujui;
    }
}
