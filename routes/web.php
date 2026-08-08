<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\Dosen;
use App\Http\Controllers\Mahasiswa;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\Sso;
use App\Http\Controllers\VerifikasiSuratController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::view('/', 'landing')->name('landing');

/*
|--------------------------------------------------------------------------
| Verifikasi Dokumen — publik
|--------------------------------------------------------------------------
|
| Tanpa autentikasi, dan memang harus begitu: yang memeriksa keaslian surat
| adalah petugas bank, staf kedutaan, atau calon pemberi kerja — tak satu pun
| punya akun di sini, dan meminta mereka membuatnya berarti tidak akan ada
| yang pernah memverifikasi apa pun.
|
| Karena itu pengamanannya ada pada dua hal lain: halaman ini hanya
| menampilkan secukupnya untuk dicocokkan dengan kertas di tangan pembaca, dan
| dikunci pada UUID yang tak dapat ditebak — bukan nomor surat yang berurutan.
| Pencarian manual dibatasi lajunya karena ia menerima tebakan.
|
*/
Route::prefix('verifikasi')->name('verifikasi.')->group(function (): void {
    Route::get('/', [VerifikasiSuratController::class, 'formulir'])->name('formulir');

    Route::post('/', [VerifikasiSuratController::class, 'cari'])
        ->middleware('throttle:verifikasi')
        ->name('cari');

    Route::get('/{uuid}', [VerifikasiSuratController::class, 'tampil'])
        ->middleware('throttle:verifikasi')
        ->name('surat');
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| One sign-in surface for all three portals; LoginController resolves the
| guard from the identifier that was typed.
|
*/

Route::get('/masuk', [LoginController::class, 'show'])->name('login');
Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');
Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');

/*
|--------------------------------------------------------------------------
| SSO — aplikasi yang diizinkan seseorang
|--------------------------------------------------------------------------
|
| Reachable by all three portals: the consent screen promises that access can
| be withdrawn, and that promise has to hold for whoever gave it.
|
*/

if (config('sso.enabled')) {
    Route::middleware('auth:mahasiswa,dosen,staff')->group(function (): void {
        Route::get('/aplikasi-terhubung', [Sso\AplikasiTerhubungController::class, 'index'])
            ->name('sso.aplikasi');

        Route::delete('/aplikasi-terhubung/{client}', [Sso\AplikasiTerhubungController::class, 'cabut'])
            ->name('sso.aplikasi.cabut');
    });
}

/*
|--------------------------------------------------------------------------
| Portal Mahasiswa
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:mahasiswa', 'term.active'])->prefix('mahasiswa')->name('mahasiswa.')->group(function (): void {
    Route::get('/', Mahasiswa\DashboardController::class)->name('dashboard');

    Route::get('/krs', [Mahasiswa\KrsController::class, 'index'])->name('krs');
    Route::post('/krs/kelas/{kelas}', [Mahasiswa\KrsController::class, 'tambah'])->name('krs.tambah');
    Route::delete('/krs/detail/{detail}', [Mahasiswa\KrsController::class, 'hapus'])->name('krs.hapus');
    Route::post('/krs/paket', [Mahasiswa\KrsController::class, 'paket'])->name('krs.paket');

    // Tanpa parameter: pemiliknya adalah yang sedang masuk. Endpoint yang
    // menerima NIM butuh pemeriksaan kepemilikan, dan pemeriksaan bisa salah
    // ditulis — tidak menerima parameternya sama sekali tidak bisa.
    Route::get('/ktm', [Mahasiswa\CetakController::class, 'ktm'])->name('ktm');
    Route::get('/kartu-ujian', [Mahasiswa\CetakController::class, 'kartuUjian'])->name('kartu-ujian');
    Route::post('/krs/ajukan', [Mahasiswa\KrsController::class, 'ajukan'])->name('krs.ajukan');

    Route::get('/jadwal', Mahasiswa\JadwalController::class)->name('jadwal');
    Route::get('/khs', Mahasiswa\KhsController::class)->name('khs');
    Route::get('/khs/transkrip', Mahasiswa\TranskripController::class)->name('transkrip');
    Route::get('/tagihan', Mahasiswa\TagihanController::class)->name('tagihan');

    Route::get('/presensi', [Mahasiswa\PresensiMandiriController::class, 'form'])->name('presensi');
    Route::post('/presensi', [Mahasiswa\PresensiMandiriController::class, 'catat'])->name('presensi.catat');

    Route::get('/tugas-akhir', [Mahasiswa\TugasAkhirController::class, 'index'])->name('tugas-akhir');
    Route::post('/tugas-akhir', [Mahasiswa\TugasAkhirController::class, 'ajukan'])->name('tugas-akhir.ajukan');
    Route::post('/tugas-akhir/bimbingan', [Mahasiswa\TugasAkhirController::class, 'catatBimbingan'])->name('tugas-akhir.bimbingan');
    Route::delete('/tugas-akhir/bimbingan/{bimbingan}', [Mahasiswa\TugasAkhirController::class, 'hapusBimbingan'])->name('tugas-akhir.bimbingan.hapus');

    Route::get('/capaian', Mahasiswa\PenguasaanController::class)->name('capaian');

    Route::get('/edom', [Mahasiswa\EdomController::class, 'index'])->name('edom');
    Route::post('/edom', [Mahasiswa\EdomController::class, 'kirim'])->name('edom.kirim');

    Route::get('/surat', [Mahasiswa\SuratController::class, 'index'])->name('surat');
    Route::post('/surat', [Mahasiswa\SuratController::class, 'ajukan'])->name('surat.ajukan');
    Route::get('/surat/{surat}/unduh', [Mahasiswa\SuratController::class, 'unduh'])->name('surat.unduh');

    Route::get('/profil', [Mahasiswa\ProfilController::class, 'index'])->name('profil');
    Route::put('/profil', [Mahasiswa\ProfilController::class, 'perbarui'])->name('profil.perbarui');
    Route::post('/profil/kata-sandi', [Mahasiswa\ProfilController::class, 'gantiKataSandi'])->name('profil.kata-sandi');
});

/*
|--------------------------------------------------------------------------
| Portal Dosen
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:dosen', 'term.active'])->prefix('dosen')->name('dosen.')->group(function (): void {
    Route::get('/', Dosen\DashboardController::class)->name('dashboard');

    Route::get('/kelas', Dosen\KelasController::class)->name('kelas');

    Route::get('/nilai', [Dosen\NilaiController::class, 'index'])->name('nilai');
    Route::get('/nilai/{kelas}', [Dosen\NilaiController::class, 'edit'])->name('nilai.kelas');
    Route::post('/nilai/{kelas}', [Dosen\NilaiController::class, 'simpan'])->name('nilai.simpan');
    Route::post('/nilai/{kelas}/finalisasi', [Dosen\NilaiController::class, 'finalisasi'])->name('nilai.finalisasi');

    Route::get('/presensi', [Dosen\PresensiController::class, 'index'])->name('presensi');
    Route::get('/presensi/{kelas}', [Dosen\PresensiController::class, 'show'])->name('presensi.kelas');
    Route::post('/presensi/{kelas}/{pertemuan}', [Dosen\PresensiController::class, 'simpan'])->name('presensi.simpan');
    Route::post('/presensi/{kelas}/{pertemuan}/qr', [Dosen\PresensiController::class, 'bukaQr'])->name('presensi.qr.buka');
    Route::delete('/presensi/{kelas}/{pertemuan}/qr', [Dosen\PresensiController::class, 'tutupQr'])->name('presensi.qr.tutup');

    // Perwalian KRS — dosen wali bagi satu angkatan.
    Route::get('/bimbingan', Dosen\BimbinganController::class)->name('bimbingan');

    // Bimbingan tugas akhir — hal yang berbeda, dan sengaja terpisah: dosen
    // wali membimbing rencana studi, pembimbing tugas akhir membimbing karya.
    Route::get('/edom', Dosen\EdomController::class)->name('edom');

    Route::get('/rps', [Dosen\RpsController::class, 'index'])->name('rps');
    Route::get('/rps/susun/{mataKuliah}', [Dosen\RpsController::class, 'susun'])->name('rps.susun');
    Route::put('/rps/{rps}', [Dosen\RpsController::class, 'simpan'])->name('rps.simpan');
    Route::post('/rps/{rps}/terbitkan', [Dosen\RpsController::class, 'terbitkan'])->name('rps.terbitkan');
    Route::get('/rps/jurnal/{kelas}', [Dosen\RpsController::class, 'jurnal'])->name('rps.jurnal');
    Route::post('/rps/jurnal/{pertemuan}/simpan', [Dosen\RpsController::class, 'simpanJurnal'])->name('rps.jurnal.simpan');

    Route::get('/analitik', [Dosen\AnalitikController::class, 'index'])->name('analitik');
    Route::get('/analitik/{kelas}', [Dosen\AnalitikController::class, 'kelas'])->name('analitik.kelas');

    Route::get('/bkd', [Dosen\BkdController::class, 'index'])->name('bkd');
    Route::post('/bkd/ajukan', [Dosen\BkdController::class, 'ajukan'])->name('bkd.ajukan');
    Route::get('/bkd/{laporan}/unduh', [Dosen\BkdController::class, 'unduh'])->name('bkd.unduh');
    Route::get('/bkd/penilaian', [Dosen\BkdController::class, 'penilaian'])->name('bkd.penilaian');
    Route::post('/bkd/{laporan}/nilai', [Dosen\BkdController::class, 'nilai'])->name('bkd.nilai');
    Route::post('/bkd/{laporan}/kembalikan', [Dosen\BkdController::class, 'kembalikan'])->name('bkd.kembalikan');

    Route::get('/portofolio', [Dosen\PortofolioController::class, 'index'])->name('portofolio');
    Route::post('/portofolio/pendidikan', [Dosen\PortofolioController::class, 'simpanPendidikan'])->name('portofolio.pendidikan');
    Route::post('/portofolio/jabatan', [Dosen\PortofolioController::class, 'simpanJabatan'])->name('portofolio.jabatan');
    Route::post('/portofolio/sertifikasi', [Dosen\PortofolioController::class, 'simpanSertifikasi'])->name('portofolio.sertifikasi');
    Route::post('/portofolio/kegiatan', [Dosen\PortofolioController::class, 'simpanKegiatan'])->name('portofolio.kegiatan');

    Route::get('/tugas-akhir', [Dosen\TugasAkhirController::class, 'index'])->name('tugas-akhir');
    Route::get('/tugas-akhir/{tugasAkhir}', [Dosen\TugasAkhirController::class, 'show'])->name('tugas-akhir.show');
    Route::post('/tugas-akhir/bimbingan/{bimbingan}/setujui', [Dosen\TugasAkhirController::class, 'setujuiBimbingan'])->name('tugas-akhir.bimbingan.setujui');
    Route::post('/tugas-akhir/bimbingan/{bimbingan}/cabut', [Dosen\TugasAkhirController::class, 'batalkanPersetujuan'])->name('tugas-akhir.bimbingan.cabut');
    Route::post('/tugas-akhir/penguji/{penguji}/nilai', [Dosen\TugasAkhirController::class, 'nilaiUjian'])->name('tugas-akhir.penguji.nilai');

    Route::get('/persetujuan-krs', [Dosen\PersetujuanKrsController::class, 'index'])->name('persetujuan-krs');
    Route::post('/persetujuan-krs/{krs}', [Dosen\PersetujuanKrsController::class, 'putuskan'])
        ->name('persetujuan-krs.putuskan');

    // Dibatasi kelas yang benar-benar diampu: daftar hadir memuat nama seluruh
    // peserta, persis jenis daftar yang tidak boleh dicetak siapa pun yang
    // menebak id kelas.
    Route::get('/kelas/{kelas}/absensi', [Dosen\CetakController::class, 'absensi'])->name('kelas.absensi');
    Route::get('/kelas/{kelas}/jurnal', [Dosen\CetakController::class, 'jurnal'])->name('kelas.jurnal');
});

/*
|--------------------------------------------------------------------------
| Unduhan Berkas
|--------------------------------------------------------------------------
|
| Berkas pendukung disimpan pada disk privat yang tidak dapat dijangkau web
| server, sehingga tidak ada URL yang menyajikannya langsung. Setiap unduhan
| lewat sini, dan tiap rute memutuskan siapa yang boleh melihat berkas itu —
| bukan sekadar siapa yang sudah masuk.
|
| Sengaja di luar gerbang term.active: berkas pendaftar dan dokumen cuti tetap
| perlu dibuka meski belum ada semester yang ditetapkan aktif.
|
*/

/*
|--------------------------------------------------------------------------
| Notifikasi
|--------------------------------------------------------------------------
|
| Satu layar untuk ketiga portal: notifikasi milik orang, bukan milik peran.
|
| Di luar gerbang term.active, sengaja. Pemberitahuan tentang tagihan dan
| keputusan tetap harus terbaca pada masa pergantian semester — justru saat
| itulah paling banyak keputusan diambil.
|
*/
Route::middleware('auth:staff,dosen,mahasiswa')->prefix('notifikasi')->name('notifikasi')
    ->group(function (): void {
        Route::get('/', [NotifikasiController::class, 'index']);
        Route::post('/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('.baca-semua');
        Route::post('/{notifikasi}/baca', [NotifikasiController::class, 'baca'])->name('.baca');
        Route::get('/preferensi', [NotifikasiController::class, 'preferensi'])->name('.preferensi');
        Route::put('/preferensi', [NotifikasiController::class, 'simpanPreferensi'])->name('.preferensi.simpan');
    });

Route::middleware('auth:staff,mahasiswa')->prefix('berkas')->name('berkas.')->group(function (): void {
    Route::get('/pmb/{berkas}', [BerkasController::class, 'pmb'])->name('pmb');
    Route::get('/cuti/{cuti}', [BerkasController::class, 'cuti'])->name('cuti');
});

/*
|--------------------------------------------------------------------------
| Portal Admin / BAAK
|--------------------------------------------------------------------------
*/

/*
| Master data sits OUTSIDE the term.active gate, deliberately.
|
| EnsureTermIsActive answers 503 when no academic term is flagged active — and
| the screen that creates the first term is this one. Leaving it inside the gate
| makes a fresh installation unusable: the operator is told to set up a semester
| by a page that refuses to load until a semester exists.
|
| One area with tabs rather than six sidebar entries: setting up a semester
| means moving between terms, programmes and courses in a single sitting.
*/
Route::middleware('auth:staff')->prefix('admin/master')->name('admin.master.')
    ->group(function (): void {
        Route::get('/', [Admin\Master\TahunAkademikController::class, 'index'])->name('index');

        Route::get('/tahun-akademik', [Admin\Master\TahunAkademikController::class, 'index'])->name('term');
        Route::post('/tahun-akademik', [Admin\Master\TahunAkademikController::class, 'store'])->name('term.store');
        Route::put('/tahun-akademik/{term}', [Admin\Master\TahunAkademikController::class, 'update'])->name('term.update');
        Route::post('/tahun-akademik/{term}/aktifkan', [Admin\Master\TahunAkademikController::class, 'aktifkan'])->name('term.aktifkan');
        Route::post('/tahun-akademik/{term}/kunci', [Admin\Master\TahunAkademikController::class, 'kunci'])->name('term.kunci');
        Route::post('/tahun-akademik/{term}/buka-kunci', [Admin\Master\TahunAkademikController::class, 'bukaKunci'])->name('term.buka-kunci');

        Route::get('/cpl', [Admin\Master\CplController::class, 'index'])->name('cpl');
        Route::post('/cpl', [Admin\Master\CplController::class, 'store'])->name('cpl.store');
        Route::put('/cpl/{cpl}', [Admin\Master\CplController::class, 'update'])->name('cpl.update');
        Route::delete('/cpl/{cpl}', [Admin\Master\CplController::class, 'destroy'])->name('cpl.destroy');

        Route::get('/fakultas', [Admin\Master\FakultasController::class, 'index'])->name('fakultas');
        Route::post('/fakultas', [Admin\Master\FakultasController::class, 'store'])->name('fakultas.store');
        Route::put('/fakultas/{fakultas}', [Admin\Master\FakultasController::class, 'update'])->name('fakultas.update');
        Route::delete('/fakultas/{fakultas}', [Admin\Master\FakultasController::class, 'destroy'])->name('fakultas.destroy');

        Route::get('/prodi', [Admin\Master\ProdiController::class, 'index'])->name('prodi');
        Route::post('/prodi', [Admin\Master\ProdiController::class, 'store'])->name('prodi.store');
        Route::put('/prodi/{prodi}', [Admin\Master\ProdiController::class, 'update'])->name('prodi.update');
        Route::delete('/prodi/{prodi}', [Admin\Master\ProdiController::class, 'destroy'])->name('prodi.destroy');

        Route::get('/kurikulum', [Admin\Master\KurikulumController::class, 'index'])->name('kurikulum');
        Route::post('/kurikulum', [Admin\Master\KurikulumController::class, 'store'])->name('kurikulum.store');
        Route::put('/kurikulum/{kurikulum}', [Admin\Master\KurikulumController::class, 'update'])->name('kurikulum.update');
        Route::delete('/kurikulum/{kurikulum}', [Admin\Master\KurikulumController::class, 'destroy'])->name('kurikulum.destroy');
        Route::post('/kurikulum/{kurikulum}/aktifkan', [Admin\Master\KurikulumController::class, 'aktifkan'])->name('kurikulum.aktifkan');
        Route::post('/kurikulum/{kurikulum}/mata-kuliah', [Admin\Master\KurikulumController::class, 'tambahMk'])->name('kurikulum.mk.tambah');
        Route::delete('/kurikulum/{kurikulum}/mata-kuliah/{mataKuliah}', [Admin\Master\KurikulumController::class, 'hapusMk'])->name('kurikulum.mk.hapus');

        Route::get('/mata-kuliah', [Admin\Master\MataKuliahController::class, 'index'])->name('mata-kuliah');
        Route::post('/mata-kuliah', [Admin\Master\MataKuliahController::class, 'store'])->name('mata-kuliah.store');
        Route::put('/mata-kuliah/{mataKuliah}', [Admin\Master\MataKuliahController::class, 'update'])->name('mata-kuliah.update');
        Route::delete('/mata-kuliah/{mataKuliah}', [Admin\Master\MataKuliahController::class, 'destroy'])->name('mata-kuliah.destroy');
        Route::post('/mata-kuliah/{mataKuliah}/prasyarat', [Admin\Master\MataKuliahController::class, 'tambahPrasyarat'])->name('mata-kuliah.prasyarat.tambah');
        Route::delete('/mata-kuliah/{mataKuliah}/prasyarat/{prasyarat}', [Admin\Master\MataKuliahController::class, 'hapusPrasyarat'])->name('mata-kuliah.prasyarat.hapus');

        Route::get('/ruang', [Admin\Master\RuangController::class, 'index'])->name('ruang');
        Route::post('/gedung', [Admin\Master\RuangController::class, 'storeGedung'])->name('gedung.store');
        Route::delete('/gedung/{gedung}', [Admin\Master\RuangController::class, 'destroyGedung'])->name('gedung.destroy');
        Route::post('/ruang', [Admin\Master\RuangController::class, 'storeRuang'])->name('ruang.store');
        Route::put('/ruang/{ruang}', [Admin\Master\RuangController::class, 'updateRuang'])->name('ruang.update');
        Route::delete('/ruang/{ruang}', [Admin\Master\RuangController::class, 'destroyRuang'])->name('ruang.destroy');
    });

/*
| Everything else in the admin portal does require a live semester — a Feeder
| sync or a graduation decision is meaningless without one to file it under.
*/
Route::middleware(['auth:staff', 'term.active'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/mahasiswa', Admin\MahasiswaController::class)->name('mahasiswa');

    Route::get('/kelas', [Admin\KelasController::class, 'index'])->name('kelas');
    Route::post('/kelas', [Admin\KelasController::class, 'buka'])->name('kelas.buka');
    Route::put('/kelas/{kelas}', [Admin\KelasController::class, 'perbarui'])->name('kelas.perbarui');
    Route::delete('/kelas/{kelas}', [Admin\KelasController::class, 'tutup'])->name('kelas.tutup');
    Route::post('/kelas/{kelas}/dosen', [Admin\KelasController::class, 'tugaskanDosen'])->name('kelas.dosen.tugaskan');
    Route::delete('/kelas/{kelas}/dosen/{dosen}', [Admin\KelasController::class, 'lepasDosen'])->name('kelas.dosen.lepas');
    Route::post('/kelas/{kelas}/jadwal', [Admin\KelasController::class, 'jadwalkan'])->name('kelas.jadwal');
    Route::delete('/kelas/{kelas}/jadwal/{jadwal}', [Admin\KelasController::class, 'hapusJadwal'])->name('kelas.jadwal.hapus');

    Route::get('/dosen', [Admin\DosenController::class, 'index'])->name('dosen');
    Route::post('/dosen', [Admin\DosenController::class, 'store'])->name('dosen.store');
    Route::put('/dosen/{dosen}', [Admin\DosenController::class, 'update'])->name('dosen.update');
    Route::post('/dosen/{dosen}/nonaktifkan', [Admin\DosenController::class, 'nonaktifkan'])->name('dosen.nonaktifkan');
    Route::post('/dosen/{dosen}/aktifkan', [Admin\DosenController::class, 'aktifkan'])->name('dosen.aktifkan');
    Route::post('/dosen/{dosen}/reset-sandi', [Admin\DosenController::class, 'resetKataSandi'])->name('dosen.reset-sandi');

    Route::get('/staf', [Admin\StaffController::class, 'index'])->name('staff');
    Route::post('/staf', [Admin\StaffController::class, 'store'])->name('staff.store');
    Route::put('/staf/{staff}', [Admin\StaffController::class, 'update'])->name('staff.update');
    Route::post('/staf/{staff}/nonaktifkan', [Admin\StaffController::class, 'nonaktifkan'])->name('staff.nonaktifkan');
    Route::post('/staf/{staff}/aktifkan', [Admin\StaffController::class, 'aktifkan'])->name('staff.aktifkan');
    Route::post('/staf/{staff}/reset-sandi', [Admin\StaffController::class, 'resetKataSandi'])->name('staff.reset-sandi');

    Route::get('/tutup-semester', [Admin\PenutupanSemesterController::class, 'index'])->name('tutup-semester');
    Route::post('/tutup-semester', [Admin\PenutupanSemesterController::class, 'tutup'])->name('tutup-semester.tutup');
    Route::post('/tutup-semester/{status}/buka', [Admin\PenutupanSemesterController::class, 'bukaKembali'])->name('tutup-semester.buka');

    Route::get('/koreksi-nilai', [Admin\KoreksiNilaiController::class, 'index'])->name('koreksi-nilai');
    Route::post('/koreksi-nilai/{nilai}', [Admin\KoreksiNilaiController::class, 'koreksi'])->name('koreksi-nilai.simpan');

    Route::get('/pengumuman', [Admin\PengumumanController::class, 'index'])->name('pengumuman');
    Route::post('/pengumuman', [Admin\PengumumanController::class, 'store'])->name('pengumuman.store');
    Route::put('/pengumuman/{pengumuman}', [Admin\PengumumanController::class, 'perbarui'])->name('pengumuman.perbarui');
    Route::post('/pengumuman/{pengumuman}/terbitkan', [Admin\PengumumanController::class, 'terbitkan'])->name('pengumuman.terbitkan');
    Route::post('/pengumuman/{pengumuman}/sematkan', [Admin\PengumumanController::class, 'sematkan'])->name('pengumuman.sematkan');
    Route::delete('/pengumuman/{pengumuman}', [Admin\PengumumanController::class, 'hapus'])->name('pengumuman.hapus');

    /*
    | Tugas akhir.
    |
    | Setiap rute memakai satu parameter saja. Rute berparameter dua menuntut
    | pemeriksaan bahwa keduanya berhubungan — otorisasi atas objek A tidak
    | mengatakan apa pun tentang objek B — dan bentuk seperti ini menghindarkan
    | kelas galat itu sejak awal. Lihat SECURITY.md §Otorisasi.
    */
    Route::get('/tugas-akhir', [Admin\TugasAkhirController::class, 'index'])->name('tugas-akhir');
    Route::get('/tugas-akhir/{tugasAkhir}', [Admin\TugasAkhirController::class, 'show'])->name('tugas-akhir.show');
    Route::post('/tugas-akhir/{tugasAkhir}/setujui', [Admin\TugasAkhirController::class, 'setujui'])->name('tugas-akhir.setujui');
    Route::post('/tugas-akhir/{tugasAkhir}/tolak', [Admin\TugasAkhirController::class, 'tolak'])->name('tugas-akhir.tolak');
    Route::post('/tugas-akhir/{tugasAkhir}/pembimbing', [Admin\TugasAkhirController::class, 'tetapkanPembimbing'])->name('tugas-akhir.pembimbing');
    Route::delete('/tugas-akhir/pembimbing/{pembimbing}', [Admin\TugasAkhirController::class, 'lepasPembimbing'])->name('tugas-akhir.pembimbing.lepas');
    Route::post('/tugas-akhir/{tugasAkhir}/ujian', [Admin\TugasAkhirController::class, 'jadwalkanUjian'])->name('tugas-akhir.ujian');
    Route::post('/tugas-akhir/ujian/{ujian}/hasil', [Admin\TugasAkhirController::class, 'catatHasil'])->name('tugas-akhir.ujian.hasil');
    Route::post('/tugas-akhir/ujian/{ujian}/batal', [Admin\TugasAkhirController::class, 'batalkanUjian'])->name('tugas-akhir.ujian.batal');
    Route::post('/tugas-akhir/{tugasAkhir}/selesai', [Admin\TugasAkhirController::class, 'selesaikan'])->name('tugas-akhir.selesai');
    Route::post('/tugas-akhir/{tugasAkhir}/batal', [Admin\TugasAkhirController::class, 'batalkan'])->name('tugas-akhir.batal');

    Route::get('/konversi', [Admin\KonversiController::class, 'index'])->name('konversi');
    Route::post('/konversi/{mahasiswa}', [Admin\KonversiController::class, 'ajukan'])->name('konversi.ajukan');
    Route::post('/konversi/{konversi}/setujui', [Admin\KonversiController::class, 'setujui'])->name('konversi.setujui');
    Route::post('/konversi/{konversi}/tolak', [Admin\KonversiController::class, 'tolak'])->name('konversi.tolak');
    Route::post('/konversi/{konversi}/cabut', [Admin\KonversiController::class, 'cabut'])->name('konversi.cabut');

    Route::get('/surat', [Admin\SuratController::class, 'index'])->name('surat');
    Route::post('/surat/skpi', [Admin\SuratController::class, 'terbitkanSkpi'])->name('surat.skpi');
    Route::post('/surat/{surat}/terbitkan', [Admin\SuratController::class, 'terbitkan'])->name('surat.terbitkan');
    Route::post('/surat/{surat}/tolak', [Admin\SuratController::class, 'tolak'])->name('surat.tolak');
    Route::post('/surat/{surat}/cabut', [Admin\SuratController::class, 'cabut'])->name('surat.cabut');
    Route::get('/surat/{surat}/unduh', [Admin\SuratController::class, 'unduh'])->name('surat.unduh');

    Route::get('/kurikulum-lanjutan', [Admin\KurikulumLanjutanController::class, 'index'])->name('kurikulum-lanjutan');
    Route::post('/kurikulum-lanjutan/padanan', [Admin\KurikulumLanjutanController::class, 'simpanPadanan'])->name('kurikulum-lanjutan.padanan');
    Route::delete('/kurikulum-lanjutan/padanan', [Admin\KurikulumLanjutanController::class, 'hapusPadanan'])->name('kurikulum-lanjutan.padanan.hapus');
    Route::post('/kurikulum-lanjutan/{kurikulum}/konsentrasi', [Admin\KurikulumLanjutanController::class, 'simpanKonsentrasi'])->name('kurikulum-lanjutan.konsentrasi');
    Route::post('/kurikulum-lanjutan/{kurikulum}/paket', [Admin\KurikulumLanjutanController::class, 'simpanPaket'])->name('kurikulum-lanjutan.paket');
    Route::post('/kurikulum-lanjutan/{kurikulum}/petakan', [Admin\KurikulumLanjutanController::class, 'petakanKonsentrasi'])->name('kurikulum-lanjutan.petakan');

    Route::get('/akuntansi', [Admin\AkuntansiController::class, 'index'])->name('akuntansi.index');
    Route::post('/akuntansi/kirim', [Admin\AkuntansiController::class, 'kirim'])->name('akuntansi.kirim');
    Route::post('/akuntansi/ulangi-semua', [Admin\AkuntansiController::class, 'ulangiSemua'])->name('akuntansi.ulangi-semua');
    Route::post('/akuntansi/{dokumen}/ulangi', [Admin\AkuntansiController::class, 'ulangi'])->name('akuntansi.ulangi');
    Route::get('/akuntansi/ekspor/jurnal', [Admin\AkuntansiController::class, 'eksporJurnal'])->name('akuntansi.ekspor');

    Route::get('/bkd', [Admin\BkdController::class, 'index'])->name('bkd.index');
    Route::post('/bkd/{laporan}/asesor', [Admin\BkdController::class, 'tetapkanAsesor'])->name('bkd.asesor');
    Route::post('/bkd/{laporan}/sahkan', [Admin\BkdController::class, 'sahkan'])->name('bkd.sahkan');
    Route::get('/bkd/{laporan}/unduh', [Admin\BkdController::class, 'unduh'])->name('bkd.unduh');
    Route::get('/bkd/ekspor/rekap', [Admin\BkdController::class, 'eksporRekap'])->name('bkd.ekspor.rekap');
    Route::get('/bkd/ekspor/kegiatan', [Admin\BkdController::class, 'eksporKegiatan'])->name('bkd.ekspor.kegiatan');
    Route::get('/bkd/ekspor/portofolio/{dosen}', [Admin\BkdController::class, 'eksporPortofolio'])->name('bkd.ekspor.portofolio');

    Route::get('/edom', [Admin\EdomController::class, 'index'])->name('edom.index');
    Route::get('/edom/{periode}/kelas/{kelas}', [Admin\EdomController::class, 'kelas'])->name('edom.kelas');
    Route::post('/edom/periode', [Admin\EdomController::class, 'simpanPeriode'])->name('edom.periode');
    Route::post('/edom/{periode}/status', [Admin\EdomController::class, 'ubahStatus'])->name('edom.status');
    Route::post('/edom/{periode}/pertanyaan', [Admin\EdomController::class, 'tambahPertanyaan'])->name('edom.pertanyaan');
    Route::post('/edom/{periode}/salin', [Admin\EdomController::class, 'salinPertanyaan'])->name('edom.salin');
    Route::delete('/edom/pertanyaan/{pertanyaan}', [Admin\EdomController::class, 'hapusPertanyaan'])->name('edom.pertanyaan.hapus');

    Route::get('/log', Admin\LogAktivitasController::class)->name('log');

    Route::get('/pengaturan', [Admin\PengaturanController::class, 'index'])->name('pengaturan');
    Route::put('/pengaturan', [Admin\PengaturanController::class, 'simpan'])->name('pengaturan.simpan');
    Route::put('/pengaturan/dokumen', [Admin\PengaturanController::class, 'simpanDokumen'])
        ->name('pengaturan.dokumen');

    Route::get('/cetak/ktm/{mahasiswa}', [Admin\CetakController::class, 'ktm'])->name('cetak.ktm');
    Route::get('/cetak/kartu-ujian/{krs}', [Admin\CetakController::class, 'kartuUjian'])
        ->name('cetak.kartu-ujian');
    Route::get('/cetak/absensi/{kelas}', [Admin\CetakController::class, 'absensi'])->name('cetak.absensi');
    Route::get('/cetak/jurnal/{kelas}', [Admin\CetakController::class, 'jurnal'])->name('cetak.jurnal');

    Route::get('/tarif', [Admin\TarifController::class, 'index'])->name('tarif');
    Route::post('/tarif', [Admin\TarifController::class, 'store'])->name('tarif.store');
    Route::put('/tarif/{tarif}', [Admin\TarifController::class, 'perbarui'])->name('tarif.perbarui');
    Route::delete('/tarif/{tarif}', [Admin\TarifController::class, 'hapus'])->name('tarif.hapus');

    Route::get('/keuangan', [Admin\KeuanganController::class, 'index'])->name('keuangan');
    Route::post('/keuangan/pratinjau', [Admin\KeuanganController::class, 'pratinjau'])->name('keuangan.pratinjau');
    Route::post('/keuangan/terbitkan', [Admin\KeuanganController::class, 'terbitkan'])->name('keuangan.terbitkan');
    Route::post('/keuangan/tagihan/{tagihan}/pembayaran', [Admin\KeuanganController::class, 'catatPembayaran'])->name('keuangan.pembayaran');
    Route::post('/keuangan/pembayaran/{pembayaran}/batal', [Admin\KeuanganController::class, 'batalkanPembayaran'])->name('keuangan.pembayaran.batal');
    Route::post('/keuangan/tagihan/{tagihan}/keringanan', [Admin\KeuanganController::class, 'keringanan'])->name('keuangan.keringanan');
    Route::post('/keuangan/potongan/{item}/hapus', [Admin\KeuanganController::class, 'hapusPotongan'])->name('keuangan.potongan.hapus');

    Route::get('/beasiswa', [Admin\BeasiswaController::class, 'index'])->name('beasiswa');
    Route::post('/beasiswa', [Admin\BeasiswaController::class, 'simpanSkema'])->name('beasiswa.skema');
    Route::post('/beasiswa/{beasiswa}/tetapkan', [Admin\BeasiswaController::class, 'tetapkan'])->name('beasiswa.tetapkan');
    Route::post('/beasiswa/penerima/{penerima}/cabut', [Admin\BeasiswaController::class, 'cabut'])->name('beasiswa.cabut');

    Route::get('/cuti', [Admin\CutiController::class, 'index'])->name('cuti');
    Route::post('/cuti', [Admin\CutiController::class, 'ajukan'])->name('cuti.ajukan');
    Route::post('/cuti/{cuti}/setujui', [Admin\CutiController::class, 'setujui'])->name('cuti.setujui');
    Route::post('/cuti/{cuti}/tolak', [Admin\CutiController::class, 'tolak'])->name('cuti.tolak');
    Route::post('/cuti/{cuti}/aktifkan', [Admin\CutiController::class, 'aktifkanKembali'])->name('cuti.aktifkan');

    Route::get('/pmb', [Admin\PmbController::class, 'index'])->name('pmb');
    Route::post('/pmb/gelombang', [Admin\PmbController::class, 'storeGelombang'])->name('pmb.gelombang.store');
    Route::post('/pmb/{pendaftar}/luluskan', [Admin\PmbController::class, 'luluskan'])->name('pmb.luluskan');
    Route::post('/pmb/{pendaftar}/tidak-luluskan', [Admin\PmbController::class, 'tidakLuluskan'])->name('pmb.tidak-luluskan');
    Route::post('/pmb/{pendaftar}/daftar-ulang', [Admin\PmbController::class, 'daftarUlang'])->name('pmb.daftar-ulang');
    Route::post('/pmb/{pendaftar}/berkas', [Admin\PmbController::class, 'unggahBerkas'])->name('pmb.berkas.unggah');
    Route::post('/pmb/berkas/{berkas}/verifikasi', [Admin\PmbController::class, 'verifikasiBerkas'])->name('pmb.berkas.verifikasi');
    Route::delete('/pmb/berkas/{berkas}', [Admin\PmbController::class, 'hapusBerkas'])->name('pmb.berkas.hapus');

    Route::get('/feeder', [Admin\FeederController::class, 'index'])->name('feeder');
    Route::post('/feeder/validasi', [Admin\FeederController::class, 'validasi'])->name('feeder.validasi');
    Route::post('/feeder/referensi', [Admin\FeederController::class, 'tarikReferensi'])->name('feeder.referensi');
    Route::post('/feeder/{entity}/jalankan', [Admin\FeederController::class, 'jalankan'])->name('feeder.jalankan');
    Route::post('/feeder/{entity}/ulangi', [Admin\FeederController::class, 'ulangi'])->name('feeder.ulangi');

    Route::get('/yudisium', [Admin\YudisiumController::class, 'index'])->name('yudisium');
    Route::post('/yudisium/ajukan', [Admin\YudisiumController::class, 'ajukan'])->name('yudisium.ajukan');
    Route::post('/yudisium/{yudisium}/tetapkan', [Admin\YudisiumController::class, 'tetapkan'])->name('yudisium.tetapkan');
    Route::post('/yudisium/{yudisium}/batalkan', [Admin\YudisiumController::class, 'batalkan'])->name('yudisium.batalkan');

    Route::get('/wisuda', [Admin\WisudaController::class, 'index'])->name('wisuda');
    Route::post('/wisuda/periode', [Admin\WisudaController::class, 'storePeriode'])->name('wisuda.periode.store');
    Route::post('/wisuda/periode/{periode}/buka', [Admin\WisudaController::class, 'bukaPendaftaran'])->name('wisuda.buka');
    Route::post('/wisuda/periode/{periode}/tutup', [Admin\WisudaController::class, 'tutupPendaftaran'])->name('wisuda.tutup');
    Route::post('/wisuda/periode/{periode}/daftarkan', [Admin\WisudaController::class, 'daftarkan'])->name('wisuda.daftarkan');
    Route::post('/wisuda/periode/{periode}/daftarkan-massal', [Admin\WisudaController::class, 'daftarkanMassal'])->name('wisuda.daftarkan-massal');
    Route::post('/wisuda/periode/{periode}/ijazah', [Admin\WisudaController::class, 'terbitkanIjazah'])->name('wisuda.ijazah');
    Route::delete('/wisuda/peserta/{peserta}', [Admin\WisudaController::class, 'batalkan'])->name('wisuda.peserta.batal');

    Route::get('/data-iku', [Admin\IkuRecordController::class, 'index'])->name('iku-records');
    Route::post('/data-iku/aktivitas/{aktivitas}/verifikasi', [Admin\IkuRecordController::class, 'verifikasiAktivitas'])
        ->name('iku-records.aktivitas.verifikasi');
    Route::post('/data-iku/aktivitas/{aktivitas}/batal', [Admin\IkuRecordController::class, 'batalkanAktivitas'])
        ->name('iku-records.aktivitas.batal');
    Route::post('/data-iku/penugasan/{penugasan}/verifikasi', [Admin\IkuRecordController::class, 'verifikasiPenugasan'])
        ->name('iku-records.penugasan.verifikasi');
    Route::post('/data-iku/penugasan/{penugasan}/batal', [Admin\IkuRecordController::class, 'batalkanPenugasan'])
        ->name('iku-records.penugasan.batal');

    Route::get('/bridge', [Admin\BridgeController::class, 'index'])->name('bridge');
    Route::post('/bridge/pengiriman/{pengiriman}/kirim-ulang', [Admin\BridgeController::class, 'kirimUlang'])
        ->name('bridge.kirim-ulang');
});
