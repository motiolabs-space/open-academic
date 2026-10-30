<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\AturanAkademikException;
use App\Models\Bridge\BridgeConsumer;
use App\Services\Bridge\KonsumenBridgeService;
use Illuminate\Console\Command;

/**
 * The command-line twin of the consumer screen (/admin/bridge/konsumen/{uuid}).
 *
 * Both go through KonsumenBridgeService, so a token issued here is audited the
 * same way as one issued from the screen, and refused the same way for an
 * inactive consumer.
 */
class BridgeTokenCommand extends Command
{
    protected $signature = 'openacademic:bridge-token
        {slug : Slug aplikasi konsumen, mis. open-campus}
        {--nama=default : Nama token, untuk membedakan beberapa token milik satu aplikasi}
        {--cabut : Cabut seluruh token aplikasi ini alih-alih menerbitkan yang baru}';

    protected $description = 'Menerbitkan atau mencabut token Campus Bridge untuk sebuah aplikasi konsumen';

    public function handle(KonsumenBridgeService $layanan): int
    {
        $consumer = BridgeConsumer::where('slug', $this->argument('slug'))->first();

        if ($consumer === null) {
            $this->error("Aplikasi konsumen dengan slug \"{$this->argument('slug')}\" tidak ditemukan.");

            return self::FAILURE;
        }

        if ($this->option('cabut')) {
            $jumlah = $layanan->cabutSemuaToken($consumer);
            $this->info("{$jumlah} token {$consumer->nama} dicabut.");

            return self::SUCCESS;
        }

        // Token abilities mirror the consumer's scopes. The scope list on the
        // consumer stays authoritative, so narrowing access later does not
        // require reissuing anything.
        try {
            $token = $layanan->terbitkanToken($consumer, (string) $this->option('nama'));
        } catch (AturanAkademikException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Token untuk {$consumer->nama}:");
        $this->line('  <fg=yellow>'.$token->plainTextToken.'</>');
        $this->newLine();
        $this->line('Scope: '.implode(', ', $consumer->scopes ?? []));
        $this->newLine();
        $this->warn('Token ini hanya ditampilkan sekali. Simpan sekarang; kami tidak menyimpan salinannya.');

        return self::SUCCESS;
    }
}
