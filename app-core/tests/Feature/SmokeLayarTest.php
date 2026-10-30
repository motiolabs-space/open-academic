<?php

declare(strict_types=1);

use App\DTOs\Keuangan\DispensasiData;
use App\Enums\JenisDaftar;
use App\Enums\JenisPeristiwa;
use App\Enums\JenisUjianKelas;
use App\Enums\StatusPeristiwa;
use App\Models\Akademik\JadwalUjian;
use App\Models\Akademik\KalenderPeristiwa;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\DispensasiTagihan;
use App\Models\Keuangan\Tagihan;
use App\Models\Kuesioner\Kuesioner;
use App\Models\Pmb\PmbAkun;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\Proposal;
use App\Models\TugasAkhir\TugasAkhir;
use App\Services\Akademik\JadwalUjianService;
use App\Services\Keuangan\DispensasiService;
use App\Support\Portal;
use Database\Seeders\DemoCampusSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Walks every screen and every Bridge endpoint against the full demo campus.
 *
 * Two defects are being hunted, and they need two different instruments:
 *
 *  1. Lazy-loaded relations — caught by `Model::preventLazyLoading()`, which is
 *     armed outside production. Note that Laravel only arms it on queries that
 *     hydrate more than one model (Builder::hydrate), on the reasoning that a
 *     single record cannot cause an N+1.
 *
 *  2. A fresh query builder per row — invisible to the guard above, because
 *     nothing is being lazily *loaded*; the controller simply asks the database
 *     again inside a loop. Only a query count catches this.
 *
 * The ceilings below are deliberately close to the measured counts. A budget
 * with slack in it is a budget that never fails, and a regression that lands
 * inside the slack is a regression nobody sees.
 *
 * Every authenticated screen carries one query these ceilings did not used to
 * include: the unread-notification count behind the topbar bell. It is a single
 * COUNT over the (notifiable_type, notifiable_id, read_at) index and it runs on
 * every page of every portal — a real cost, accepted deliberately rather than
 * hidden by widening the budgets. The KRS screen is the one that had no slack
 * left, which is exactly what these numbers are for.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoCampusSeeder::class);

    Portal::lupakanTerm();

    // The seeder writes fixtures directly and relaxes strictness while it does
    // so; the walk itself must run under the strict rules.
    Model::preventLazyLoading();
});

/**
 * Runs a request with the query log on and fails if it overruns the budget.
 */
function dalamAnggaranKueri(Closure $panggil, int $anggaran): void
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $panggil();
    } finally {
        $kueri = DB::getRawQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    // The first fill of a guarded model in a process reads that table's
    // columns once (Model::isGuardableColumn) and caches them for the rest of
    // the process. Whether this screen pays for it depends on which test ran
    // before it in the same worker, not on the screen — the BKD worksheet
    // read 18 or 22 queries by test order alone. Not a cost of the page.
    $kueri = array_values(array_filter(
        $kueri,
        fn (array $q): bool => preg_match('/sqlite_master|pragma_table_xinfo/', $q['raw_query']) !== 1,
    ));

    expect(count($kueri))->toBeLessThanOrEqual(
        $anggaran,
        sprintf(
            "Anggaran kueri terlampaui: %d > %d.\nKueri terakhir:\n%s",
            count($kueri),
            $anggaran,
            collect($kueri)->take(-12)->pluck('raw_query')->implode("\n"),
        ),
    );
}

/*
 * Layar jadwal pada hari Minggu.
 *
 * Ditemukan dengan cara paling menjengkelkan: suite yang hijau kemarin gagal
 * hari ini, karena hari ini Minggu.
 *
 * `kelompokPerHari` mengisi Senin–Sabtu meski kosong, jadi hanya kunci 7 yang
 * benar-benar tidak ada — dan kartu "Hari Ini" mengindeksnya langsung. Setiap
 * mahasiswa, setiap Minggu, layarnya melempar "Undefined array key".
 *
 * Hanya Minggu yang diuji: hari lain selalu punya kuncinya, jadi menyertakannya
 * berarti menambah kasus yang tidak bisa gagal. Waktunya dibekukan supaya bug
 * bergantung tanggal ini tidak lagi menunggu kalender untuk terlihat.
 */
