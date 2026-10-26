<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The Neo Feeder service refused or could not be reached.
 *
 * Carries the Feeder error code so the sync ledger can record *why* a row
 * failed, not merely that it did — an operator chasing a reporting deadline
 * needs the reason, and "sync failed" is not one.
 */
class FeederException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $feederErrorCode = null,
        public readonly ?string $act = null,
    ) {
        parent::__construct($message);
    }

    public static function tidakTersambung(string $url, string $sebab): self
    {
        return new self(
            "Tidak dapat menghubungi Neo Feeder di {$url}. {$sebab}",
            act: 'connect',
        );
    }

    public static function gagalToken(string $desc): self
    {
        return new self(
            "Gagal memperoleh token Neo Feeder: {$desc}. Periksa kredensial pada .env.",
            act: 'GetToken',
        );
    }

    public static function ditolak(string $act, int $code, string $desc): self
    {
        return new self(
            "Neo Feeder menolak aksi {$act} (kode {$code}): {$desc}",
            feederErrorCode: $code,
            act: $act,
        );
    }

    public static function entitasTidakDikenal(string $entity): self
    {
        return new self("Entitas sinkronisasi \"{$entity}\" tidak terdaftar pada config/feeder.php.");
    }

    /** @param array<int, string> $belum */
    public static function dependensiBelumSinkron(string $entity, array $belum): self
    {
        return new self(sprintf(
            'Entitas %s tidak dapat dikirim sebelum %s berhasil disinkronkan pada semester yang sama.',
            $entity,
            implode(', ', $belum),
        ));
    }

    public static function adaBarisTidakValid(string $entity, int $jumlah): self
    {
        return new self(
            "Sinkronisasi {$entity} dibatalkan: {$jumlah} baris tidak lolos validasi pra-kirim. "
            .'Perbaiki data tersebut terlebih dahulu.',
        );
    }

    public static function dinonaktifkan(): self
    {
        return new self('Integrasi Neo Feeder sedang dinonaktifkan (FEEDER_ENABLED=false).');
    }

    /**
     * Refuses to compare an entity that has no comparison configured.
     *
     * Thrown rather than answered with an empty result: "tidak ada selisih" and
     * "tidak pernah diperiksa" look identical on a screen, and only one of them
     * is a reason to stop worrying.
     */
    public static function hanyaLewatPddiktiAdmin(string $kolom): self
    {
        return new self(
            "Perubahan {$kolom} tidak diterima web service Feeder; ajukan lewat PDDIKTI admin "
            .'dengan surat permohonan dan lampirannya.',
        );
    }

    public static function registrasiBelumSinkron(string $nim): self
    {
        return new self(
            "Riwayat pendidikan mahasiswa {$nim} belum pernah terkirim ke Feeder, "
            .'jadi tidak ada id_registrasi_mahasiswa yang dapat diperbarui.',
        );
    }

    public static function prodiTanpaKodePddikti(string $prodi): self
    {
        return new self(
            "Program studi {$prodi} belum memiliki kode PDDIKTI (id_sms), jadi daftar mahasiswanya "
            .'tidak dapat dibaca dari Feeder.',
        );
    }

    public static function adopsiBukanAmbigu(): self
    {
        return new self('Hanya temuan adopsi yang ambigu yang dapat ditautkan atau dipisahkan secara manual.');
    }

    public static function kandidatTidakDikenal(string $idRegistrasi): self
    {
        return new self("Registrasi {$idRegistrasi} bukan salah satu kandidat temuan ini. Jalankan adopsi ulang bila daftar Feeder berubah.");
    }

    public static function registrasiSudahDipakai(string $nim): self
    {
        return new self("Registrasi Feeder itu sudah tertaut ke mahasiswa {$nim}. Satu registrasi PDDIKTI hanya untuk satu mahasiswa.");
    }

    public static function tertautLain(string $nim): self
    {
        return new self(
            "Mahasiswa {$nim} sudah tertaut ke data Feeder lain. Periksa riwayat sinkronisasinya sebelum "
            .'menautkan ulang; menimpa id Feeder diam-diam akan memutus nilai dan KRS yang sudah terkirim.',
        );
    }

    public static function tidakDapatDibandingkan(string $entity): self
    {
        return new self(
            "Entitas \"{$entity}\" belum dapat dibandingkan dengan Feeder: aksi pembacaannya "
            .'belum ditetapkan pada config/feeder.php (bagian reconcile).',
        );
    }
}
