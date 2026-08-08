<?php

declare(strict_types=1);

namespace App\Notifications\Kemahasiswaan;

use App\Enums\KategoriNotifikasi;
use App\Enums\LeaveStatus;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Notifications\Notifikasi;

/**
 * A leave application was granted or refused.
 *
 * Mandatory: the outcome changes the student's reported status at PDDIKTI and
 * whether they may enrol. Someone who assumes leave was granted, and was not,
 * accrues a semester they did not study.
 */
class CutiDiputus extends Notifikasi
{
    public function __construct(public readonly CutiMahasiswa $cuti) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kemahasiswaan;
    }

    public function judul(object $penerima): string
    {
        return 'Pengajuan cuti '.strtolower($this->cuti->status->label());
    }

    public function ringkasan(object $penerima): string
    {
        $pesan = sprintf(
            'Pengajuan cuti Anda untuk %s: %s.',
            $this->cuti->tahunAkademik->nama,
            $this->cuti->status->label(),
        );

        return $this->cuti->catatan !== null
            ? $pesan.' Catatan: '.$this->cuti->catatan
            : $pesan;
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.profil');
    }

    public function tone(): string
    {
        return match ($this->cuti->status) {
            LeaveStatus::Disetujui => 'success',
            LeaveStatus::Ditolak => 'danger',
            default => 'info',
        };
    }
}
