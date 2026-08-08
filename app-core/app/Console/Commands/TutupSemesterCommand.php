<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Services\Akademik\PenutupanSemesterService;
use Illuminate\Console\Command;

/**
 * Closes a semester from the command line.
 *
 * The screen covers the ordinary case; this exists for the campus running the
 * close for eight thousand students, where a web request would time out long
 * before it finished.
 */
class TutupSemesterCommand extends Command
{
    protected $signature = 'openacademic:tutup-semester
        {kode? : Kode PDDIKTI semester, misalnya 20261. Bawaannya semester aktif}
        {--periksa : Hanya tampilkan pratinjau, tidak membekukan apa pun}
        {--staff= : NIP atau surel staf yang bertanggung jawab}';

    protected $description = 'Membekukan catatan semester (IPS/IPK) agar batas SKS berikutnya terhitung';

    public function handle(PenutupanSemesterService $penutupan): int
    {
        $term = $this->argument('kode') === null
            ? TahunAkademik::aktif()
            : TahunAkademik::byKode((string) $this->argument('kode'));

        if ($term === null) {
            $this->error('Semester tidak ditemukan. Sebutkan kodenya, misalnya 20261.');

            return self::FAILURE;
        }

        $this->info("Semester: {$term->nama} ({$term->kode})");

        $pratinjau = $penutupan->pratinjau($term);

        $this->table(['Keadaan', 'Jumlah'], [
            ['Siap dibekukan', $pratinjau['siap']->count()],
            ['Terhalang nilai belum final', $pratinjau['terhalang']->count()],
            ['Sudah beku sebelumnya', $pratinjau['sudah_final']],
            ['Kelas belum difinalisasi', $pratinjau['kelas_belum_final']->count()],
        ]);

        if ($pratinjau['kelas_belum_final']->isNotEmpty()) {
            $this->newLine();
            $this->warn('Kelas yang nilainya belum difinalisasi dosen:');

            foreach ($pratinjau['kelas_belum_final']->take(15) as $kelas) {
                $this->line("  · {$kelas->mataKuliah->kode} kelas {$kelas->kode}");
            }

            if ($pratinjau['kelas_belum_final']->count() > 15) {
                $this->line('  · dan '.($pratinjau['kelas_belum_final']->count() - 15).' lainnya');
            }
        }

        if ($this->option('periksa')) {
            $this->newLine();
            $this->comment('Mode periksa — tidak ada yang dibekukan.');

            return self::SUCCESS;
        }

        if ($pratinjau['siap']->isEmpty()) {
            $this->newLine();
            $this->comment('Tidak ada catatan yang siap dibekukan.');

            return self::SUCCESS;
        }

        $staff = $this->staf();

        if ($staff === null) {
            $this->error('Staf penanggung jawab tidak ditemukan. Sebutkan dengan --staff=NIP atau --staff=surel.');

            return self::FAILURE;
        }

        // Freezing changes what every student may take next semester, so it is
        // confirmed rather than assumed — even from a terminal.
        if (!$this->option('no-interaction') && !$this->confirm(
            sprintf('Bekukan %d catatan semester atas nama %s?', $pratinjau['siap']->count(), $staff->nama),
        )) {
            return self::SUCCESS;
        }

        $hasil = $penutupan->tutup($term, $staff);

        $this->newLine();
        $this->info(sprintf(
            '%d dibekukan · %d terhalang · %d dilewati.',
            $hasil['dibekukan'],
            $hasil['terhalang'],
            $hasil['dilewati'],
        ));

        return self::SUCCESS;
    }

    private function staf(): ?Staff
    {
        $kunci = $this->option('staff');

        if ($kunci === null) {
            // A single-administrator installation should not have to type it.
            return Staff::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
                ->first();
        }

        return Staff::where('nip', $kunci)->orWhere('email', $kunci)->first();
    }
}
