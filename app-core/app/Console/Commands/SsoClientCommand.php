<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTOs\Sso\KlienSsoData;
use App\Exceptions\AturanAkademikException;
use App\Models\Sso\KlienOAuth;
use App\Services\Auth\PencabutanTokenSso;
use App\Services\Sso\KlienSsoService;
use Illuminate\Console\Command;

/**
 * Registers an application that may sign people in with a campus account.
 *
 * Passport ships `passport:client`, but it asks a generic set of questions and
 * says nothing about what a campus is actually agreeing to. This one refuses
 * plain-HTTP redirects outside local development, warns when a client is being
 * granted the right to skip the consent screen, and prints the secret exactly
 * once with a plain statement of what that means.
 *
 * Registration goes through KlienSsoService, the same path as the SSO client
 * screen (/admin/sso-klien), so a client made here has the campus's record
 * beside it and the same redirect rules.
 */
class SsoClientCommand extends Command
{
    protected $signature = 'openacademic:sso-client
        {nama? : Nama aplikasi yang akan dibaca mahasiswa pada layar persetujuan; tidak dipakai bersama --daftar atau --cabut}
        {--redirect=* : URI callback (boleh diulang)}
        {--daftar : Tampilkan daftar aplikasi terdaftar}
        {--cabut= : Cabut seluruh token milik satu client (isi dengan client id)}';

    protected $description = 'Kelola aplikasi yang boleh memakai SSO Open Academic';

    public function handle(KlienSsoService $layanan): int
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

        return $this->buat($layanan);
    }

    private function buat(KlienSsoService $layanan): int
    {
        $nama = (string) $this->argument('nama');

        if ($nama === '') {
            $this->components->error('Nama aplikasi wajib diisi untuk mendaftarkan klien baru.');

            return self::FAILURE;
        }

        $redirects = (array) $this->option('redirect');

        if ($redirects === []) {
            $this->components->error('Minimal satu --redirect wajib diisi.');

            return self::FAILURE;
        }

        try {
            ['klien' => $client, 'rahasia' => $rahasia] = $layanan->daftarkan(new KlienSsoData($nama, $redirects));
        } catch (AturanAkademikException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Aplikasi terdaftar.');

        $this->table(['Kolom', 'Nilai'], [
            ['Client ID', $client->getKey()],
            ['Nama', $client->name],
            ['Redirect', implode(', ', $redirects)],
        ]);

        $this->newLine();
        $this->components->warn('Client secret hanya ditampilkan sekali berikut ini:');
        $this->line('  '.$rahasia);
        $this->newLine();

        $this->components->bulletList([
            'Simpan secret di .env aplikasi konsumen, bukan di dalam kode.',
            'Kehilangan secret berarti menerbitkan ulang client — tidak ada cara memulihkannya.',
            'Agar aplikasi ini melewati layar persetujuan, tandai sebagai pihak pertama di layar Klien SSO '
                .'atau tambahkan client id ke SSO_FIRST_PARTY. Lakukan hanya untuk aplikasi yang dijalankan kampus sendiri.',
        ]);

        return self::SUCCESS;
    }

    private function tampilkanDaftar(): int
    {
        $clients = KlienOAuth::query()
            ->with('pengaturan')
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
                $c->pihakPertama() ? 'ya' : '—',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function cabutToken(string $clientId): int
    {
        $jumlah = app(PencabutanTokenSso::class)->cabut($clientId);

        $this->components->info("{$jumlah} token dicabut untuk client {$clientId}.");
        $this->line('  Pengguna akan diminta menyetujui ulang saat berikutnya memakai aplikasi tersebut.');

        return self::SUCCESS;
    }
}
