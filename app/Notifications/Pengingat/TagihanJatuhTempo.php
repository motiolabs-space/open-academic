<?php

declare(strict_types=1);

namespace App\Notifications\Pengingat;

use App\Enums\KategoriNotifikasi;
use App\Models\Keuangan\Tagihan;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * An invoice is about to fall due, or already has.
 *
 * Categorised as a reminder rather than as finance, and therefore muteable. The
 * invoice itself arrived through the mandatory Keuangan category; this is the
 * nudge, and someone who does not want nudges is entitled to stop them without
 * losing the record that the bill exists.
 */
class TagihanJatuhTempo extends Notifikasi
{
    /** @param int $hariTersisa negative once the due date has passed */
    public function __construct(
        public readonly Tagihan $tagihan,
        public readonly int $hariTersisa,
    ) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Pengingat;
    }

    public function judul(object $penerima): string
    {
        return $this->lewat()
            ? 'Tagihan melewati jatuh tempo'
            : 'Tagihan jatuh tempo '.$this->hariTersisa.' hari lagi';
    }

    public function ringkasan(object $penerima): string
    {
        $sisa = max(0, (int) $this->tagihan->total - (int) $this->tagihan->terbayar);

        return sprintf(
            'Tagihan %s (%s) menyisakan %s. Jatuh tempo %s.',
            $this->tagihan->nomor,
            $this->tagihan->keterangan,
            Format::rupiah($sisa),
            $this->tagihan->jatuh_tempo->translatedFormat('j F Y'),
        );
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.tagihan');
    }

    public function tone(): string
    {
        return $this->lewat() ? 'danger' : 'warning';
    }

    public function ajakan(): string
    {
        return 'Lihat Tagihan';
    }

    private function lewat(): bool
    {
        return $this->hariTersisa < 0;
    }
}
