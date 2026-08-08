<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederValidationIssue;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use Illuminate\Console\Command;

class FeederValidateCommand extends Command
{
    protected $signature = 'openacademic:feeder-validate
        {entity=semua : Entitas yang diperiksa}
        {--term= : Kode semester PDDIKTI; bawaan semester aktif}';

    protected $description = 'Memeriksa data terhadap aturan PDDIKTI tanpa mengirim apa pun';

    public function handle(FeederValidator $validator): int
    {
        $kode = $this->option('term');
        $term = $kode ? TahunAkademik::byKode((string) $kode) : TahunAkademik::aktif();

        if ($term === null) {
            $this->error('Semester tidak ditemukan. Tentukan dengan --term=20261.');

            return self::FAILURE;
        }

        $entities = $this->argument('entity') === 'semua'
            ? array_keys(FeederSyncService::MAPPERS)
            : [$this->argument('entity')];

        $ringkas = [];
        $totalError = 0;

        foreach ($entities as $entity) {
            $mapper = app(FeederSyncService::MAPPERS[$entity] ?? throw new \InvalidArgumentException(
                "Entitas {$entity} tidak dikenal.",
            ));

            $hasil = $validator->periksa($entity, $term->kode, $mapper);
            $totalError += $hasil['error'];

            $ringkas[] = [$entity, $hasil['diperiksa'], $hasil['error'], $hasil['warning']];

            $this->tampilkanTemuan($hasil['batch']);
        }

        $this->newLine();
        $this->table(['Entitas', 'Diperiksa', 'Error', 'Peringatan'], $ringkas);

        if ($totalError === 0) {
            $this->info('Seluruh baris lolos aturan PDDIKTI. Sinkronisasi dapat dijalankan.');

            return self::SUCCESS;
        }

        $this->error("{$totalError} baris akan ditolak Feeder. Perbaiki terlebih dahulu.");

        return self::FAILURE;
    }

    /** Shows the worst offenders rather than dumping every row. */
    private function tampilkanTemuan(string $batch): void
    {
        $temuan = FeederValidationIssue::batch($batch)->error()->limit(15)->get();

        if ($temuan->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('<fg=red>Temuan:</>');

        foreach ($temuan as $isu) {
            $this->line(sprintf('  · %-28s %s', $isu->local_label, $isu->message));
        }

        $sisa = FeederValidationIssue::batch($batch)->error()->count() - $temuan->count();

        if ($sisa > 0) {
            $this->line("  … dan {$sisa} baris lain. Lihat layar Neo Feeder Sync untuk daftar lengkap.");
        }
    }
}
