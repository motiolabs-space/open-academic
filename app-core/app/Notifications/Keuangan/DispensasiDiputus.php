<?php

declare(strict_types=1);

namespace App\Notifications\Keuangan;

use App\Enums\KategoriNotifikasi;
use App\Enums\StatusDispensasi;
use App\Models\Keuangan\DispensasiTagihan;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * A dispensation was granted, refused, or revoked.
 *
 * All three change whether the student can fill a KRS this week, which is
 * exactly the kind of thing nobody checks for by logging in.
 */
class DispensasiDiputus extends Notifikasi
{
    public function __construct(public readonly DispensasiTagihan $dispensasi) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Keuangan;
    }

    public function judul(object $penerima): string
    {
        return __('keuangan.dispensasi.notifikasi.judul_'.$this->kunci());
    }

    public function ringkasan(object $penerima): string
    {
        return __('keuangan.dispensasi.notifikasi.ringkasan_'.$this->kunci(), [
            'nomor' => $this->dispensasi->tagihan->nomor,
            'tanggal' => Format::tanggal($this->dispensasi->sampai),
            'catatan' => $this->dispensasi->catatan_keputusan ?? '—',
            'alasan' => $this->dispensasi->alasan_cabut ?? '—',
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.tagihan');
    }

    public function tone(): string
    {
        return $this->dispensasi->status === StatusDispensasi::Disetujui ? 'success' : 'warning';
    }

    public function ajakan(): string
    {
        return __('keuangan.dispensasi.notifikasi.ajakan');
    }

    private function kunci(): string
    {
        return match ($this->dispensasi->status) {
            StatusDispensasi::Ditolak => 'ditolak',
            StatusDispensasi::Dicabut => 'dicabut',
            default => 'diberikan',
        };
    }
}
