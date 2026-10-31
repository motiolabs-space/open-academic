<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTOs\Feeder\LaporanKamusEntitas;
use App\DTOs\Feeder\TemuanKamus;
use App\Enums\JenisTemuanKamus;
use App\Exceptions\FeederException;
use App\Services\Feeder\PemeriksaKamusFeeder;
use Illuminate\Console\Command;

/**
 * Reads the campus Feeder's web-service dictionary and reports where the
 * mappers disagree with it.
 *
 * Meant to be run on the campus server, against its own Neo Feeder, after
 * every Feeder upgrade. It changes no mapping and no config: what it prints
 * is for a person to act on. The reading is stored, so the console screen
 * shows the same report afterwards.
 */
class FeederKamusCommand extends Command
{
    protected $signature = 'openacademic:feeder-kamus
        {--act=* : Hanya aksi ini (mis. InsertBiodataMahasiswa); boleh diulang}
        {--versi= : Versi Neo Feeder yang terpasang, bila Feeder tidak melaporkannya sendiri}';

    protected $description = 'Membaca kamus web service Neo Feeder (GetDictionary) dan membandingkannya dengan medan yang dikirim setiap mapper';

    public function handle(PemeriksaKamusFeeder $pemeriksa): int
    {
        try {
            $run = $pemeriksa->periksa(null, $this->option('versi') ?: null, $this->option('act') ?: null);
        } catch (FeederException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $laporan = $pemeriksa->laporan($run);

        $this->info(__('feeder.kamus.perintah.ringkasan', [
            'aksi' => $run->jumlah_aksi,
            'gagal' => $run->jumlah_gagal,
            'versi' => $run->versi_feeder ?? __('feeder.kamus.versi_tidak_diketahui'),
        ]));

        $baris = $laporan->flatMap(fn (LaporanKamusEntitas $l): array => $l->cocok()
            ? [[$l->entity, $l->act, __('feeder.kamus.cocok'), '', '']]
            : array_map(fn (TemuanKamus $t): array => [
                $l->entity,
                $l->act,
                $t->jenis->label(),
                $t->kita ?? '',
                $t->feeder ?? ($t->jenis === JenisTemuanKamus::AksiTidakTerbaca ? (string) $t->keterangan : ''),
            ], $l->temuan));

        $this->table(['Entitas', 'Aksi', 'Temuan', 'Dikirim', 'Di kamus'], $baris->all());

        foreach ($pemeriksa->saranConfig($run) as $saran) {
            if ($saran->barisEnv() !== null) {
                $this->line(__('feeder.kamus.perintah.saran', ['baris' => $saran->barisEnv()]));
            }
        }

        $selisih = $laporan->reject(fn (LaporanKamusEntitas $l): bool => $l->cocok())->count();

        if ($selisih > 0) {
            $this->warn(__('feeder.kamus.perintah.ada_selisih', ['jumlah' => $selisih]));
        }

        // Disagreement is a finding, not a failure of the command: exit 0 so
        // a scheduled run does not page anyone for a report.
        return $run->jumlah_gagal === $run->jumlah_aksi && $run->jumlah_aksi > 0 ? self::FAILURE : self::SUCCESS;
    }
}
