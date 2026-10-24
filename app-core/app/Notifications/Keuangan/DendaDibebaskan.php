<?php

declare(strict_types=1);

namespace App\Notifications\Keuangan;

use App\Enums\KategoriNotifikasi;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * A late fee was forgiven.
 *
 * Told to the student for the same reason a reduction is: someone who does
 * not know the bill changed pays the old figure, and the overpayment then has
 * to be chased back out of the system by hand. The reason travels with it so
 * the student reads a decision, not a mystery.
 */
class DendaDibebaskan extends Notifikasi
{
    public function __construct(
        public readonly Tagihan $tagihan,
        public readonly TagihanItem $pembebasan,
    ) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Keuangan;
    }

    public function judul(object $penerima): string
    {
        return __('keuangan.denda.notifikasi_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('keuangan.denda.notifikasi_ringkasan', [
            'denda' => Format::rupiah($this->pembebasan->besaran()),
            'nomor' => $this->tagihan->nomor,
            'alasan' => rtrim((string) $this->pembebasan->alasan, '.').'.',
            'sisa' => Format::rupiah($this->tagihan->sisa()),
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.tagihan');
    }

    public function tone(): string
    {
        return 'success';
    }

    public function ajakan(): string
    {
        return 'Lihat Tagihan';
    }
}