it('membuka layar jadwal pada hari Minggu', function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-08-09')->startOfWeek()->addDays(6));

    $mahasiswa = Mahasiswa::query()->whereHas('krs')->orderBy('id')->firstOrFail();

    $this->actingAs($mahasiswa, 'mahasiswa')->get('/mahasiswa/jadwal')->assertOk();

    Carbon\Carbon::setTestNow();
});

it('membuka layar mahasiswa dalam anggaran kueri', function (string $url, int $anggaran) {
    $mahasiswa = Mahasiswa::query()->whereHas('krs')->orderBy('id')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($mahasiswa, 'mahasiswa')->get($url)->assertOk(),
        $anggaran,
    );
})->with([
    ['/mahasiswa', 25],
    // 34 → 36: peta kurikulum (gerbang konsentrasi), konsentrasi mahasiswa,
    // dan prodinya. Ketiganya satu kueri apa pun panjang katalognya — bukan
    // per baris, jadi tidak tumbuh bersama jumlah kelas yang ditawarkan.
    //
    // 36 → 37: kueri hitung milik paginasi. Satu kueri tambahan yang dibayar
    // sekali, ditukar dengan katalog yang berhenti merender seluruh isinya —
    // pada 1.000 kelas layarnya dulu 1,92 detik dan 2,2 MB HTML.
    //
    // 37 → 27 (audit N+1, Okt 2026): prasyarat dibaca dari relasi yang sudah
    // di-eager-load, bukan satu kueri per baris katalog. Sepuluh kueri itu
    // tumbuh sampai 25 pada halaman penuh — lihat AnggaranKueriSkalaTest.
    ['/mahasiswa/krs', 27],
    ['/mahasiswa/jadwal', 20],
    // Ruang duduk seluruh sesi dihitung dari satu kueri peserta, bukan dua per
    // sesi — tanpa itu layar ini tumbuh bersama jumlah mata kuliah.
    ['/mahasiswa/jadwal-ujian', 18],
    // Tujuh sumber kalender satu kueri masing-masing, ditambah sesi ujian
    // milik mahasiswa dan ruang duduknya — tidak tumbuh per peristiwa.
    ['/mahasiswa/kalender', 25],
    ['/mahasiswa/kalender?tampilan=daftar', 25],
    ['/mahasiswa/khs', 25],
    ['/mahasiswa/tagihan', 20],
    ['/mahasiswa/tugas-akhir', 25],
    ['/notifikasi', 25],
    ['/notifikasi/preferensi', 25],
    ['/mahasiswa/surat', 30],
    ['/mahasiswa/helpdesk', 20],
    // Kuesioner: daftar, lalu satu tengokan ke tabel partisipasi untuk semuanya.
    ['/mahasiswa/kuesioner', 16],
    ['/mahasiswa/edom', 15],
    ['/mahasiswa/capaian', 12],
]);

