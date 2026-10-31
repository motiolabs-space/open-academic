<?php

declare(strict_types=1);

namespace App\Notifications\Akademik;

use App\Enums\KategoriNotifikasi;
use App\Models\Akademik\KonversiKredit;
use App\Notifications\Notifikasi;

/**
 * The assessor needs a document before deciding a credit conversion. The
 * decision waits on the student, which they cannot know without being told.
 */
class DokumenKonversiDiminta extends Notifikasi
{
    public function __construct(public readonly KonversiKredit $konversi) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Akademik;
    }

    public function judul(object $penerima): string
    {
        return __('swalayan.konversi.notifikasi.judul_diminta', ['kode' => $this->konversi->mataKuliah->kode]);
    }

    public function ringkasan(object $penerima): string
    {
        return __('swalayan.konversi.notifikasi.ringkasan_diminta', [
            'mata_kuliah' => $this->konversi->mataKuliah->nama,
            'dokumen' => $this->konversi->dokumen_diminta,
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.konversi');
    }

    public function tone(): string
    {
        return 'warning';
    }
}
