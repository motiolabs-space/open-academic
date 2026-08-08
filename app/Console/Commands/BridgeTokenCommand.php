<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Bridge\BridgeConsumer;
use Illuminate\Console\Command;

class BridgeTokenCommand extends Command
{
    protected $signature = 'openacademic:bridge-token
        {slug : Slug aplikasi konsumen, mis. open-campus}
        {--nama=default : Nama token, untuk membedakan beberapa token milik satu aplikasi}
        {--cabut : Cabut seluruh token aplikasi ini alih-alih menerbitkan yang baru}';

    protected $description = 'Menerbitkan atau mencabut token Campus Bridge untuk sebuah aplikasi konsumen';

    public function handle(): int
    {
        $consumer = BridgeConsumer::where('slug', $this->argument('slug'))->first();

        if ($consumer === null) {
            $this->error("Aplikasi konsumen dengan slug \"{$this->argument('slug')}\" tidak ditemukan.");

            return self::FAILURE;
        }

        if ($this->option('cabut')) {
            $jumlah = $consumer->tokens()->delete();
            $this->info("{$jumlah} token {$consumer->nama} dicabut.");

            return self::SUCCESS;
        }

        // Token abilities mirror the consumer's scopes. The scope list on the
        // consumer stays authoritative, so narrowing access later does not
        // require reissuing anything.
        $token = $consumer->createToken((string) $this->option('nama'), $consumer->scopes ?? []);

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
