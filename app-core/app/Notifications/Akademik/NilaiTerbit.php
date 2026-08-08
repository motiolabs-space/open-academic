<?php

declare(strict_types=1);

namespace App\Notifications\Akademik;

use App\Enums\KategoriNotifikasi;
use App\Models\Akademik\KelasKuliah;
use App\Notifications\Notifikasi;

/**
 * A class's grades have been finalised.
 *
 * Sent per class rather than per student-grade: a lecturer finalising one
 * offering is one event, and the student wants to know their mark for that
 * course, not to receive a message per component.
 */
class NilaiTerbit extends Notifikasi
{
    public function __construct(public readonly KelasKuliah $kelas) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Akademik;
    }

    public function judul(object $penerima): string
    {
        return 'Nilai '.$this->kelas->mataKuliah->nama.' sudah terbit';
    }

    public function ringkasan(object $penerima): string
    {
        return 'Nilai akhir untuk '.$this->kelas->namaLengkap()
            .' sudah difinalisasi dan tampil pada KHS Anda.';
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.khs');
    }

    public function tone(): string
    {
        return 'success';
    }

    public function ajakan(): string
    {
        return 'Lihat KHS';
    }
}