it('membuka layar dosen dalam anggaran kueri', function (string $url, int $anggaran) {
    $dosen = Dosen::where('email', 'dosen1@demo.test')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($dosen, 'dosen')->get($url)->assertOk(),
        $anggaran,
    );
})->with([
    ['/dosen', 25],
    ['/dosen/kelas', 25],
    ['/dosen/jadwal-ujian', 16],
    ['/dosen/kalender', 26],
    ['/dosen/nilai', 25],
    ['/dosen/presensi', 25],
    ['/dosen/persetujuan-krs', 25],
    ['/dosen/bimbingan', 25],
    ['/dosen/tugas-akhir', 25],
    // Sengaja ketat: layar ini melisting seluruh kelas yang diampu, dan
    // menghitung tiap kelas satu per satu adalah godaan yang paling wajar di
    // sini. Anggaran ini yang menahannya.
    ['/dosen/edom', 14],
    // dosen1 punya laporan BKD yang sudah disahkan, jadi jalur ini membaca
    // cuplikan beku. Jalur lembar kerja hidup — yang jauh lebih mahal — diuji
    // terpisah di bawah.
    // 20 → 12, dan yang penting bukan angkanya: dulu tumbuh per kelas yang
    // diampu (RPS dan pertemuan dicari satu per satu), sekarang tidak. Anggaran
    // longgar hanya menyembunyikan kembalinya N+1 itu sampai ada dosen dengan
    // beban penuh.
    ['/dosen/rps', 12],
    ['/dosen/analitik', 12],
    ['/dosen/capaian', 12],
    ['/dosen/bkd', 12],
    ['/dosen/bkd/penilaian', 13],
    ['/dosen/portofolio', 13],
    // dosen1 memegang berkas kepegawaian lengkap demo. Empat belas relasi,
    // satu kueri masing-masing, validator sekali untuk semuanya — anggaran
    // ini tidak boleh tumbuh per baris riwayat.
    ['/dosen/kepegawaian', 25],
    ['/dosen/angka-kredit', 10],
    ['/dosen/helpdesk', 20],
    ['/dosen/kuesioner', 16],
    ['/notifikasi', 25],
]);

it('membuka layar admin dalam anggaran kueri', function (string $url, int $anggaran) {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get($url)->assertOk(),
        $anggaran,
    );
})->with([
    ['/admin', 30],
    ['/admin/mahasiswa', 25],
    // Laporan bentrok membaca seluruh sesi semester dan peserta seluruh kelasnya
    // dalam dua kueri, apa pun jumlah sesinya.
    ['/admin/jadwal-ujian', 28],
    ['/admin/jadwal-ujian/susun', 12],
    ['/admin/kalender', 18],
    ['/admin/kalender/peristiwa', 12],
    // Pemakaian ruang: ruang, gedung, slot kuliah, ruang ujian terbit, peserta,
    // dan libur — satu kueri masing-masing, berapa pun banyaknya ruang.
    ['/admin/pemanfaatan-ruang', 16],
    ['/admin/pemanfaatan-ruang?urut=terkosong&ruang=00000000-0000-0000-0000-000000000000', 16],
    ['/admin/pemanfaatan-ruang/ruang-kosong', 10],
    ['/admin/pemanfaatan-ruang/ruang-kosong?hari=1&jam_mulai=08:00&jam_selesai=10:00&kapasitas_min=30', 13],
    ['/admin/pemanfaatan-ruang/ruang-kosong?tanggal=2026-10-14&jam_mulai=08:00&jam_selesai=10:00', 15],

    // Ditambahkan 18 Agu 2026 sesudah layar ini ditemukan 500 di MySQL: view-nya
    // merender `prodiPilihan2` yang tidak ikut di-eager-load, dan penjaga N+1
    // melemparkannya. Lolos selama ini justru karena tidak pernah dipanggil tes.
    ['/admin/pmb', 20],
    ['/admin/yudisium', 30],
    ['/admin/tugas-akhir', 25],
    ['/admin/data-iku', 25],
    // Kurikulum OBE (ADR-038): daftar mata kuliah dengan status versinya,
    // dan rekap capaian prodi dari baris tersimpan — tidak tumbuh per baris.
    ['/admin/cpmk', 12],
    ['/admin/capaian', 12],
    ['/admin/feeder', 30],
    ['/admin/feeder/lampau', 12],
    ['/admin/bridge', 25],
    // Klien SSO: daftar, catatan kampus, dan pengguna per klien dalam satu
    // kueri berkelompok.
    ['/admin/sso-klien', 14],
    ['/admin/surat', 25],
    ['/admin/helpdesk', 20],
    ['/admin/helpdesk/pengaturan', 14],
    // Staf sebagai pemohon: layar pemohon yang sama dengan portal mahasiswa.
    ['/admin/tiket-saya', 20],
    ['/admin/kuesioner', 14],
    ['/admin/kuesioner-saya', 14],
    ['/admin/konversi', 25],
    ['/admin/pindahan', 14],
    ['/admin/pindahan/baru', 11],
    ['/admin/beasiswa', 25],
    ['/admin/edom', 24],
    ['/admin/bkd', 20],
    // Antrean: satu kueri per riwayat, apa pun jumlah ajuannya.
    ['/admin/kepegawaian/validasi', 28],
    // Rekap kampus dan lembar penyetaraan dari tiga kueri, apa pun jumlah
    // dosennya — lembar penyetaraan memakai rekap yang sama, bukan menghitung ulang.
    ['/admin/angka-kredit', 12],
    ['/admin/akuntansi', 16],
    ['/admin/kurikulum-lanjutan', 18],
    ['/admin/unit-kerja', 12],
    ['/admin/kinerja', 16],

    // 21 sesudah rekap berhenti mengulang kueri daftarnya. Sebelum itu 26 —
    // duplikasi yang sama seperti pada /dosen/rps, dan hanya angka inilah yang
    // menangkapnya.
    ['/admin/spmi', 21],
    ['/notifikasi', 25],
]);

