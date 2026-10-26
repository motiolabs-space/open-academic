<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sdm\RiwayatKepegawaianService;
use Illuminate\Console\Command;

/**
 * Closes the accounts of lecturers whose approved retirement has arrived.
 *
 * Retirements are usually recorded weeks before their TMT, so approval alone
 * cannot close the account. This runs daily and closes what is due, through
 * the same refusal as the manual button: a retiree still teaching this term's
 * class, or still advising a cohort, is reported and left open until the
 * hand-over is done. Idempotent — a closed account is not matched again.
 */
class TutupAkunPensiunCommand extends Command
{
    protected $signature = 'openacademic:tutup-akun-pensiun';

    protected $description = 'Menutup akun dosen yang TMT pensiunnya (sudah disetujui) telah tiba.';

    public function __construct(private readonly RiwayatKepegawaianService $riwayat)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $hasil = $this->riwayat->tutupAkunPensiun();

        foreach ($hasil['ditutup'] as $dosen) {
            $this->info(__('kepegawaian.pesan.akun_ditutup', ['nama' => $dosen->nama]));
        }

        foreach ($hasil['tertahan'] as $tertahan) {
            $this->warn(__('kepegawaian.pesan.akun_tertahan', [
                'nama' => $tertahan['dosen']->nama,
                'halangan' => implode('; ', $tertahan['halangan']),
            ]));
        }

        $this->line(sprintf('%d ditutup, %d tertahan.', $hasil['ditutup']->count(), $hasil['tertahan']->count()));

        return self::SUCCESS;
    }
}
