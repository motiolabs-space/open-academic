<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * Registers an application that may sign people in with a campus account.
 *
 * Passport ships `passport:client`, but it asks a generic set of questions and
 * says nothing about what a campus is actually agreeing to. This one refuses
 * plain-HTTP redirects outside local development, warns when a client is being
 * granted the right to skip the consent screen, and prints the secret exactly
 * once with a plain statement of what that means.
 */
class SsoClientCommand extends Command
{
    protected $signature = 'openacademic:sso-client
        {nama : Nama aplikasi yang akan dibaca mahasiswa pada layar persetujuan}
        {--redirect=* : URI callback (boleh diulang)}
        {--daftar : Tampilkan daftar aplikasi terdaftar}
        {--cabut= : Cabut seluruh token milik satu client (isi dengan client id)}';

    protected $description = 'Kelola aplikasi yang boleh memakai SSO Open Academic';

    public function handle(ClientRepository $clients): int
    {
        if (!config('sso.enabled')) {
            $this->components->error('SSO nonaktif. Setel SSO_ENABLED=true lebih dulu.');

            return self::FAILURE;
        }

        if ($this->option('daftar')) {
            return $this->tampilkanDaftar();
        }

        if ($cabut = $this->option('cabut')) {
            return $this->cabutToken($cabut);
        }

        return $this->buat($clients);
    }

    private function buat(ClientRepository $clients): int
    {
        $redirects = (array) $this->option('redirect');

        if ($redirects === []) {
            $this->components->error('Minimal satu --redirect wajib diisi.');

            return self::FAILURE;
        }

        foreach ($redirects as $uri) {
            if (!$this->redirectSah($uri)) {
                return self::FAILURE;
            }
        }

        $client = $clients->createAuthorizationCodeGrantClient(
            name: $this->argument('nama'),
            redirectUris: $redirects,
            confidential: true,
        );

        $this->components->info('Aplikasi terdaftar.');

        $this->table(['Kolom', 'Nilai'], [
            ['Client ID', $client->getKey()],
            ['Nama', $client->name],
            ['Redirect', implode(', ', $redirects)],
        ]);

        $this->newLine();
        $this->components->warn('Client secret hanya ditampilkan sekali berikut ini:');
        $this->line('  '.$client->plainSecret);
        $this->newLine();

        $this->components->bulletList([
            'Simpan secret di .env aplikasi konsumen, bukan di dalam kode.',
            'Kehilangan secret berarti menerbitkan ulang client — tidak ada cara memulihkannya.',
            'Agar aplikasi ini melewati layar persetujuan, tambahkan client id ke SSO_FIRST_PARTY. '
                .'Lakukan hanya untuk aplikasi yang dijalankan kampus sendiri.',
        ]);

        return self::SUCCESS;
    }

    private function redirectSah(string $uri): bool
    {
        if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
            $this->components->error("Redirect tidak valid: {$uri}");

            return false;
        }

        $host = parse_url($uri, PHP_URL_HOST);
        $skema = parse_url($uri, PHP_URL_SCHEME);
        $lokal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        // An authorization code travels back on this URI. Over plain HTTP
        // anyone on the path can read it and redeem it before the real
        // consumer does — which hands them the account, not just the code.
        if ($skema !== 'https' && !$lokal) {
            $this->components->error("Redirect non-HTTPS ditolak: {$uri}");
            $this->line('  Kode otorisasi dikirim lewat URI ini; di HTTP polos ia dapat dicuri dan ditukar duluan.');

            return false;
        }

        return true;
    }

    private function tampilkanDaftar(): int
    {
        $clients = Passport::clientModel()::query()
            ->orderBy('name')
            ->get();

        if ($clients->isEmpty()) {
            $this->components->warn('Belum ada aplikasi terdaftar.');

            return self::SUCCESS;
        }

        $this->table(
            ['Client ID', 'Nama', 'Dicabut', 'Lewati Persetujuan'],
            $clients->map(fn ($c): array => [
                $c->getKey(),
                $c->name,
                $c->revoked ? 'ya' : '—',
                in_array((string) $c->getKey(), (array) config('sso.first_party'), true) ? 'ya' : '—',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function cabutToken(string $clientId): int
    {
        $jumlah = Passport::tokenModel()::query()
            ->where('client_id', $clientId)
            ->where('revoked', false)
            ->update(['revoked' => true]);

        $this->components->info("{$jumlah} token dicabut untuk client {$clientId}.");
        $this->line('  Pengguna akan diminta menyetujui ulang saat berikutnya memakai aplikasi tersebut.');

        return self::SUCCESS;
    }
}