/*
 * Berkas kepegawaian dan buku besar angka kredit satu dosen — dosen demo
 * pertama, yang riwayatnya lengkap dan buku besarnya memuat konversi, BKD
 * disahkan, ijazah, dan penyesuaian.
 */
it('membuka berkas kepegawaian dan buku besar angka kredit satu dosen dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $dosen = Dosen::whereNotNull('nidn')->orderBy('id')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get("/admin/dosen/{$dosen->uuid}/kepegawaian")
            ->assertOk()->assertSee('Penata'),
        29,
    );

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get("/admin/angka-kredit/{$dosen->uuid}")
            ->assertOk()->assertSee('Konversi BKD lama'),
        6,
    );
});

/*
 * Penetapan dosen wali (Sesi 65): satu prodi demo dengan seluruh mahasiswanya.
 * Wali saat ini, jumlah KRS menunggu, dan beban bimbingan tiap calon dosen
 * dibaca bersama — bukan satu kueri per baris.
 */
it('membuka layar penetapan dosen wali dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $prodi = Mahasiswa::query()->selectRaw('prodi_id, count(*) as n')->groupBy('prodi_id')->orderByDesc('n')->value('prodi_id');

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get('/admin/dosen-wali?prodi='.$prodi)->assertOk(),
        16,
    );
});

/*
 * Meja dispensasi (Sesi 65): permohonan, yang berlaku, dan riwayat, masing-
 * masing beserta tagihan dan mahasiswanya — dimuat per daftar, bukan per baris.
 */
it('membuka meja dispensasi dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $layanan = app(DispensasiService::class);

    // Demo invoices that are not yet settled: two granted, two requested.
    Tagihan::query()->with('mahasiswa')->belumLunas()->orderBy('id')->limit(4)->get()
        ->each(fn ($t, int $i) => $i < 2
            ? $layanan->beri($t, new DispensasiData(now()->addDays(14 + $i), 'Uji anggaran kueri'), $staff)
            : $layanan->ajukan($t, $t->mahasiswa, new DispensasiData(now()->addDays(10), 'Uji anggaran kueri')));

    expect(DispensasiTagihan::count())->toBe(4);

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get('/admin/keuangan/dispensasi')->assertOk()->assertSee('Uji anggaran kueri'),
        // 20 terukur; riwayat yang berisi menambah satu eager load per relasi.
        26,
    );
});

/*
 * Pengumuman di ketiga portal (Sesi 65): satu scope, paginasi, tanpa relasi.
 */
