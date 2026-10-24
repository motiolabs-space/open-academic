<?php

declare(strict_types=1);

namespace App\Notifications\Pengingat;

use App\DTOs\Keuangan\AturanDenda;
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

        $pesan = sprintf(
            'Tagihan %s (%s) menyisakan %s. Jatuh tempo %s.',
            $this->tagihan->nomor,
            $this->tagihan->keterangan,
            Format::rupiah($sisa),
            $this->tagihan->jatuh_tempo->translatedFormat('j F Y'),
        );

        return trim($pesan.' '.$this->soalDenda());
    }

    /**
     * The late-fee clause, when the campus charges one.
     *
     * Read at render time, not at construction: the notification is queued, and
     * the policy in force when the worker runs is the one the student will
     * actually be charged under. Before the due date it states the rule; after
     * it, the fee already running, so the figure the student sees here is the
     * figure on their invoice.
     */
    private function soalDenda(): string
    {
        $aturan = AturanDenda::dariConfig();

        if (!$aturan->aktif || $this->tagihan->dendaDibebaskan()) {
            return '';
        }

        $kalimat = __('keuangan.denda.pengingat', ['aturan' => $aturan->deskripsi()]);

        if ((int) $this->tagihan->denda > 0) {
            $kalimat .= ' '.__('keuangan.denda.berjalan', ['denda' => Format::rupiah((int) $this->tagihan->denda)]);
        }

        return $kalimat;
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
