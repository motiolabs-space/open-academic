<?php

declare(strict_types=1);

namespace App\Notifications\Surat;

use App\Enums\KategoriNotifikasi;
use App\Enums\StatusSurat;
use App\Models\Surat\Surat;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * A letter request has been decided.
 *
 * Covers both outcomes. A rejection that goes unannounced sends the applicant
 * back to the counter to ask why — which is the queue this whole module exists
 * to shorten, so leaving it out would give back most of the gain.
 */
class SuratTerbit extends Notifikasi
{
    public function __construct(public readonly Surat $surat) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kemahasiswaan;
    }

    public function judul(object $penerima): string
    {
        return $this->ditolak()
            ? $this->surat->jenis->label().' ditolak'
            : $this->surat->jenis->label().' sudah terbit';
    }

    public function ringkasan(object $penerima): string
    {
        if ($this->ditolak()) {
            return 'Permohonan Anda ditolak. Alasan: '.($this->surat->alasan ?? '—');
        }

        $pesan = 'Nomor '.$this->surat->nomor.'. Berkas dapat diunduh dari portal';

        return $this->surat->berlaku_sampai !== null
            ? $pesan.', berlaku sampai '.Format::tanggalPanjang($this->surat->berlaku_sampai).'.'
            : $pesan.'.';
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.surat');
    }

    public function tone(): string
    {
        return $this->ditolak() ? 'danger' : 'success';
    }

    public function ajakan(): string
    {
        return 'Buka Surat';
    }

    private function ditolak(): bool
    {
        return $this->surat->status === StatusSurat::Ditolak;
    }
}