it('membuka daftar pengumuman ketiga portal dalam anggaran kueri', function (string $email, string $guard, string $url, int $anggaran) {
    $aktor = match ($guard) {
        'mahasiswa' => Mahasiswa::where('email', $email)->firstOrFail(),
        'dosen' => Dosen::where('email', $email)->firstOrFail(),
        default => Staff::where('email', $email)->firstOrFail(),
    };

    dalamAnggaranKueri(fn () => $this->actingAs($aktor, $guard)->get($url)->assertOk(), $anggaran);
})->with([
    ['mahasiswa1@demo.test', 'mahasiswa', '/mahasiswa/pengumuman', 8],
    ['dosen1@demo.test', 'dosen', '/dosen/pengumuman', 8],
    ['admin@demo.test', 'staff', '/admin/pengumuman/papan', 8],
]);

/*
 * Detail mahasiswa staf (Sesi 65): mahasiswa demo dengan riwayat semester
 * terpanjang — KRS, KHS, cuti, wali, tagihan, dan validator Feeder sekaligus.
 * Super-admin melihat semua bagian, jadi semua kueri ikut terhitung.
 */
it('membuka detail mahasiswa staf dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $mahasiswa = Mahasiswa::query()->withCount('statusPerSemester')->orderByDesc('status_per_semester_count')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get('/admin/mahasiswa/'.$mahasiswa->uuid)->assertOk(),
        // 33 terukur; setiap daftar dimuat sekali (KRS, KHS, tagihan, cuti,
        // riwayat wali), validator Feeder menambah empat.
        35,
    );
});

/*
 * Profil pindahan demo: dibuat lewat intake sungguhan oleh KonversiSeeder,
 * dengan tiga konversi diakui dan satu menunggu. Ringkasannya menjalankan
 * aturan FeederValidator yang sama dengan sinkronisasi — termasuk pemetaan
 * jenis daftar dan perubahan data yang terbuka — jadi ia tidak boleh tumbuh
 * per baris konversi.
 */
it('membuka profil pindahan demo dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $pindahan = Mahasiswa::where('email', 'nadia.pindahan@demo.test')->firstOrFail();

    // SKS diakuinya adalah jumlah konversi yang disetujui, bukan angka seeder.
    expect($pindahan->jenis_daftar)->toBe(JenisDaftar::Pindahan)
        ->and($pindahan->sks_diakui)->toBe((int) $pindahan->konversiKredit()->diakui()->sum('sks_diakui'))
        ->and($pindahan->sks_diakui)->toBeGreaterThan(0);

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get('/admin/pindahan/'.$pindahan->uuid)->assertOk(),
        // 19 sejak #44b: validator biodata juga menanyakan klaim mahasiswa
        // lampau bagi mahasiswa yang belum ber-id Feeder.
        19,
    );
});

it('melayani endpoint Bridge dalam anggaran kueri', function (string $url, int $anggaran) {
    $consumer = BridgeConsumer::query()->orderBy('id')->firstOrFail();
    $token = $consumer->createToken('smoke', $consumer->scopes)->plainTextToken;

    dalamAnggaranKueri(
        fn () => $this->getJson($url, ['Authorization' => 'Bearer '.$token])->assertOk(),
        $anggaran,
    );
})->with([
    ['/api/bridge/v1/students', 20],
    ['/api/bridge/v1/students/statistics', 20],
    ['/api/bridge/v1/lecturers', 20],
    ['/api/bridge/v1/classes', 20],
    ['/api/bridge/v1/student-activities', 20],
    ['/api/bridge/v1/graduates', 20],
    ['/api/bridge/v1/academic-terms', 20],
    ['/api/bridge/v1/academic-terms/current', 20],
    // +7 fixed queries for the iku_2026 block (ADR-036), not per row.
    ['/api/bridge/v1/iku-data', 42],
    ['/api/bridge/v1/teaching-evaluations', 14],
    ['/api/bridge/v1/lecturer-workload', 14],
]);

