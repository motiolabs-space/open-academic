<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Akademik\KrsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Satu percobaan merebut kursi — dijalankan berkali-kali secara bersamaan.
 *
 * `KrsService::tambah()` mengunci baris kelas dengan `lockForUpdate()` di dalam
 * transaksi, lalu menolak bila `terisi >= kuota`. Suite tidak dapat membuktikan
 * itu bekerja: ia berjalan di SQLite in-memory, satu proses, tanpa penguncian
 * baris sungguhan. Yang membuktikannya hanya banyak proses berebut satu baris
 * di MySQL — persis keadaan jam pembukaan KRS.
 *
 * Perintah ini sengaja melakukan SATU percobaan lalu keluar, supaya pemanggilnya
 * (skrip shell) yang menentukan tingkat kebersamaannya. Keluarannya satu kata:
 * DAPAT, PENUH, atau GAGAL — supaya hasilnya dapat dihitung tanpa mengurai teks.
 *
 * Menyiapkan medannya dengan `--siapkan`: satu kelas berkuota N, dan
 * mahasiswa-mahasiswa dengan KRS terbuka.
 */
class BebanKuotaCommand extends Command
{
    protected $signature = 'openacademic:beban-kuota
        {--siapkan= : Siapkan satu kelas dengan kuota sebanyak ini, lalu keluar}
        {--mahasiswa= : Id mahasiswa yang mencoba merebut kursi}
        {--kelas= : Id kelas yang diperebutkan}';

    protected $description = 'Satu percobaan merebut kursi KRS, untuk uji beban bersamaan';

    public function handle(KrsService $krs): int
    {
        if (app()->isProduction()) {
            $this->error('Menolak berjalan di produksi.');

            return self::FAILURE;
        }

        if ($this->option('siapkan') !== null) {
            return $this->siapkan((int) $this->option('siapkan'));
        }

        $mahasiswa = Mahasiswa::query()->findOrFail((int) $this->option('mahasiswa'));
        $kelas = KelasKuliah::query()->findOrFail((int) $this->option('kelas'));
        $term = TahunAkademik::aktif();

        try {
            $rencana = $krs->bukaAtauAmbil($mahasiswa, $term);
            $krs->tambahKelas($rencana, $kelas);
            $this->line('DAPAT');
        } catch (AturanAkademikException $e) {
            // Kuota habis adalah hasil yang BENAR di sini, bukan kegagalan.
            // Penolakan lain (prasyarat, batas SKS, bentrok jadwal) dilaporkan
            // apa adanya supaya tidak terhitung sebagai kuota penuh.
            $this->line(str_contains($e->getMessage(), 'uota')
                ? 'PENUH'
                : 'TOLAK '.substr($e->getMessage(), 0, 60));
        } catch (Throwable $e) {
            $this->line('GAGAL '.substr($e->getMessage(), 0, 80));
        }

        return self::SUCCESS;
    }

    private function siapkan(int $kuota): int
    {
        $term = TahunAkademik::aktif();

        /*
         * Kelas dari mata kuliah karangan (kode BB…), bukan dari kurikulum demo.
         *
         * Percobaan pertama memakai kelas pertama di tabel, dan seluruh 20
         * prosesnya ditolak sebelum sempat berebut: mahasiswa demo sudah lulus
         * mata kuliah itu, atau KRS-nya sudah diajukan. Penolakan yang sah,
         * tapi bukan yang sedang diuji — dan tanpa membaca pesannya, nol
         * pemenang akan terbaca seperti penguncian yang bekerja.
         */
        $kelas = KelasKuliah::query()
            ->where('tahun_akademik_id', $term->id)
            ->whereHas('mataKuliah', fn ($q) => $q->whereLike('kode', 'BB%'))
            ->orderBy('id')
            ->firstOrFail();

        // Dikosongkan dan dipatok kuotanya, supaya jumlah pemenang yang
        // diharapkan sama persis dengan kuotanya — tidak ada yang perlu ditebak
        // saat membaca hasilnya.
        DB::table('kelas_kuliah')->where('id', $kelas->id)->update(['kuota' => $kuota, 'terisi' => 0]);
        DB::table('krs_detail')->where('kelas_kuliah_id', $kelas->id)->delete();

        // Hanya mahasiswa AKTIF yang kurikulumnya memuat kelas itu, dan KRS-nya
        // dikembalikan ke draf — yang berstatus diajukan/disetujui memang tidak
        // boleh menambah kelas, dan penolakannya akan menyamarkan hasilnya.
        $mahasiswa = Mahasiswa::query()
            // 'A' = aktif pada kode status PDDIKTI; 'L' lulus, 'C' cuti, 'D' keluar.
            ->where('status', 'A')
            ->where('kurikulum_id', $kelas->mataKuliah->kurikulum()->value('kurikulum.id'))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        DB::table('krs')
            ->where('tahun_akademik_id', $term->id)
            ->whereIn('mahasiswa_id', $mahasiswa)
            ->update(['status' => 'draft']);

        $this->line('kelas='.$kelas->id);
        $this->line('kuota='.$kuota);
        $this->line('mahasiswa='.implode(',', $mahasiswa));

        return self::SUCCESS;
    }
}
