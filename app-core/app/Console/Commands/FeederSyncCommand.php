<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\FeederException;
use App\Models\Akademik\TahunAkademik;
use App\Services\Feeder\FeederSyncService;
use Illuminate\Console\Command;

class FeederSyncCommand extends Command
{
    protected $signature = 'openacademic:feeder-sync
        {entity : Entitas yang disinkronkan, atau "semua" untuk seluruhnya sesuai urutan dependensi}
        {--term= : Kode semester PDDIKTI (mis. 20261); bawaan semester aktif}
        {--lewati-validasi : Kirim tanpa validasi pra-kirim — gunakan hanya bila Anda tahu konsekuensinya}';

    protected $description = 'Mendorong data akademik ke Neo Feeder PDDIKTI (idempotent, aman diulang)';

    public function handle(FeederSyncService $sync): int
    {
        $term = $this->term();

        if ($term === null) {
            return self::FAILURE;
        }

        $entities = $this->argument('entity') === 'semua'
            ? array_keys(config('feeder.entities'))
            : [$this->argument('entity')];

        $this->info("Semester {$term->nama} ({$term->kode}) · driver ".config('feeder.driver'));
        $this->newLine();

        $baris = [];
        $adaGagal = false;

        foreach ($entities as $entity) {
            try {
                $hasil = $sync->sinkronkan($entity, $term, (bool) $this->option('lewati-validasi'));

                $baris[] = [
                    $entity,
                    $hasil['terkirim'],
                    $hasil['dilewati'],
                    $hasil['gagal'],
                    $hasil['gagal'] > 0 ? 'sebagian' : 'ok',
                ];

                $adaGagal = $adaGagal || $hasil['gagal'] > 0;
            } catch (FeederException $e) {
                $baris[] = [$entity, 0, 0, 0, 'dibatalkan'];
                $this->newLine();
                $this->error($e->getMessage());
                $adaGagal = true;

                // Later entities depend on this one; continuing would only
                // produce a cascade of dependency errors.
                break;
            }
        }

        $this->newLine();
        $this->table(['Entitas', 'Terkirim', 'Dilewati', 'Gagal', 'Status'], $baris);

        $this->line('Baris "dilewati" berarti payload-nya tidak berubah sejak sinkronisasi terakhir yang berhasil.');

        return $adaGagal ? self::FAILURE : self::SUCCESS;
    }

    private function term(): ?TahunAkademik
    {
        $kode = $this->option('term');

        $term = $kode ? TahunAkademik::byKode((string) $kode) : TahunAkademik::aktif();

        if ($term === null) {
            $this->error($kode
                ? "Semester dengan kode {$kode} tidak ditemukan."
                : 'Tidak ada semester aktif. Tentukan dengan --term=20261.');
        }

        return $term;
    }
}
