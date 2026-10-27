<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where one version of a final-project proposal stands.
 *
 * Only Menunggu changes, and only once: a decided version keeps its decision
 * for good. Revising means submitting the next version, not reopening this one.
 */
enum StatusProposal: string
{
    case Menunggu = 'menunggu';
    case Revisi = 'revisi';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return __('tugas_akhir_proposal.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Menunggu => 'warning',
            self::Revisi => 'info',
            self::Diterima => 'success',
            self::Ditolak => 'danger',
        };
    }
}
