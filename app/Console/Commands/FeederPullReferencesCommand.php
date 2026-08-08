<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\FeederException;
use App\Services\Feeder\FeederSyncService;
use Illuminate\Console\Command;

class FeederPullReferencesCommand extends Command
{
    protected $signature = 'openacademic:feeder-refs';

    protected $description = 'Menarik tabel referensi Neo Feeder (agama, wilayah, jenjang, kode status)';

    public function handle(FeederSyncService $sync): int
    {
        try {
            $hasil = $sync->tarikReferensi();
        } catch (FeederException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Referensi', 'Baris'],
            collect($hasil)->map(fn (int $jumlah, string $tipe): array => [$tipe, $jumlah])->values()->all(),
        );

        $this->line('Referensi adalah milik Feeder. Jalankan ini sebelum sinkronisasi pertama '
            .'dan setiap kali Feeder diperbarui.');

        return self::SUCCESS;
    }
}
