<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\DuaFaktorController;
use App\Http\Controllers\Auth\IdpLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\Dosen;
use App\Http\Controllers\Helpdesk;
use App\Http\Controllers\KalenderController;
use App\Http\Controllers\KalenderIcsController;
use App\Http\Controllers\Mahasiswa;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PengumumanPortalController;
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
| Langganan Kalender Akademik — publik, bertoken
|--------------------------------------------------------------------------
|
| Google Calendar dan Outlook mengambil feed ini dari server mereka sendiri,
| tanpa sesi siapa pun. Token di URL adalah satu-satunya pemeriksaan akses,
| jadi isinya tidak memuat nama, NIM, atau surel — lihat
| LanggananKalenderService.
|
*/
Route::get('/kalender/{token}.ics', KalenderIcsController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:60,1')
    ->name('kalender.ics');

/*
|--------------------------------------------------------------------------
| Pusat Bantuan — publik
|--------------------------------------------------------------------------
|
| Hanya FAQ yang sudah terbit, tanpa login: orang yang paling perlu membaca
| "bagaimana mengatur ulang kata sandi" adalah yang sedang tidak bisa masuk.
| Mengajukan tiket tetap lewat portal masing-masing.
|
*/
Route::get('/bantuan', Helpdesk\FaqPublikController::class)->name('bantuan');

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

/*
 | Tantangan dua langkah untuk staf.
 |
 | Di luar auth:staff dengan sengaja: pemiliknya sudah membuktikan kata sandi
 | tetapi BELUM masuk. Identitas yang menunggu ada di sesi, bukan di URL atau
 | ruas formulir.
 */
Route::get('/masuk/dua-langkah', [DuaFaktorController::class, 'tantangan'])->name('dua-faktor.tantangan');
Route::post('/masuk/dua-langkah', [DuaFaktorController::class, 'verifikasi'])->name('dua-faktor.verifikasi');

/*
 | Pemasangan, dari akun yang sudah masuk.
 |
 | Tanpa term.active: ketika DUA_FAKTOR_WAJIB menyala, staf digiring ke sini
 | sebelum dapat membuka apa pun — dan tahun akademik yang belum aktif tidak
 | boleh ikut memblokirnya.
 */
Route::middleware('auth:staff')->prefix('admin/dua-langkah')->name('dua-faktor.')->group(function (): void {
    Route::get('/', [DuaFaktorController::class, 'kelola'])->name('kelola');
    Route::post('/konfirmasi', [DuaFaktorController::class, 'konfirmasi'])->name('konfirmasi');
    Route::post('/pemulihan', [DuaFaktorController::class, 'pemulihanBaru'])->name('pemulihan');
    Route::post('/matikan', [DuaFaktorController::class, 'matikan'])->name('matikan');
});
Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');

/*
 | Masuk lewat IdP kampus (OIDC). Hanya terdaftar bila IDP_ENABLED=true —
 | tombolnya di halaman masuk mengikuti syarat yang sama, jadi instalasi tanpa
 | IdP tidak punya pintu yang mengarah ke mana-mana.
 */
if (config('idp.enabled')) {
    Route::get('/masuk/idp', [IdpLoginController::class, 'redirect'])->name('idp.redirect');
    Route::get('/masuk/idp/callback', [IdpLoginController::class, 'callback'])->name('idp.callback');
}

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
    Route::post('/krs/revisi', [Mahasiswa\KrsController::class, 'revisi'])->name('krs.revisi');

    Route::get('/jadwal', Mahasiswa\JadwalController::class)->name('jadwal');
    Route::get('/jadwal-ujian', Mahasiswa\JadwalUjianController::class)->name('jadwal-ujian');

    Route::get('/kalender', [KalenderController::class, 'index'])->name('kalender');
    Route::post('/kalender/langganan', [KalenderController::class, 'langganan'])->name('kalender.langganan');
    Route::delete('/kalender/langganan', [KalenderController::class, 'cabut'])->name('kalender.langganan.cabut');

    Route::get('/khs', Mahasiswa\KhsController::class)->name('khs');
    Route::get('/khs/transkrip', Mahasiswa\TranskripController::class)->name('transkrip');
    Route::get('/khs/{term}/cetak', [Mahasiswa\KhsController::class, 'cetak'])->name('khs.cetak');
    Route::get('/pengumuman', [PengumumanPortalController::class, 'index'])->name('pengumuman.index');
    Route::get('/pengumuman/{pengumuman}', [PengumumanPortalController::class, 'show'])->name('pengumuman.show');
    Route::get('/tagihan', Mahasiswa\TagihanController::class)->name('tagihan');
    Route::post('/tagihan/{tagihan}/dispensasi', Mahasiswa\DispensasiController::class)->name('tagihan.dispensasi');

    Route::get('/presensi', [Mahasiswa\PresensiMandiriController::class, 'form'])->name('presensi');
    Route::post('/presensi', [Mahasiswa\PresensiMandiriController::class, 'catat'])->name('presensi.catat');

    Route::get('/tugas-akhir', [Mahasiswa\TugasAkhirController::class, 'index'])->name('tugas-akhir');
    Route::post('/tugas-akhir', [Mahasiswa\TugasAkhirController::class, 'ajukan'])->name('tugas-akhir.ajukan');
    Route::post('/tugas-akhir/bimbingan', [Mahasiswa\TugasAkhirController::class, 'catatBimbingan'])->name('tugas-akhir.bimbingan');
    Route::post('/tugas-akhir/proposal', [Mahasiswa\TugasAkhirController::class, 'unggahProposal'])->name('tugas-akhir.proposal');
    Route::delete('/tugas-akhir/bimbingan/{bimbingan}', [Mahasiswa\TugasAkhirController::class, 'hapusBimbingan'])->name('tugas-akhir.bimbingan.hapus');

    Route::get('/capaian', Mahasiswa\PenguasaanController::class)->name('capaian');

    // KKN/PKL (#37). Pemilik partisipasi dan logbook selalu yang sedang masuk.
    Route::get('/kegiatan-lapangan', [Mahasiswa\KegiatanLapanganController::class, 'index'])->name('kegiatan-lapangan');
    Route::post('/kegiatan-lapangan/{kegiatan}/daftar', [Mahasiswa\KegiatanLapanganController::class, 'daftar'])->name('kegiatan-lapangan.daftar');
    Route::post('/kegiatan-lapangan/logbook/{aktivitas}', [Mahasiswa\KegiatanLapanganController::class, 'catatLogbook'])->name('kegiatan-lapangan.logbook');
    Route::delete('/kegiatan-lapangan/logbook/{logbook}', [Mahasiswa\KegiatanLapanganController::class, 'hapusLogbook'])->name('kegiatan-lapangan.logbook.hapus');

    // Berparameter periode, bukan orang: pemilik pendaftarannya selalu yang
    // sedang masuk.
    Route::get('/wisuda', [Mahasiswa\WisudaController::class, 'index'])->name('wisuda');
    Route::post('/wisuda/{periode}', [Mahasiswa\WisudaController::class, 'daftar'])->name('wisuda.daftar');
    Route::delete('/wisuda/{periode}', [Mahasiswa\WisudaController::class, 'batal'])->name('wisuda.batal');

    Route::get('/edom', [Mahasiswa\EdomController::class, 'index'])->name('edom');
    Route::post('/edom', [Mahasiswa\EdomController::class, 'kirim'])->name('edom.kirim');

    Route::get('/surat', [Mahasiswa\SuratController::class, 'index'])->name('surat');
    Route::post('/surat', [Mahasiswa\SuratController::class, 'ajukan'])->name('surat.ajukan');
    Route::get('/surat/{surat}/unduh', [Mahasiswa\SuratController::class, 'unduh'])->name('surat.unduh');

    Route::get('/helpdesk', [Helpdesk\TiketPemohonController::class, 'index'])->name('helpdesk');
    Route::post('/helpdesk', [Helpdesk\TiketPemohonController::class, 'store'])->name('helpdesk.store');
    Route::get('/helpdesk/{tiket}', [Helpdesk\TiketPemohonController::class, 'show'])->name('helpdesk.show');
    Route::post('/helpdesk/{tiket}/balas', [Helpdesk\TiketPemohonController::class, 'balas'])->name('helpdesk.balas');

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

    // Satu controller dengan portal mahasiswa: layarnya sama, hanya guard dan
    // awalan rutenya yang berbeda.
    Route::get('/helpdesk', [Helpdesk\TiketPemohonController::class, 'index'])->name('helpdesk');
    Route::post('/helpdesk', [Helpdesk\TiketPemohonController::class, 'store'])->name('helpdesk.store');
    Route::get('/helpdesk/{tiket}', [Helpdesk\TiketPemohonController::class, 'show'])->name('helpdesk.show');
    Route::post('/helpdesk/{tiket}/balas', [Helpdesk\TiketPemohonController::class, 'balas'])->name('helpdesk.balas');

    Route::get('/pengumuman', [PengumumanPortalController::class, 'index'])->name('pengumuman.index');
    Route::get('/pengumuman/{pengumuman}', [PengumumanPortalController::class, 'show'])->name('pengumuman.show');

    Route::get('/kelas', Dosen\KelasController::class)->name('kelas');
    Route::get('/jadwal-ujian', Dosen\JadwalUjianController::class)->name('jadwal-ujian');

    Route::get('/kalender', [KalenderController::class, 'index'])->name('kalender');
    Route::post('/kalender/langganan', [KalenderController::class, 'langganan'])->name('kalender.langganan');
    Route::delete('/kalender/langganan', [KalenderController::class, 'cabut'])->name('kalender.langganan.cabut');

    Route::get('/nilai', [Dosen\NilaiController::class, 'index'])->name('nilai');
    Route::get('/nilai/{kelas}', [Dosen\NilaiController::class, 'edit'])->name('nilai.kelas');
    Route::post('/nilai/{kelas}', [Dosen\NilaiController::class, 'simpan'])->name('nilai.simpan');
    Route::post('/nilai/{kelas}/rencana', [Dosen\NilaiController::class, 'rencana'])->name('nilai.rencana');
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

    Route::get('/kegiatan-lapangan', [Dosen\KegiatanLapanganController::class, 'index'])->name('kegiatan-lapangan');
    Route::get('/kegiatan-lapangan/{kelompok}', [Dosen\KegiatanLapanganController::class, 'show'])->name('kegiatan-lapangan.show');
    Route::post('/kegiatan-lapangan/{kelompok}/nilai', [Dosen\KegiatanLapanganController::class, 'nilai'])->name('kegiatan-lapangan.nilai');
    Route::post('/kegiatan-lapangan/logbook/{logbook}/periksa', [Dosen\KegiatanLapanganController::class, 'periksa'])->name('kegiatan-lapangan.periksa');

    // Riwayat kepegawaian: {jenis} memilih tabel, {riwayat} adalah UUID baris
    // di tabel itu — diikat di AppServiceProvider, dicek pemiliknya oleh policy.
    Route::get('/kepegawaian', [Dosen\KepegawaianController::class, 'index'])->name('kepegawaian');
    Route::post('/kepegawaian/{jenis}', [Dosen\KepegawaianController::class, 'store'])->name('kepegawaian.store');
    Route::put('/kepegawaian/{jenis}/{riwayat}', [Dosen\KepegawaianController::class, 'update'])->name('kepegawaian.update');
    Route::delete('/kepegawaian/{jenis}/{riwayat}', [Dosen\KepegawaianController::class, 'destroy'])->name('kepegawaian.destroy');

    Route::get('/angka-kredit', Dosen\AngkaKreditController::class)->name('angka-kredit');

    Route::get('/tugas-akhir', [Dosen\TugasAkhirController::class, 'index'])->name('tugas-akhir');
    Route::get('/tugas-akhir/{tugasAkhir}', [Dosen\TugasAkhirController::class, 'show'])->name('tugas-akhir.show');
    Route::get('/tugas-akhir/proposal/{proposal}', [Dosen\TugasAkhirController::class, 'proposal'])->name('tugas-akhir.proposal');
    Route::post('/tugas-akhir/proposal/{proposal}/telaah', [Dosen\TugasAkhirController::class, 'telaahProposal'])->name('tugas-akhir.proposal.telaah');
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

// Dokumen riwayat kepegawaian: dosen pemiliknya dan staf kepegawaian.
// RiwayatKepegawaianPolicy::view yang memutuskan, bukan guard-nya.
Route::middleware('auth:staff,dosen')->prefix('berkas/kepegawaian')->name('berkas.')
    ->group(function (): void {
        Route::get('/{jenis}/{riwayat}', [BerkasController::class, 'riwayatKepegawaian'])->name('kepegawaian');
    });

// Bukti kegiatan dosen (SK, undangan, bukti terbit): dosen pemiliknya dan staf
// yang memverifikasinya — diputuskan PenugasanDosenPolicy.
Route::middleware('auth:staff,dosen')->prefix('berkas')->name('berkas.')->group(function (): void {
    Route::get('/kegiatan-dosen/{penugasan}', [BerkasController::class, 'penugasanDosen'])->name('kegiatan-dosen');
});

// Lampiran helpdesk dapat diunggah dosen juga, maka ketiga guard. Siapa yang
// boleh membukanya diputuskan HelpdeskTiketPolicy, bukan oleh guard.
Route::middleware('auth:staff,dosen,mahasiswa')->prefix('berkas/helpdesk')->name('berkas.helpdesk.')
    ->group(function (): void {
        Route::get('/tiket/{tiket}', [BerkasController::class, 'helpdeskTiket'])->name('tiket');
        Route::get('/balasan/{balasan}', [BerkasController::class, 'helpdeskBalasan'])->name('balasan');
    });

// Lampiran logbook KKN/PKL: penulisnya, dosen pembimbing lapangan kelompoknya,
// dan staf — diputuskan KegiatanLapanganLogbookPolicy.
Route::middleware('auth:staff,dosen,mahasiswa')->prefix('berkas')->name('berkas.')->group(function (): void {
    Route::get('/kegiatan-lapangan/{logbook}', [BerkasController::class, 'logbookKegiatan'])->name('logbook-kegiatan');

    // Proposal tugas akhir (#38): pemiliknya, calon/pembimbingnya, dan staf
    // tugas akhir — diputuskan ProposalPolicy.
    Route::get('/proposal-ta/{proposal}', [BerkasController::class, 'proposalTugasAkhir'])->name('proposal-ta');
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

    // Dosen wali: penetapan per prodi (massal atau merata) dan per mahasiswa.
    Route::get('/dosen-wali', [Admin\DosenWaliController::class, 'index'])->name('dosen-wali');
    Route::post('/dosen-wali/massal', [Admin\DosenWaliController::class, 'massal'])->name('dosen-wali.massal');
    Route::post('/dosen-wali/merata', [Admin\DosenWaliController::class, 'merata'])->name('dosen-wali.merata');
    Route::post('/mahasiswa/{mahasiswa}/dosen-wali', [Admin\DosenWaliController::class, 'tetapkan'])->name('dosen-wali.tetapkan');

    // Detail satu mahasiswa: baca saja, plus cetak KHS/transkrip dari service
    // yang sama dengan portal mahasiswa.
    Route::get('/mahasiswa/{mahasiswa}', [Admin\MahasiswaDetailController::class, 'show'])->name('mahasiswa.show');
    Route::get('/mahasiswa/{mahasiswa}/khs/{term}', [Admin\MahasiswaDetailController::class, 'khs'])->name('mahasiswa.khs');
    Route::get('/mahasiswa/{mahasiswa}/transkrip', [Admin\MahasiswaDetailController::class, 'transkrip'])->name('mahasiswa.transkrip');

    Route::get('/kelas', [Admin\KelasController::class, 'index'])->name('kelas');
    Route::post('/kelas', [Admin\KelasController::class, 'buka'])->name('kelas.buka');
    Route::put('/kelas/{kelas}', [Admin\KelasController::class, 'perbarui'])->name('kelas.perbarui');
    Route::delete('/kelas/{kelas}', [Admin\KelasController::class, 'tutup'])->name('kelas.tutup');
    Route::post('/kelas/{kelas}/dosen', [Admin\KelasController::class, 'tugaskanDosen'])->name('kelas.dosen.tugaskan');
    Route::delete('/kelas/{kelas}/dosen/{dosen}', [Admin\KelasController::class, 'lepasDosen'])->name('kelas.dosen.lepas');
    Route::post('/kelas/{kelas}/jadwal', [Admin\KelasController::class, 'jadwalkan'])->name('kelas.jadwal');
    Route::delete('/kelas/{kelas}/jadwal/{jadwal}', [Admin\KelasController::class, 'hapusJadwal'])->name('kelas.jadwal.hapus');

    // UTS/UAS. Susun dan pratinjau sebelum {jadwal}, agar tidak tertangkap
    // sebagai UUID.
    Route::get('/jadwal-ujian', [Admin\JadwalUjianController::class, 'index'])->name('jadwal-ujian.index');
    Route::get('/jadwal-ujian/baru', [Admin\JadwalUjianController::class, 'create'])->name('jadwal-ujian.create');
    Route::post('/jadwal-ujian', [Admin\JadwalUjianController::class, 'store'])->name('jadwal-ujian.store');
    Route::get('/jadwal-ujian/susun', [Admin\JadwalUjianController::class, 'susun'])->name('jadwal-ujian.susun');
    Route::get('/jadwal-ujian/susun/pratinjau', [Admin\JadwalUjianController::class, 'pratinjau'])->name('jadwal-ujian.pratinjau');
    Route::post('/jadwal-ujian/susun', [Admin\JadwalUjianController::class, 'simpanSusun'])->name('jadwal-ujian.susun.simpan');
    Route::post('/jadwal-ujian/terbitkan', [Admin\JadwalUjianController::class, 'terbitkan'])->name('jadwal-ujian.terbitkan');
    Route::get('/jadwal-ujian/{jadwal}', [Admin\JadwalUjianController::class, 'show'])->name('jadwal-ujian.show');
    Route::get('/jadwal-ujian/{jadwal}/ubah', [Admin\JadwalUjianController::class, 'edit'])->name('jadwal-ujian.edit');
    Route::put('/jadwal-ujian/{jadwal}', [Admin\JadwalUjianController::class, 'update'])->name('jadwal-ujian.update');
    Route::delete('/jadwal-ujian/{jadwal}', [Admin\JadwalUjianController::class, 'destroy'])->name('jadwal-ujian.destroy');

    // Pemanfaatan ruang: laporan baca-saja dari jadwal kuliah dan ujian terbit.
    Route::get('/pemanfaatan-ruang', [Admin\PemanfaatanRuangController::class, 'index'])->name('pemanfaatan-ruang.index');
    Route::get('/pemanfaatan-ruang/ekspor', [Admin\PemanfaatanRuangController::class, 'ekspor'])->name('pemanfaatan-ruang.ekspor');
    Route::get('/pemanfaatan-ruang/ruang-kosong', [Admin\PemanfaatanRuangController::class, 'ruangKosong'])->name('pemanfaatan-ruang.kosong');

    // Kalender akademik: tampilan bersama ketiga portal, plus layar kelola
    // untuk peristiwa yang tidak dimiliki modul lain. "baru" sebelum
    // {peristiwa}, agar tidak tertangkap sebagai UUID.
    Route::get('/kalender', [KalenderController::class, 'index'])->name('kalender');
    Route::post('/kalender/langganan', [KalenderController::class, 'langganan'])->name('kalender.langganan');
    Route::delete('/kalender/langganan', [KalenderController::class, 'cabut'])->name('kalender.langganan.cabut');
    Route::get('/kalender/peristiwa', [Admin\PeristiwaKalenderController::class, 'index'])->name('kalender.peristiwa.index');
    Route::get('/kalender/peristiwa/baru', [Admin\PeristiwaKalenderController::class, 'create'])->name('kalender.peristiwa.create');
    Route::post('/kalender/peristiwa', [Admin\PeristiwaKalenderController::class, 'store'])->name('kalender.peristiwa.store');
    Route::get('/kalender/peristiwa/{peristiwa}/ubah', [Admin\PeristiwaKalenderController::class, 'edit'])->name('kalender.peristiwa.edit');
    Route::put('/kalender/peristiwa/{peristiwa}', [Admin\PeristiwaKalenderController::class, 'update'])->name('kalender.peristiwa.update');
    Route::delete('/kalender/peristiwa/{peristiwa}', [Admin\PeristiwaKalenderController::class, 'destroy'])->name('kalender.peristiwa.destroy');

    Route::get('/dosen', [Admin\DosenController::class, 'index'])->name('dosen');
    Route::post('/dosen', [Admin\DosenController::class, 'store'])->name('dosen.store');
    Route::put('/dosen/{dosen}', [Admin\DosenController::class, 'update'])->name('dosen.update');
    Route::post('/dosen/{dosen}/nonaktifkan', [Admin\DosenController::class, 'nonaktifkan'])->name('dosen.nonaktifkan');
    Route::post('/dosen/{dosen}/aktifkan', [Admin\DosenController::class, 'aktifkan'])->name('dosen.aktifkan');
    Route::post('/dosen/{dosen}/reset-sandi', [Admin\DosenController::class, 'resetKataSandi'])->name('dosen.reset-sandi');

    Route::get('/kepegawaian/validasi', [Admin\RiwayatKepegawaianController::class, 'antrean'])->name('kepegawaian.antrean');
    Route::get('/dosen/{dosen}/kepegawaian', [Admin\RiwayatKepegawaianController::class, 'show'])->name('dosen.kepegawaian');
    Route::post('/dosen/{dosen}/kepegawaian/{jenis}', [Admin\RiwayatKepegawaianController::class, 'store'])->name('dosen.kepegawaian.store');
    Route::put('/kepegawaian/{jenis}/{riwayat}', [Admin\RiwayatKepegawaianController::class, 'update'])->name('kepegawaian.update');
    Route::delete('/kepegawaian/{jenis}/{riwayat}', [Admin\RiwayatKepegawaianController::class, 'destroy'])->name('kepegawaian.destroy');
    Route::post('/kepegawaian/{jenis}/{riwayat}/setujui', [Admin\RiwayatKepegawaianController::class, 'setujui'])->name('kepegawaian.setujui');
    Route::post('/kepegawaian/{jenis}/{riwayat}/tolak', [Admin\RiwayatKepegawaianController::class, 'tolak'])->name('kepegawaian.tolak');

    // Buku besar angka kredit. Rute statis didahulukan supaya "ekspor" tidak
    // terbaca sebagai UUID dosen.
    Route::get('/angka-kredit', [Admin\AngkaKreditController::class, 'index'])->name('angka-kredit.index');
    Route::get('/angka-kredit/ekspor', [Admin\AngkaKreditController::class, 'ekspor'])->name('angka-kredit.ekspor');
    Route::post('/angka-kredit/susun', [Admin\AngkaKreditController::class, 'susunSemua'])->name('angka-kredit.susun-semua');
    Route::delete('/angka-kredit/baris/{baris}', [Admin\AngkaKreditController::class, 'destroy'])->name('angka-kredit.destroy');
    Route::get('/angka-kredit/{dosen}', [Admin\AngkaKreditController::class, 'show'])->name('angka-kredit.show');
    Route::post('/angka-kredit/{dosen}/susun', [Admin\AngkaKreditController::class, 'susun'])->name('angka-kredit.susun');
    Route::post('/angka-kredit/{dosen}/manual', [Admin\AngkaKreditController::class, 'store'])->name('angka-kredit.store');
    Route::put('/angka-kredit/{dosen}/nuptk', [Admin\AngkaKreditController::class, 'nuptk'])->name('angka-kredit.nuptk');

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

    // Papan baca untuk setiap staf, terpisah dari layar kelola di bawahnya
    // yang menuntut izin pengaturan. Didaftarkan lebih dulu agar "papan"
    // tidak pernah dibaca sebagai UUID.
    Route::get('/pengumuman/papan', [PengumumanPortalController::class, 'index'])->name('papan-pengumuman.index');
    Route::get('/pengumuman/papan/{pengumuman}', [PengumumanPortalController::class, 'show'])->name('papan-pengumuman.show');
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
    Route::post('/tugas-akhir/proposal/{proposal}/telaah', [Admin\TugasAkhirController::class, 'telaahProposal'])->name('tugas-akhir.proposal.telaah');
    Route::post('/tugas-akhir/{tugasAkhir}/pembimbing', [Admin\TugasAkhirController::class, 'tetapkanPembimbing'])->name('tugas-akhir.pembimbing');
    Route::delete('/tugas-akhir/pembimbing/{pembimbing}', [Admin\TugasAkhirController::class, 'lepasPembimbing'])->name('tugas-akhir.pembimbing.lepas');
    Route::post('/tugas-akhir/{tugasAkhir}/ujian', [Admin\TugasAkhirController::class, 'jadwalkanUjian'])->name('tugas-akhir.ujian');
    Route::post('/tugas-akhir/ujian/{ujian}/hasil', [Admin\TugasAkhirController::class, 'catatHasil'])->name('tugas-akhir.ujian.hasil');
    Route::post('/tugas-akhir/ujian/{ujian}/batal', [Admin\TugasAkhirController::class, 'batalkanUjian'])->name('tugas-akhir.ujian.batal');
    Route::post('/tugas-akhir/{tugasAkhir}/selesai', [Admin\TugasAkhirController::class, 'selesaikan'])->name('tugas-akhir.selesai');
    Route::post('/tugas-akhir/{tugasAkhir}/batal', [Admin\TugasAkhirController::class, 'batalkan'])->name('tugas-akhir.batal');

    /*
    | KKN/PKL (#37). Satu parameter per rute, seperti tugas akhir di atas:
    | rute kelompok, lokasi, dan peserta mencari kegiatannya dari rekaman itu
    | sendiri. `baru` didaftarkan sebelum `{kegiatan}`.
    */
    Route::get('/kegiatan-lapangan', [Admin\KegiatanLapanganController::class, 'index'])->name('kegiatan-lapangan.index');
    Route::get('/kegiatan-lapangan/baru', [Admin\KegiatanLapanganController::class, 'create'])->name('kegiatan-lapangan.create');
    Route::post('/kegiatan-lapangan', [Admin\KegiatanLapanganController::class, 'store'])->name('kegiatan-lapangan.store');
    Route::get('/kegiatan-lapangan/{kegiatan}', [Admin\KegiatanLapanganController::class, 'show'])->name('kegiatan-lapangan.show');
    Route::get('/kegiatan-lapangan/{kegiatan}/ubah', [Admin\KegiatanLapanganController::class, 'edit'])->name('kegiatan-lapangan.edit');
    Route::put('/kegiatan-lapangan/{kegiatan}', [Admin\KegiatanLapanganController::class, 'update'])->name('kegiatan-lapangan.update');
    Route::post('/kegiatan-lapangan/{kegiatan}/lokasi', [Admin\KegiatanLapanganController::class, 'simpanLokasi'])->name('kegiatan-lapangan.lokasi');
    Route::delete('/kegiatan-lapangan/lokasi/{lokasi}', [Admin\KegiatanLapanganController::class, 'hapusLokasi'])->name('kegiatan-lapangan.lokasi.hapus');
    Route::post('/kegiatan-lapangan/{kegiatan}/peserta', [Admin\KegiatanLapanganController::class, 'tambahPeserta'])->name('kegiatan-lapangan.peserta');
    Route::delete('/kegiatan-lapangan/peserta/{aktivitas}', [Admin\KegiatanLapanganController::class, 'keluarkanPeserta'])->name('kegiatan-lapangan.peserta.keluar');
    Route::post('/kegiatan-lapangan/peserta/{aktivitas}/pindah', [Admin\KegiatanLapanganController::class, 'pindahkan'])->name('kegiatan-lapangan.peserta.pindah');
    Route::post('/kegiatan-lapangan/{kegiatan}/bentuk-kelompok', [Admin\KegiatanLapanganController::class, 'bentukOtomatis'])->name('kegiatan-lapangan.bentuk');
    Route::post('/kegiatan-lapangan/{kegiatan}/kelompok', [Admin\KegiatanLapanganController::class, 'buatKelompok'])->name('kegiatan-lapangan.kelompok');
    Route::put('/kegiatan-lapangan/kelompok/{kelompok}', [Admin\KegiatanLapanganController::class, 'aturKelompok'])->name('kegiatan-lapangan.kelompok.atur');
    Route::delete('/kegiatan-lapangan/kelompok/{kelompok}', [Admin\KegiatanLapanganController::class, 'hapusKelompok'])->name('kegiatan-lapangan.kelompok.hapus');
    Route::post('/kegiatan-lapangan/kelompok/{kelompok}/nilai', [Admin\KegiatanLapanganController::class, 'nilai'])->name('kegiatan-lapangan.kelompok.nilai');
    Route::post('/kegiatan-lapangan/{kegiatan}/konversi', [Admin\KegiatanLapanganController::class, 'konversi'])->name('kegiatan-lapangan.konversi');

    // Intake pindahan/RPL (#33). `referensi` dan `baru` didaftarkan sebelum
    // `{mahasiswa}` agar tidak ditangkap sebagai UUID.
    Route::get('/pindahan', [Admin\PindahanController::class, 'index'])->name('pindahan');
    Route::get('/pindahan/referensi', [Admin\PindahanController::class, 'referensi'])->name('pindahan.referensi');
    Route::get('/pindahan/baru', [Admin\PindahanController::class, 'create'])->name('pindahan.create');
    Route::post('/pindahan', [Admin\PindahanController::class, 'store'])->name('pindahan.store');
    Route::get('/pindahan/{mahasiswa}', [Admin\PindahanController::class, 'show'])->name('pindahan.show');
    Route::put('/pindahan/{mahasiswa}', [Admin\PindahanController::class, 'update'])->name('pindahan.update');
    Route::post('/pindahan/{mahasiswa}/transkrip', [Admin\PindahanController::class, 'transkrip'])->name('pindahan.transkrip');

    Route::get('/konversi', [Admin\KonversiController::class, 'index'])->name('konversi');
    Route::post('/konversi/{mahasiswa}', [Admin\KonversiController::class, 'ajukan'])->name('konversi.ajukan');
    Route::post('/konversi/{konversi}/setujui', [Admin\KonversiController::class, 'setujui'])->name('konversi.setujui');
    Route::post('/konversi/{konversi}/tolak', [Admin\KonversiController::class, 'tolak'])->name('konversi.tolak');
    Route::post('/konversi/{konversi}/cabut', [Admin\KonversiController::class, 'cabut'])->name('konversi.cabut');

    // Pengaturan didaftarkan sebelum {tiket} agar "pengaturan" tidak pernah
    // dibaca sebagai UUID tiket.
    Route::get('/helpdesk/pengaturan', [Admin\HelpdeskPengaturanController::class, 'index'])->name('helpdesk.pengaturan');
    Route::post('/helpdesk/pengaturan/kategori', [Admin\HelpdeskPengaturanController::class, 'simpanKategori'])->name('helpdesk.kategori.simpan');
    Route::put('/helpdesk/pengaturan/kategori/{kategori}', [Admin\HelpdeskPengaturanController::class, 'perbaruiKategori'])->name('helpdesk.kategori.perbarui');
    Route::post('/helpdesk/pengaturan/faq', [Admin\HelpdeskPengaturanController::class, 'simpanFaq'])->name('helpdesk.faq.simpan');
    Route::put('/helpdesk/pengaturan/faq/{faq}', [Admin\HelpdeskPengaturanController::class, 'perbaruiFaq'])->name('helpdesk.faq.perbarui');
    Route::delete('/helpdesk/pengaturan/faq/{faq}', [Admin\HelpdeskPengaturanController::class, 'hapusFaq'])->name('helpdesk.faq.hapus');

    Route::get('/helpdesk', [Admin\HelpdeskController::class, 'index'])->name('helpdesk');
    Route::get('/helpdesk/{tiket}', [Admin\HelpdeskController::class, 'show'])->name('helpdesk.show');
    Route::post('/helpdesk/{tiket}/disposisi', [Admin\HelpdeskController::class, 'disposisi'])->name('helpdesk.disposisi');
    Route::post('/helpdesk/{tiket}/balas', [Admin\HelpdeskController::class, 'balas'])->name('helpdesk.balas');
    Route::post('/helpdesk/{tiket}/status', [Admin\HelpdeskController::class, 'status'])->name('helpdesk.status');

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
    Route::get('/bkd/ekspor/sister/{grup}', [Admin\BkdController::class, 'eksporSister'])->name('bkd.ekspor.sister');

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

    Route::get('/kinerja', [Admin\KinerjaController::class, 'index'])->name('kinerja');
    Route::post('/kinerja/periode', [Admin\KinerjaController::class, 'simpanPeriode'])->name('kinerja.periode');
    Route::post('/kinerja/periode/{periode}/jalankan', [Admin\KinerjaController::class, 'jalankan'])
        ->name('kinerja.jalankan');
    Route::post('/kinerja/periode/{periode}/kunci', [Admin\KinerjaController::class, 'kunci'])
        ->name('kinerja.kunci');
    Route::post('/kinerja/periode/{periode}/ukur', [Admin\KinerjaController::class, 'ukurOtomatis'])
        ->name('kinerja.ukur');
    Route::post('/kinerja/periode/{periode}/sasaran', [Admin\KinerjaController::class, 'simpanSasaran'])
        ->name('kinerja.sasaran');
    Route::post('/kinerja/sasaran/{sasaran}/ukuran', [Admin\KinerjaController::class, 'simpanUkuran'])
        ->name('kinerja.ukuran');
    Route::post('/kinerja/ukuran/{ukuran}/capaian', [Admin\KinerjaController::class, 'catatCapaian'])
        ->name('kinerja.capaian');

    /*
     * SPMI — Audit Mutu Internal. Borang akreditasi tidak di sini; ia butuh data
     * penelitian, PkM dan keuangan yang aplikasi ini tidak punya. Lihat docs/SPMI.md.
     */
    Route::get('/spmi', [Admin\SpmiController::class, 'index'])->name('spmi');
    Route::post('/spmi/standar', [Admin\SpmiController::class, 'simpanStandar'])->name('spmi.standar');
    Route::post('/spmi/audit', [Admin\SpmiController::class, 'rencanakanAudit'])->name('spmi.audit');
    Route::post('/spmi/audit/{audit}/mulai', [Admin\SpmiController::class, 'mulaiAudit'])
        ->name('spmi.audit.mulai');
    Route::post('/spmi/audit/{audit}/tutup', [Admin\SpmiController::class, 'tutupAudit'])
        ->name('spmi.audit.tutup');
    Route::post('/spmi/audit/{audit}/temuan', [Admin\SpmiController::class, 'catatTemuan'])
        ->name('spmi.audit.temuan');
    Route::post('/spmi/temuan/{temuan}/tindak-lanjut', [Admin\SpmiController::class, 'catatTindakLanjut'])
        ->name('spmi.temuan.tindak');
    Route::post('/spmi/temuan/{temuan}/tutup', [Admin\SpmiController::class, 'tutupTemuan'])
        ->name('spmi.temuan.tutup');
    Route::post('/spmi/tindak-lanjut/{tindak}/verifikasi', [Admin\SpmiController::class, 'verifikasiTindakLanjut'])
        ->name('spmi.tindak.verifikasi');

    Route::get('/unit-kerja', [Admin\UnitKerjaController::class, 'index'])->name('unit-kerja');
    Route::post('/unit-kerja', [Admin\UnitKerjaController::class, 'simpan'])->name('unit-kerja.simpan');
    Route::put('/unit-kerja/{unit}', [Admin\UnitKerjaController::class, 'perbarui'])->name('unit-kerja.perbarui');
    Route::post('/unit-kerja/{unit}/nonaktifkan', [Admin\UnitKerjaController::class, 'nonaktifkan'])
        ->name('unit-kerja.nonaktifkan');
    Route::post('/unit-kerja/staf/{staf}', [Admin\UnitKerjaController::class, 'pindahkanStaf'])
        ->name('unit-kerja.pindah-staf');

    Route::get('/poin-kemahasiswaan', [Admin\PoinKemahasiswaanController::class, 'index'])
        ->name('poin-kemahasiswaan');
    Route::post('/poin-kemahasiswaan/kategori', [Admin\PoinKemahasiswaanController::class, 'simpanKategori'])
        ->name('poin-kemahasiswaan.kategori');
    Route::post('/poin-kemahasiswaan/catat', [Admin\PoinKemahasiswaanController::class, 'catat'])
        ->name('poin-kemahasiswaan.catat');
    Route::post('/poin-kemahasiswaan/{poin}/verifikasi', [Admin\PoinKemahasiswaanController::class, 'verifikasi'])
        ->name('poin-kemahasiswaan.verifikasi');
    Route::post('/poin-kemahasiswaan/{poin}/tolak', [Admin\PoinKemahasiswaanController::class, 'tolak'])
        ->name('poin-kemahasiswaan.tolak');

    // Graduates for the national tracer study (ADR-037 §2). No questionnaire.
    Route::get('/lulusan', [Admin\LulusanController::class, 'index'])->name('lulusan');
    Route::post('/lulusan/ekspor', [Admin\LulusanController::class, 'ekspor'])->name('lulusan.ekspor');
    Route::put('/lulusan/{alumni}/kontak', [Admin\LulusanController::class, 'kontak'])->name('lulusan.kontak');
    Route::post('/lulusan/impor', [Admin\LulusanController::class, 'periksaImpor'])->name('lulusan.impor');
    Route::post('/lulusan/impor/terapkan', [Admin\LulusanController::class, 'terapkanImpor'])->name('lulusan.impor.terapkan');
    Route::post('/lulusan/impor/batal', [Admin\LulusanController::class, 'batalImpor'])->name('lulusan.impor.batal');

    // SIMKATMAWA report (ADR-037 §1): export only, nothing is sent.
    Route::get('/simkatmawa', [Admin\SimkatmawaController::class, 'index'])->name('simkatmawa');
    Route::get('/simkatmawa/unduh', [Admin\SimkatmawaController::class, 'unduh'])->name('simkatmawa.unduh');
    Route::get('/poin-kemahasiswaan/{poin}/simkatmawa', [Admin\SimkatmawaController::class, 'edit'])
        ->name('poin-kemahasiswaan.simkatmawa');
    Route::put('/poin-kemahasiswaan/{poin}/simkatmawa', [Admin\SimkatmawaController::class, 'perbarui'])
        ->name('poin-kemahasiswaan.simkatmawa.simpan');
    Route::post('/poin-kemahasiswaan/kategori/{kategori}/simkatmawa', [Admin\SimkatmawaController::class, 'kategori'])
        ->name('poin-kemahasiswaan.kategori.simkatmawa');

    Route::get('/evaluasi-studi', [Admin\EvaluasiStudiController::class, 'index'])->name('evaluasi-studi');
    Route::post('/evaluasi-studi/jalankan', [Admin\EvaluasiStudiController::class, 'jalankan'])
        ->name('evaluasi-studi.jalankan');
    Route::post('/evaluasi-studi/{evaluasi}/putuskan', [Admin\EvaluasiStudiController::class, 'putuskan'])
        ->name('evaluasi-studi.putuskan');
    Route::post('/evaluasi-studi/{evaluasi}/batalkan', [Admin\EvaluasiStudiController::class, 'batalkan'])
        ->name('evaluasi-studi.batalkan');

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
    Route::post('/keuangan/tagihan/{tagihan}/bebaskan-denda', [Admin\KeuanganController::class, 'bebaskanDenda'])->name('keuangan.denda.bebaskan');

    // Dispensasi pembayaran KRS: beri langsung, putuskan permohonan, cabut.
    Route::get('/keuangan/dispensasi', [Admin\DispensasiController::class, 'index'])->name('keuangan.dispensasi');
    Route::post('/keuangan/dispensasi', [Admin\DispensasiController::class, 'beri'])->name('keuangan.dispensasi.beri');
    Route::post('/keuangan/dispensasi/{dispensasi}/putuskan', [Admin\DispensasiController::class, 'putuskan'])->name('keuangan.dispensasi.putuskan');
    Route::post('/keuangan/dispensasi/{dispensasi}/cabut', [Admin\DispensasiController::class, 'cabut'])->name('keuangan.dispensasi.cabut');

    Route::get('/beasiswa', [Admin\BeasiswaController::class, 'index'])->name('beasiswa');
    Route::post('/beasiswa', [Admin\BeasiswaController::class, 'simpanSkema'])->name('beasiswa.skema');
    Route::post('/beasiswa/{beasiswa}/tetapkan', [Admin\BeasiswaController::class, 'tetapkan'])->name('beasiswa.tetapkan');
    Route::post('/beasiswa/penerima/{penerima}/cabut', [Admin\BeasiswaController::class, 'cabut'])->name('beasiswa.cabut');
    Route::get('/beasiswa/kipk/ekspor', [Admin\BeasiswaController::class, 'eksporKipk'])->name('beasiswa.kipk');

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

    Route::get('/lkps', [Admin\LkpsController::class, 'index'])->name('lkps');
    Route::get('/lkps/ekspor', [Admin\LkpsController::class, 'ekspor'])->name('lkps.ekspor');

    Route::get('/feeder', [Admin\FeederController::class, 'index'])->name('feeder');
    Route::post('/feeder/validasi', [Admin\FeederController::class, 'validasi'])->name('feeder.validasi');
    Route::post('/feeder/referensi', [Admin\FeederController::class, 'tarikReferensi'])->name('feeder.referensi');
    Route::get('/feeder/adopsi', [Admin\FeederAdopsiController::class, 'index'])->name('feeder.adopsi');
    Route::post('/feeder/adopsi', [Admin\FeederAdopsiController::class, 'jalankan'])->name('feeder.adopsi.jalankan');
    Route::post('/feeder/adopsi/{adopsi}/tautkan', [Admin\FeederAdopsiController::class, 'tautkan'])->name('feeder.adopsi.tautkan');
    Route::post('/feeder/adopsi/{adopsi}/pisahkan', [Admin\FeederAdopsiController::class, 'pisahkan'])->name('feeder.adopsi.pisahkan');
    Route::get('/feeder/lampau', [Admin\FeederLampauController::class, 'index'])->name('feeder.lampau');
    Route::post('/feeder/lampau', [Admin\FeederLampauController::class, 'baca'])->name('feeder.lampau.baca');
    Route::post('/feeder/lampau/klaim', [Admin\FeederLampauController::class, 'klaim'])->name('feeder.lampau.klaim');
    Route::post('/feeder/{entity}/jalankan', [Admin\FeederController::class, 'jalankan'])->name('feeder.jalankan');
    Route::post('/feeder/{entity}/ulangi', [Admin\FeederController::class, 'ulangi'])->name('feeder.ulangi');
    Route::post('/feeder/{entity}/bandingkan', [Admin\FeederController::class, 'bandingkan'])->name('feeder.bandingkan');
    Route::post('/feeder/{entity}/hapus', [Admin\FeederController::class, 'hapus'])->name('feeder.hapus');
    Route::post('/feeder/perubahan/surat', [Admin\FeederPerubahanController::class, 'terbitkanSurat'])->name('feeder.perubahan.surat');
    Route::post('/feeder/perubahan/{perubahan}/ajukan', [Admin\FeederPerubahanController::class, 'ajukan'])->name('feeder.perubahan.ajukan');
    Route::post('/feeder/perubahan/{perubahan}/kirim', [Admin\FeederPerubahanController::class, 'kirim'])->name('feeder.perubahan.kirim');
    Route::post('/feeder/perubahan/{perubahan}/selesai', [Admin\FeederPerubahanController::class, 'selesai'])->name('feeder.perubahan.selesai');

    Route::get('/yudisium', [Admin\YudisiumController::class, 'index'])->name('yudisium');
    Route::post('/yudisium/ajukan', [Admin\YudisiumController::class, 'ajukan'])->name('yudisium.ajukan');
    Route::post('/yudisium/{yudisium}/tetapkan', [Admin\YudisiumController::class, 'tetapkan'])->name('yudisium.tetapkan');
    Route::post('/yudisium/{yudisium}/batalkan', [Admin\YudisiumController::class, 'batalkan'])->name('yudisium.batalkan');
    Route::post('/yudisium/ukom/{mahasiswa}', [Admin\YudisiumController::class, 'catatUkom'])->name('yudisium.ukom');

    Route::get('/wisuda', [Admin\WisudaController::class, 'index'])->name('wisuda');
    Route::post('/wisuda/periode', [Admin\WisudaController::class, 'storePeriode'])->name('wisuda.periode.store');
    Route::post('/wisuda/periode/{periode}/buka', [Admin\WisudaController::class, 'bukaPendaftaran'])->name('wisuda.buka');
    Route::post('/wisuda/periode/{periode}/tutup', [Admin\WisudaController::class, 'tutupPendaftaran'])->name('wisuda.tutup');
    Route::post('/wisuda/periode/{periode}/daftarkan', [Admin\WisudaController::class, 'daftarkan'])->name('wisuda.daftarkan');
    Route::post('/wisuda/periode/{periode}/daftarkan-massal', [Admin\WisudaController::class, 'daftarkanMassal'])->name('wisuda.daftarkan-massal');
    Route::post('/wisuda/periode/{periode}/ijazah', [Admin\WisudaController::class, 'terbitkanIjazah'])->name('wisuda.ijazah');
    Route::post('/wisuda/periode/{periode}/ijazah/catat', [Admin\WisudaController::class, 'catatIjazah'])->name('wisuda.ijazah.catat');
    Route::delete('/wisuda/peserta/{peserta}', [Admin\WisudaController::class, 'batalkan'])->name('wisuda.peserta.batal');

    Route::get('/data-iku', [Admin\IkuRecordController::class, 'index'])->name('iku-records');
    Route::post('/data-iku/aktivitas/{aktivitas}/verifikasi', [Admin\IkuRecordController::class, 'verifikasiAktivitas'])
        ->name('iku-records.aktivitas.verifikasi');
    Route::post('/data-iku/aktivitas/{aktivitas}/batal', [Admin\IkuRecordController::class, 'batalkanAktivitas'])
        ->name('iku-records.aktivitas.batal');
    Route::post('/data-iku/aktivitas/{aktivitas}/flagship', [Admin\IkuRecordController::class, 'tandaiFlagship'])
        ->name('iku-records.aktivitas.flagship');
    Route::post('/data-iku/penugasan/{penugasan}/verifikasi', [Admin\IkuRecordController::class, 'verifikasiPenugasan'])
        ->name('iku-records.penugasan.verifikasi');
    Route::post('/data-iku/penugasan/{penugasan}/batal', [Admin\IkuRecordController::class, 'batalkanPenugasan'])
        ->name('iku-records.penugasan.batal');

    Route::get('/bridge', [Admin\BridgeController::class, 'index'])->name('bridge');
    Route::post('/bridge/pengiriman/{pengiriman}/kirim-ulang', [Admin\BridgeController::class, 'kirimUlang'])
        ->name('bridge.kirim-ulang');
});

/*
| PMB daring: portal calon mahasiswa dan layar seleksi staf. Berkas sendiri
| agar portal kelima tidak tercecer di antara rute tiga portal kampus.
*/
require __DIR__.'/pmb.php';