it('membuka halaman publik tanpa menyentuh basis data berlebihan', function (string $url, int $anggaran) {
    dalamAnggaranKueri(fn () => $this->get($url)->assertOk(), $anggaran);
})->with([
    ['/', 5],
    ['/masuk', 5],

    // FAQ publik: daftar terbit + kategorinya. Satu kueri per kelompok adalah
    // regresi yang paling mungkin di sini.
    ['/bantuan', 6],

    // Halaman publik yang paling mungkin dijangkau bot: satu kueri pun terasa
    // bila seseorang memindai rentang UUID.
    ['/verifikasi', 5],
    ['/pmb', 4],
    ['/pmb/masuk', 1],
]);

/*
 * PMB daring (#32) pada gelombang demo: layar gelombang dan antrean kasir di
 * sisi staf, portal pendaftar di sisi calon. Daftar pendaftar dan berkasnya
 * tumbuh per orang, jadi yang dijaga di sini adalah bahwa jumlahnya tidak.
 */
it('membuka layar PMB daring demo dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $gelombang = PmbGelombang::query()->terbukaDaring()->firstOrFail();
    $pendaftar = PmbPendaftar::where('email', 'calon.verifikasi@demo.test')->firstOrFail();
    $calon = PmbAkun::where('email', 'calon.seleksi@demo.test')->firstOrFail();

    dalamAnggaranKueri(fn () => $this->actingAs($staff, 'staff')->get('/admin/pmb/gelombang/'.$gelombang->uuid)->assertOk(), 17);
    dalamAnggaranKueri(fn () => $this->actingAs($staff, 'staff')->get('/admin/pmb/pendaftar/'.$pendaftar->uuid)->assertOk(), 11);
    dalamAnggaranKueri(fn () => $this->actingAs($staff, 'staff')->get('/admin/pmb/formulir')->assertOk(), 9);
    dalamAnggaranKueri(fn () => $this->actingAs($calon, 'pendaftar')->get('/pmb/portal')->assertOk(), 8);
    dalamAnggaranKueri(fn () => $this->actingAs($calon, 'pendaftar')->get('/pmb/portal/'.$calon->pendaftaran()->value('uuid'))->assertOk(), 10);
});

it('membuka layar kelola tugas akhir dalam anggaran kueri', function () {
    // Layar terberat modul ini: satu karya beserta pembimbing, seluruh log
    // bimbingan, tiap ujian dengan panelnya, dan beban bimbingan setiap dosen
    // aktif agar yang mengalokasikan tahu siapa yang sudah penuh. Semuanya
    // mudah berubah menjadi satu kueri per baris.
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $ta = TugasAkhir::query()->orderBy('id')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($staff, 'staff')->get('/admin/tugas-akhir/'.$ta->uuid)->assertOk(),
        30,
    );
});

it('menyusun analitik satu kelas dalam anggaran kueri', function () {
    /*
     * Kandidat N+1 paling jelas di seluruh repo: tiga lensa atas satu kelas,
     * masing-masing menggoda untuk berjalan per mahasiswa.
     *
     * 60 → 23 (audit N+1, Okt 2026). Kehadiran dulu memanggil PresensiService
     * per peserta, dua kueri masing-masing, dan anggaran ini sengaja dibiarkan
     * naik bersama jumlah peserta. Sekarang persentasenya dibaca dari rekap
     * kelas — perhitungan yang sama di PresensiService, satu lintasan — jadi
     * angkanya tidak lagi bergantung pada besarnya kelas.
     */
    $dosen = Dosen::where('email', 'dosen1@demo.test')->firstOrFail();

    $kelas = KelasKuliah::query()
        ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosen->id))
        ->whereHas('komponenNilai')
        ->orderBy('id')
        ->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($dosen, 'dosen')->get('/dosen/analitik/'.$kelas->uuid)->assertOk(),
        23,
    );
});

