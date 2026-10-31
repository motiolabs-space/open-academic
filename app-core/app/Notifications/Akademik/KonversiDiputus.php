<?php

declare(strict_types=1);

namespace App\Notifications\Akademik;

use App\Enums\KategoriNotifikasi;
use App\Enums\StatusKonversi;
use App\Models\Akademik\KonversiKredit;
use App\Notifications\Notifikasi;

/**
 * A credit conversion was recognised or refused.
 *
 * Academic, so mandatory: recognised credits change what the student still has
 * to take, and a refused one is a course they must now plan for.
 */
class KonversiDiputus extends Notifikasi
{
    public function __construct(public readonly KonversiKredit $konversi) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Akademik;
    }

    public function judul(object $penerima): string
    {
        return __('swalayan.konversi.notifikasi.judul_'.$this->kunci(), ['kode' => $this->konversi->mataKuliah->kode]);
    }

    public function ringkasan(object $penerima): string
    {
        return __('swalayan.konversi.notifikasi.ringkasan_'.$this->kunci(), [
            'mata_kuliah' => $this->konversi->mataKuliah->nama,
            'sks' => $this->konversi->sks_diakui,
            'catatan' => $this->konversi->catatan ?? '—',
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.konversi');
    }

    public function tone(): string
    {
        return $this->konversi->status === StatusKonversi::Disetujui ? 'success' : 'warning';
    }

    private function kunci(): string
    {
        return $this->konversi->status === StatusKonversi::Disetujui ? 'diakui' : 'ditolak';
    }
}
