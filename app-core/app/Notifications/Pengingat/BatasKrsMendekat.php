<?php

declare(strict_types=1);

namespace App\Notifications\Pengingat;

use App\Enums\KategoriNotifikasi;
use App\Models\Akademik\TahunAkademik;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * The study-plan window closes soon and this student has not submitted one.
 *
 * Sent only to students with nothing submitted. A reminder that also reaches the
 * people who already complied is how a channel becomes noise — and the students
 * who most need this one are exactly those least likely to read a channel full
 * of messages that did not apply to them.
 */
class BatasKrsMendekat extends Notifikasi
{
    public function __construct(
        public readonly TahunAkademik $term,
        public readonly int $hariTersisa,
    ) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Pengingat;
    }

    public function judul(object $penerima): string
    {
        return 'Pengisian KRS ditutup '.$this->hariTersisa.' hari lagi';
    }

    public function ringkasan(object $penerima): string
    {
        return sprintf(
            'Anda belum mengajukan rencana studi untuk %s. Pengisian ditutup %s.',
            $this->term->nama,
            Format::tanggalPanjang($this->term->krs_selesai),
        );
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.krs');
    }

    public function tone(): string
    {
        return $this->hariTersisa <= 2 ? 'danger' : 'warning';
    }

    public function ajakan(): string
    {
        return 'Isi Rencana Studi';
    }
}