it('menyusun lembar kerja BKD hidup dalam anggaran kueri', function () {
    /*
     * Jalur yang paling mahal di modul BKD, dan yang tidak tersentuh baris
     * dataset di atas: selama laporan masih draf, unsur pendidikan dihitung
     * ulang dari kelas, bimbingan, pengujian, dan perwalian pada setiap
     * pembukaan halaman.
     *
     * Dosen yang dipilih sengaja yang belum punya laporan — dosen1 sudah
     * disahkan, sehingga layarnya membaca cuplikan beku dan tidak membuktikan
     * apa pun tentang jalur ini.
     */
    $dosen = Dosen::query()
        ->whereDoesntHave('laporanBkd')
        ->whereHas('kelasKuliah')
        ->orderBy('id')
        ->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($dosen, 'dosen')->get('/dosen/bkd')->assertOk(),
        20,
    );
});

it('menyusun transkrip PDF dalam anggaran kueri', function () {
    $mahasiswa = Mahasiswa::query()
        ->whereHas('nilai', fn ($q) => $q->where('is_final', true))
        ->orderBy('id')
        ->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($mahasiswa, 'mahasiswa')->get('/mahasiswa/khs/transkrip')->assertOk(),
        25,
    );
});

it('menerbitkan UTS semester aktif yang bebas bentrok di kampus demo', function () {
    /*
     * Seeder menyusun dan menerbitkan lewat gerbang bentrok yang sama dengan
     * layar BAAK, jadi "terbit" di sini berarti "tidak ada mahasiswa dengan
     * dua ujian bersamaan" — bukan sekadar baris yang ditandai terbit.
     */
    $term = TahunAkademik::aktif();
    $layanan = app(JadwalUjianService::class);

    expect(JadwalUjian::term($term->id)->terbit()->count())->toBeGreaterThan(0)
        ->and($layanan->jenisTerbit($term))->toBe(JenisUjianKelas::Uts)
        ->and(collect($layanan->laporanBentrok($term))->flatten()->where('menghalangi', true))->toBeEmpty();
});

it('menanam libur dan kegiatan terbit pada semester aktif, di luar UTS demo', function () {
    $term = TahunAkademik::aktif();

    $dalamSemester = KalenderPeristiwa::query()->terbit()->beririsan($term->tanggal_mulai, $term->tanggal_selesai);

    expect((clone $dalamSemester)->where('jenis', JenisPeristiwa::Libur->value)->count())->toBeGreaterThanOrEqual(2)
        ->and((clone $dalamSemester)->where('jenis', JenisPeristiwa::Kegiatan->value)->count())->toBeGreaterThanOrEqual(2)
        ->and(KalenderPeristiwa::query()->where('status', StatusPeristiwa::Draf->value)->count())->toBeGreaterThanOrEqual(1)
        // Libur demo tidak boleh membuat UTS demo membawa peringatan.
        ->and(collect(app(JadwalUjianService::class)->laporanBentrok($term))->flatten()->where('jenis', 'libur'))->toBeEmpty();
});

it('membuka detail jadwal ujian dan mencetak kartu dari jadwal ujian dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $jadwal = JadwalUjian::query()->orderBy('id')->firstOrFail();

    dalamAnggaranKueri(
        // Laporan bentrok semester, peserta, rekap kehadiran, dan tunggakan —
        // masing-masing satu kueri untuk seluruh peserta, bukan per baris.
        fn () => $this->actingAs($staff, 'staff')->get('/admin/jadwal-ujian/'.$jadwal->uuid)->assertOk(),
        32,
    );

    $mahasiswa = Mahasiswa::query()->whereHas('krs')->orderBy('id')->firstOrFail();

    dalamAnggaranKueri(
        fn () => $this->actingAs($mahasiswa, 'mahasiswa')->get('/mahasiswa/kartu-ujian')->assertOk(),
        30,
    );
});

