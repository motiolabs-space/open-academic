<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\FeederException;
use App\Models\Akademik\TahunAkademik;
use App\Services\Feeder\FeederSyncService;
use Illuminate\Console\Command;

/**
 * The other half of a correction: what was reported and has since been
 * removed here is removed at PDDIKTI too.
 */
class FeederHapusCommand extends Command
{
    protected $signature = 'openacademic:feeder-hapus
        {entity : Entitas yang diperiksa, atau "semua"}
        {--term= : Kode semester PDDIKTI (mis. 20261); bawaan semester aktif}';

    protected $description = 'Menghapus dari Neo Feeder baris yang sudah terkirim tetapi tidak lagi ada di sini';

    public function handle(FeederSyncService $sync): int
    {
        $kode = $this->option('term');
        $term = $kode ? TahunAkademik::byKode((string) $kode) : TahunAkademik::aktif();

        if ($term === null) {
            $this->error('Semester tidak ditemukan. Tentukan dengan --term=20261.');

            return self::FAILURE;
        }

        $entities = $this->argument('entity') === 'semua'
            ? array_keys(config('feeder.entities'))
            : [$this->argument('entity')];

        $baris = [];
        $adaGagal = false;

        foreach ($entities as $entity) {
            try {
                $hasil = $sync->hapusYangTerhapus($entity, $term);
            } catch (FeederException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $baris[] = [
                $entity,
                $hasil['didukung'] ? $hasil['diperiksa'] : '—',
                $hasil['didukung'] ? $hasil['dihapus'] : '—',
                $hasil['didukung'] ? $hasil['gagal'] : '—',
                $hasil['didukung'] ? 'ok' : 'tanpa aksi hapus',
            ];

            $adaGagal = $adaGagal || $hasil['gagal'] > 0;
        }

        $this->table(['Entitas', 'Diperiksa', 'Dihapus', 'Gagal', 'Keterangan'], $baris);
        $this->line('"Diperiksa" adalah baris yang pernah berhasil terkirim; yang masih ada di sini tidak disentuh.');

        return $adaGagal ? self::FAILURE : self::SUCCESS;
    }
}
