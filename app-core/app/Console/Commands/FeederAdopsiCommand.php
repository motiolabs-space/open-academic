<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\FeederException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Services\Feeder\FeederAdopsi;
use Illuminate\Console\Command;

/**
 * Adoption from the command line, for a campus migrating many intakes at once.
 *
 * Without --periode every intake term the programme's local students carry
 * is adopted in turn. Ambiguous rows are only reported here; deciding them
 * needs a reason and a person, which is what the console screen is for.
 */
class FeederAdopsiCommand extends Command
{
    protected $signature = 'openacademic:feeder-adopsi
        {prodi : Kode prodi lokal, atau "semua"}
        {--periode= : Kode periode masuk PDDIKTI (mis. 20241); bawaan seluruh periode masuk yang dimiliki mahasiswa prodi itu}';

    protected $description = 'Mengadopsi mahasiswa yang sudah ada di PDDIKTI: mencocokkan NIM/NIK dan mencatat id Feeder tanpa mengirim apa pun';

    public function handle(FeederAdopsi $adopsi): int
    {
        $term = TahunAkademik::aktif();

        if ($term === null) {
            $this->error('Tidak ada semester aktif; baris buku besar adopsi dicatat pada semester aktif.');

            return self::FAILURE;
        }

        $daftarProdi = $this->argument('prodi') === 'semua'
            ? Prodi::query()->whereNotNull('kode_pddikti')->orderBy('kode')->get()
            : Prodi::query()->where('kode', $this->argument('prodi'))->get();

        if ($daftarProdi->isEmpty()) {
            $this->error("Program studi {$this->argument('prodi')} tidak ditemukan.");

            return self::FAILURE;
        }

        $baris = [];
        $adaAmbigu = false;

        foreach ($daftarProdi as $prodi) {
            $periodeList = $this->option('periode')
                ? [(string) $this->option('periode')]
                : $prodi->mahasiswa()->whereNotNull('term_masuk')->distinct()->orderBy('term_masuk')->pluck('term_masuk')->all();

            foreach ($periodeList as $periode) {
                try {
                    $hasil = $adopsi->adopsi($prodi, (string) $periode, $term);
                } catch (FeederException $e) {
                    $this->error("{$prodi->nama} {$periode}: {$e->getMessage()}");

                    return self::FAILURE;
                }

                $baris[] = [
                    $prodi->kode, $periode, $hasil['diadopsi'], $hasil['sudah_tertaut'],
                    $hasil['hanya_lokal'], $hasil['hanya_feeder'], $hasil['ambigu'],
                ];
                $adaAmbigu = $adaAmbigu || $hasil['ambigu'] > 0;
            }
        }

        $this->table(['Prodi', 'Periode', 'Diadopsi', 'Sudah tertaut', 'Hanya SIAKAD', 'Hanya Feeder', 'Ambigu'], $baris);

        if ($adaAmbigu) {
            $this->warn('Mahasiswa ambigu tidak diadopsi dan biodatanya ditolak validator sampai ditautkan atau dipisahkan di /admin/feeder/adopsi.');
        }

        return self::SUCCESS;
    }
}
