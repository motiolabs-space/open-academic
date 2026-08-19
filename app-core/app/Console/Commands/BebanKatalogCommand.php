<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menggelembungkan katalog kelas ke ukuran kampus sungguhan.
 *
 * `docs/KAPASITAS.md` mencatat dua hal sebagai belum terukur: katalog KRS pada
 * skala penuh, dan beban bersamaan saat masa KRS dibuka. Fixture untuk yang
 * pertama dulu dibuat sekali pakai lalu hilang bersama basis datanya — jadi
 * angkanya tidak pernah bisa diperiksa ulang oleh siapa pun. Perintah ini
 * menggantikannya.
 *
 * **Yang perlu dipahami sebelum membaca hasilnya:** katalog KRS SUDAH tersaring
 * per kurikulum, bukan per kampus. Seorang mahasiswa tidak pernah melihat 1.000
 * kelas se-kampus; ia melihat kelas dari kurikulumnya sendiri. Jadi yang
 * menentukan berat layarnya adalah **kelas per kurikulum**, dan kelas
 * se-kampus hanya menguji apakah penyaringnya tetap murah saat tabelnya besar.
 * Perintah ini menumbuhkan keduanya, terpisah, supaya keduanya dapat dibedakan.
 *
 * Menolak berjalan di produksi — ia menulis puluhan ribu baris karangan.
 */
class BebanKatalogCommand extends Command
{
    protected $signature = 'openacademic:beban-katalog
        {--mahasiswa=mahasiswa1@demo.test : Mahasiswa yang katalognya diukur — kurikulumnya yang ditumbuhkan}
        {--kurikulum=300 : Kelas yang terlihat oleh mahasiswa itu}
        {--kampus=1000 : Total kelas se-kampus pada term aktif}';

    protected $description = 'Menumbuhkan katalog kelas untuk uji beban KRS';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Menolak berjalan di produksi.');