/*
 * KKN/PKL (#37): daftar dan rincian di tiga portal. Rincian merender seluruh
 * anggota dengan prodi, nilai, dan logbooknya — tempat paling wajar bagi
 * kueri per anggota untuk menyelinap masuk.
 */
it('membuka layar KKN/PKL di tiga portal dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $kegiatan = KegiatanLapangan::query()->firstOrFail();
    $kelompok = $kegiatan->kelompok()->whereNotNull('dosen_pembimbing_id')->orderBy('nomor')->firstOrFail();
    $mahasiswa = $kelompok->anggota()->firstOrFail()->mahasiswa;

    foreach ([
        [$staff, 'staff', '/admin/kegiatan-lapangan', 12],
        [$staff, 'staff', '/admin/kegiatan-lapangan/'.$kegiatan->uuid, 16],
        [$kelompok->pembimbing, 'dosen', '/dosen/kegiatan-lapangan', 7],
        [$kelompok->pembimbing, 'dosen', '/dosen/kegiatan-lapangan/'.$kelompok->uuid, 10],
        [$mahasiswa, 'mahasiswa', '/mahasiswa/kegiatan-lapangan', 15],
    ] as [$aktor, $guard, $url, $anggaran]) {
        auth()->forgetGuards();

        dalamAnggaranKueri(fn () => $this->actingAs($aktor, $guard)->get($url)->assertOk(), $anggaran);
    }
});

/*
 * Tahap proposal tugas akhir (#38): rincian tugas akhir staf dengan riwayat
 * versi dan telaah, dan halaman telaah calon pembimbing.
 */
it('membuka layar proposal tugas akhir dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $proposal = Proposal::query()->where('status', 'menunggu')->firstOrFail();

    foreach ([
        [$staff, 'staff', '/admin/tugas-akhir/'.$proposal->tugasAkhir->uuid, 22],
        [$proposal->calonPembimbing, 'dosen', '/dosen/tugas-akhir', 12],
        [$proposal->calonPembimbing, 'dosen', '/dosen/tugas-akhir/proposal/'.$proposal->uuid, 11],
        [$proposal->tugasAkhir->mahasiswa, 'mahasiswa', '/mahasiswa/tugas-akhir', 12],
    ] as [$aktor, $guard, $url, $anggaran]) {
        auth()->forgetGuards();

        dalamAnggaranKueri(fn () => $this->actingAs($aktor, $guard)->get($url)->assertOk(), $anggaran);
    }
});

/*
 * Kuesioner umum: detail, hasil, dan formulir isi kuesioner demo yang sudah
 * melewati ambangnya — hasilnya benar-benar dihitung, bukan tertahan.
 */
it('membuka detail, hasil, dan formulir kuesioner dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $kuesioner = Kuesioner::where('kode', 'KEPUASAN-2026')->firstOrFail();
    $mahasiswa = Mahasiswa::query()->whereHas('krs')->orderBy('id')->firstOrFail();

    dalamAnggaranKueri(fn () => $this->actingAs($staff, 'staff')->get(route('admin.kuesioner.show', $kuesioner))->assertOk(), 16);
    dalamAnggaranKueri(fn () => $this->actingAs($staff, 'staff')->get(route('admin.kuesioner.hasil', $kuesioner))->assertOk()->assertSee('Wi-Fi'), 14);
    dalamAnggaranKueri(fn () => $this->actingAs($mahasiswa, 'mahasiswa')->get(route('mahasiswa.kuesioner.show', $kuesioner))->assertOk(), 16);
});

it('membuka halaman kelola konsumen Bridge demo dalam anggaran kueri', function () {
    $staff = Staff::where('email', 'admin@demo.test')->firstOrFail();
    $konsumen = BridgeConsumer::query()->orderBy('id')->firstOrFail();

    dalamAnggaranKueri(fn () => $this->actingAs($staff, 'staff')->get(route('admin.bridge.konsumen.show', $konsumen))->assertOk(), 14);
});
