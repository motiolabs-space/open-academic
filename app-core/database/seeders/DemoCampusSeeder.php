<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\System\Setting;
use App\Support\Demo;
use Database\Seeders\Demo\AkuntansiSeeder;
use Database\Seeders\Demo\BeasiswaSeeder;
use Database\Seeders\Demo\EdomSeeder;
use Database\Seeders\Demo\IntegrasiSeeder;
use Database\Seeders\Demo\KelulusanSeeder;
use Database\Seeders\Demo\KemahasiswaanSeeder;
use Database\Seeders\Demo\KepegawaianSeeder;
use Database\Seeders\Demo\KeuanganSeeder;
use Database\Seeders\Demo\KonversiSeeder;
use Database\Seeders\Demo\KurikulumLanjutanSeeder;
use Database\Seeders\Demo\MasterAkademikSeeder;
use Database\Seeders\Demo\MutuSeeder;
use Database\Seeders\Demo\NotifikasiSeeder;
use Database\Seeders\Demo\PerkuliahanSeeder;
use Database\Seeders\Demo\PmbSeeder;
use Database\Seeders\Demo\RiwayatAkademikSeeder;
use Database\Seeders\Demo\RpsSeeder;
use Database\Seeders\Demo\SdmSeeder;
use Database\Seeders\Demo\SuratSeeder;
use Database\Seeders\Demo\TugasAkhirSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A complete demo campus: one faculty, two programmes, one curriculum,
 * ~22 courses each, 7 lecturers, 50 students, three terms with grades, money,
 * admissions, and the integration baseline.
 *
 * The point is that a fresh clone can demonstrate the whole semester lifecycle
 * without anyone typing data first — and that an Open Campus dev instance has
 * something real to consume over Campus Bridge.
 *
 * Every account uses the password "password". That is why this seeder refuses
 * to run when the application environment is production.
 */
class DemoCampusSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'DemoCampusSeeder creates accounts with a known password and must never run in production.',
            );
        }

        // Fixture data generates tens of thousands of audit dispatches that
        // nobody will ever read. Discard them for the duration of the seed.
        $queue = config('queue.default');
        config(['queue.default' => 'null']);

        // Strict mode exists to catch N+1s on request paths, where the same
        // query runs once per visitor per page load. A seeder is neither: it
        // runs once, offline, and walks fixtures it just created. Enforcing it
        // here would only push eager loads into throwaway code.
        $ketat = Model::preventsLazyLoading();
        Model::preventLazyLoading(false);

        $this->seedBranding();

        $this->call([
            MasterAkademikSeeder::class,
            SdmSeeder::class,
            KemahasiswaanSeeder::class,
            PerkuliahanSeeder::class,
            RiwayatAkademikSeeder::class,

            // Sesudah RiwayatAkademikSeeder: gerbang konsentrasi berlaku saat
            // kelas ditambahkan ke KRS, jadi menetapkan jalur mahasiswa lebih
            // dulu akan mengubah riwayat yang sedang dibangun — bukan
            // menggambarkan kampus, tapi mengarangnya ulang.
            KurikulumLanjutanSeeder::class,

            KeuanganSeeder::class,

            // Sesudah KeuanganSeeder: beasiswa mendarat pada tagihan yang sudah
            // terbit, dan itu justru alur yang paling sering terjadi — seleksi
            // selesai berminggu-minggu setelah penagihan.
            BeasiswaSeeder::class,

            // Sesudah RiwayatAkademikSeeder: konversi ditolak untuk mata kuliah
            // yang sudah ditempuh, jadi calonnya dipilih dari yang belum bernilai.
            KonversiSeeder::class,

            // Sebelum KelulusanSeeder: lulusan perlu tugas akhir untuk
            // diluluskan *dari*. Urutan terbalik menghasilkan kampus demo yang
            // melanggar aturannya sendiri — ijazah dengan judul yang tidak
            // tertelusur ke mana pun.
            TugasAkhirSeeder::class,

            KelulusanSeeder::class,

            // Sesudah KelulusanSeeder: SKPI hanya dapat diterbitkan untuk
            // kelulusan yang sudah ditetapkan.
            SuratSeeder::class,

            // Sesudah RiwayatAkademikSeeder: hanya mahasiswa dengan KRS
            // disetujui yang punya kelas untuk dinilai.
            EdomSeeder::class,

            // Sesudah SuratSeeder (yang membuat prodi_cpl) dan sesudah nilai
            // terisi: penguasaan CPL dihitung dari komponen yang sudah bernilai,
            // dan tanpa CPL tidak ada yang dapat dipetakan.
            RpsSeeder::class,

            // Sesudah TugasAkhirSeeder: unsur pendidikan pada BKD ditarik dari
            // kelas, bimbingan, dan pengujian — melapor sebelum semua itu ada
            // akan membekukan laporan yang isinya cuma mengajar.
            KepegawaianSeeder::class,

            PmbSeeder::class,
            IntegrasiSeeder::class,

            // Sesudah SdmSeeder dan RiwayatAkademikSeeder: pohon unit kerja
            // menempatkan staf yang sudah ada, dan indikator kinerja yang
            // dihitung menyusuri mahasiswa serta nilai yang sudah terisi.
            // Menjalankannya lebih awal menghasilkan rencana yang realisasinya
            // nol — angka yang tidak menggambarkan apa pun.
            MutuSeeder::class,

            // Sesudah KeuanganSeeder dan BeasiswaSeeder: outbox akuntansi
            // menyusuri tagihan dan pembayaran yang sudah ada, jadi keduanya
            // harus lengkap lebih dulu — termasuk potongannya.
            AkuntansiSeeder::class,

            // Terakhir: menulis notifikasi langsung ke tabel, karena antrean
            // sedang diarahkan ke driver null di sini dan pengiriman sungguhan
            // akan dibuang tanpa jejak.
            NotifikasiSeeder::class,
        ]);

        Model::preventLazyLoading($ketat);
        config(['queue.default' => $queue]);

        /*
         * Marks this database as holding demo data.
         *
         * Written here rather than in the install command so it covers every
         * path that seeds — including `migrate:fresh --seed`. Its only reader is
         * openacademic:demo-hapus, which refuses to wipe a database that was
         * never seeded by us.
         */
        Demo::tandai();

        $this->ringkasan();
    }

    private function seedBranding(): void
    {
        Setting::put('branding', 'institution_name', 'Universitas Nusantara Digital');
        Setting::put('branding', 'institution_short', 'UND');
        Setting::put('branding', 'institution_code', '001001');
        Setting::put('branding', 'primary_color', '#1E2761');
        Setting::put('branding', 'accent_color', '#C9A961');
    }

    private function ringkasan(): void
    {
        $this->command?->newLine();
        $this->command?->info('Demo campus siap. Kata sandi semua akun: password');
        $this->command?->table(
            ['Peran', 'Akun'],
            [
                ['Super Admin', 'admin@demo.test'],
                ['BAAK', 'baak@demo.test'],
                ['Keuangan', 'keuangan@demo.test'],
                ['Operator PDDIKTI', 'pddikti@demo.test'],
                ['Dosen (wali)', 'dosen1@demo.test'],
                ['Dosen praktisi', 'praktisi@demo.test'],
                ['Mahasiswa', 'mahasiswa1@demo.test'],
            ],
        );
    }
}