            return self::FAILURE;
        }

        $term = TahunAkademik::aktif();

        if ($term === null) {
            $this->error('Tidak ada tahun akademik aktif.');

            return self::FAILURE;
        }

        /*
         * Kurikulum diambil dari MAHASISWA yang diukur, bukan `Kurikulum::first()`.
         *
         * Versi pertama perintah ini memakai kurikulum pertama di tabel, sementara
         * mahasiswa demo memakai kurikulum kedua — jadi seluruh mata kuliah yang
         * ditumbuhkan tidak pernah muncul di katalog yang sedang diukur, dan
         * angkanya diam di tempat sambil terlihat seperti hasil pengukuran.
         */
        $mahasiswa = Mahasiswa::query()->where('email', $this->option('mahasiswa'))->first();

        if ($mahasiswa === null) {
            $this->error(sprintf('Mahasiswa "%s" tidak ada.', $this->option('mahasiswa')));

            return self::FAILURE;
        }

        if ($mahasiswa->kurikulum_id === null) {
            $this->error('Mahasiswa itu belum punya kurikulum — katalognya jatuh ke penyaring prodi.');

            return self::FAILURE;
        }

        $kurikulum = Kurikulum::query()->find($mahasiswa->kurikulum_id);
        $prodi = $kurikulum?->prodi ?? Prodi::query()->orderBy('id')->first();
        $dosen = Dosen::query()->orderBy('id')->first();

        if ($kurikulum === null || $prodi === null || $dosen === null) {
            $this->error('Basis data belum ter-seed — jalankan migrate:fresh --seed dulu.');

            return self::FAILURE;
        }

        $targetKurikulum = (int) $this->option('kurikulum');
        $targetKampus = (int) $this->option('kampus');

        $this->info(sprintf('Term: %s · kurikulum: %s', $term->nama, $kurikulum->nama));

        $dibuat = $this->tumbuhkanKurikulum($term, $kurikulum, $prodi, $dosen, $targetKurikulum);
        $this->line(sprintf('  kelas terlihat oleh mahasiswa kurikulum ini : %d', $dibuat['terlihat']));

        $sisa = max(0, $targetKampus - $dibuat['terlihat']);
        $lain = $this->tumbuhkanKampus($term, $prodi, $dosen, $sisa);
        $this->line(sprintf('  kelas se-kampus di luar kurikulum itu      : %d', $lain));

        $total = DB::table('kelas_kuliah')->where('tahun_akademik_id', $term->id)->count();
        $this->newLine();
        $this->info(sprintf('Total kelas pada term aktif: %d', $total));

        return self::SUCCESS;
    }

    /**
     * Menambah mata kuliah ke kurikulum yang dipakai mahasiswa, lalu membuka
     * kelas paralel untuk masing-masing — inilah yang benar-benar memperberat
     * layar KRS.
     *
     * @return array{terlihat: int}
     */
    private function tumbuhkanKurikulum(
        TahunAkademik $term,
        Kurikulum $kurikulum,
        Prodi $prodi,
        Dosen $dosen,
        int $target,
    ): array {
        $terlihat = $this->hitungTerlihat($term, $kurikulum);

        if ($terlihat >= $target) {
            return ['terlihat' => $terlihat];
        }

        // Enam kelas paralel per mata kuliah — angka yang wajar di prodi besar,
        // dan membuat pertumbuhannya menyerupai kampus sungguhan alih-alih
        // seribu mata kuliah yang tidak pernah ada.
        $paralel = 6;
        $perluMk = (int) ceil(($target - $terlihat) / $paralel);

        $urut = MataKuliah::query()->max('id') ?? 0;
        $mkBaru = [];
        $now = now();

        for ($i = 1; $i <= $perluMk; $i++) {
            $mkBaru[] = [
                'uuid' => (string) Str::uuid(),
                'prodi_id' => $prodi->id,
                'kode' => sprintf('BB%d-%05d', $kurikulum->id, $urut + $i),
                'nama' => sprintf('Mata Kuliah Beban %d', $urut + $i),
                'sks' => 3,
                'sks_teori' => 3,
                'sks_praktik' => 0,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($mkBaru, 500) as $bagian) {
            DB::table('mata_kuliah')->insert($bagian);
        }

        /*
         * Hanya mata kuliah yang BELUM punya kelas pada term ini.
         *
         * Versi pertama mengambil seluruh mata kuliah berkode BB, termasuk dari
         * jalan sebelumnya, lalu membuat kelas berkode sama untuk semuanya —
         * kena batasan unik dan perintahnya gagal di tengah. Kegagalan itu
         * menyamar jadi "angka tidak berubah", yang terbaca persis seperti hasil
         * pengukuran yang mendatar.
         */
        $idBaru = DB::table('mata_kuliah')
            // `whereLike()` memilih operator sesuai driver. PortabilitasBasisDataTest
            // menolak operator mentahnya di seluruh app/, dan menangkap versi
            // pertama perintah ini. Penjaganya mencocokkan teks mentah, jadi
            // menyebut operatornya di komentar pun ikut tertangkap — sengaja
            // tumpul, dan lebih baik begitu daripada longgar.
            ->whereLike('kode', 'BB'.$kurikulum->id.'-%')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('kelas_kuliah')
                ->whereColumn('kelas_kuliah.mata_kuliah_id', 'mata_kuliah.id')
                ->where('kelas_kuliah.tahun_akademik_id', $term->id))
            ->pluck('id')
            ->all();

        // Dikaitkan ke kurikulum — tanpa ini katalog tidak akan melihatnya.
        $pivot = [];
        foreach ($idBaru as $mkId) {
            $pivot[] = [
                'kurikulum_id' => $kurikulum->id,
                'mata_kuliah_id' => $mkId,
                'semester' => 1,
                'jenis' => 'wajib',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($pivot, 500) as $bagian) {
            DB::table('kurikulum_mata_kuliah')->insertOrIgnore($bagian);
        }

        $this->buatKelas($term, $prodi, $dosen, $idBaru, $paralel);

        return ['terlihat' => $this->hitungTerlihat($term, $kurikulum)];
    }

    /**
     * Kelas di luar kurikulum tadi — tidak terlihat mahasiswa itu, tapi ikut
     * membesarkan tabel yang harus disaring penyaringnya.
     */
    private function tumbuhkanKampus(TahunAkademik $term, Prodi $prodi, Dosen $dosen, int $sisa): int
    {
        if ($sisa <= 0) {
            return 0;
        }

        // Sama seperti di atas: lewati yang sudah punya kelas pada term ini,
        // supaya perintahnya boleh dijalankan ulang tanpa menabrak kode kelas.
        $mk = MataKuliah::query()
            ->whereNotLike('kode', 'BB%')
            ->whereDoesntHave('kelasKuliah', fn ($q) => $q->where('tahun_akademik_id', $term->id))
            ->pluck('id')
            ->all();

        if ($mk === []) {
            return 0;
        }

        $paralel = (int) ceil($sisa / count($mk));

        return $this->buatKelas($term, $prodi, $dosen, $mk, $paralel, 'X');
    }

    /** @param array<int, int> $mataKuliahIds */
    private function buatKelas(
        TahunAkademik $term,
        Prodi $prodi,
        Dosen $dosen,
        array $mataKuliahIds,
        int $paralel,
        string $awalan = 'B',
    ): int {
        $now = now();
        $baris = [];

        foreach ($mataKuliahIds as $mkId) {
            for ($k = 1; $k <= $paralel; $k++) {
                $baris[] = [
                    'uuid' => (string) Str::uuid(),
                    'tahun_akademik_id' => $term->id,
                    'prodi_id' => $prodi->id,
                    'mata_kuliah_id' => $mkId,
                    'kode' => $awalan.$k,
                    'sks' => 3,
                    'mode' => 'tatap_muka',
                    'kuota' => 40,
                    'terisi' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($baris, 500) as $bagian) {
            DB::table('kelas_kuliah')->insert($bagian);
        }

        /*
         * Dosen pengampu ikut dipasang.
         *
         * Katalog meng-eager-load `dosenPengampu`, jadi kelas tanpa dosen
         * membuat pengukurannya terlalu optimis: relasi kosong tidak
         * menghidrasi apa pun. Kampus sungguhan tidak punya kelas tanpa
         * pengampu.
         */
        $uuidBaru = array_column($baris, 'uuid');
        $pengampu = [];

        foreach (array_chunk($uuidBaru, 1000) as $bagian) {
            $ids = DB::table('kelas_kuliah')->whereIn('uuid', $bagian)->pluck('id');

            foreach ($ids as $kelasId) {
                $pengampu[] = [
                    'kelas_kuliah_id' => $kelasId,
                    'dosen_id' => $dosen->id,
                    'peran' => 'pengampu',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($pengampu, 500) as $bagian) {
            DB::table('kelas_dosen')->insert($bagian);
        }

        return count($baris);
    }

    private function hitungTerlihat(TahunAkademik $term, Kurikulum $kurikulum): int
    {
        return DB::table('kelas_kuliah')
            ->join('kurikulum_mata_kuliah', 'kurikulum_mata_kuliah.mata_kuliah_id', '=', 'kelas_kuliah.mata_kuliah_id')
            ->where('kelas_kuliah.tahun_akademik_id', $term->id)
            ->where('kurikulum_mata_kuliah.kurikulum_id', $kurikulum->id)
            ->distinct()
            ->count('kelas_kuliah.id');
    }
}
