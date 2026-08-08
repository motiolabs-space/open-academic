<?php

declare(strict_types=1);

namespace App\Notifications\Pengingat;

use App\Enums\KategoriNotifikasi;
use App\Notifications\Notifikasi;

/**
 * Consultation logs are waiting for this lecturer's sign-off.
 *
 * A digest, deliberately. A supervisor with eight students would otherwise get
 * eight messages saying the same thing, and the eighth teaches them to stop
 * reading the first.
 *
 * It carries only a count and a link. The alternative — listing every pending
 * entry — makes the message long enough that the count stops being visible,
 * which is the one number the recipient acts on.
 */
class BimbinganMenunggu extends Notifikasi
{
    public function __construct(
        public readonly int $jumlah,
        public readonly int $mahasiswa,
    ) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Pengingat;
    }

    public function judul(object $penerima): string
    {
        return $this->jumlah.' log bimbingan menunggu persetujuan Anda';
    }

    public function ringkasan(object $penerima): string
    {
        return sprintf(
            '%d catatan bimbingan dari %d mahasiswa belum Anda setujui. Log yang belum '
                .'disetujui tidak dihitung sebagai syarat sidang.',
            $this->jumlah,
            $this->mahasiswa,
        );
    }

    public function tautan(object $penerima): ?string
    {
        return route('dosen.tugas-akhir');
    }

    public function tone(): string
    {
        return 'warning';
    }

    public function ajakan(): string
    {
        return 'Buka Bimbingan';
    }
}
