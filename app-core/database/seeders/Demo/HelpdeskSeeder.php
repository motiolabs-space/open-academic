<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Helpdesk\BalasanData;
use App\DTOs\Helpdesk\DisposisiData;
use App\DTOs\Helpdesk\TiketBaruData;
use App\Enums\JenisUnitKerja;
use App\Enums\PrioritasTiket;
use App\Enums\StatusTiket;
use App\Models\Helpdesk\HelpdeskFaq;
use App\Models\Helpdesk\HelpdeskKategori;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Helpdesk\HelpdeskService;
use Illuminate\Database\Seeder;

/**
 * Categories, a published FAQ, and tickets in every state a screen can show.
 *
 * Tickets go through HelpdeskService rather than straight to the table, for
 * the same reason SuratSeeder does: the rules are cheap to satisfy, and demo
 * rows that skipped them would behave differently from real ones — a ticket
 * closed without a note, say, which the service refuses.
 *
 * Runs after MutuSeeder, which plants the org chart the categories point at.
 * Two more units are added here (finance, IT) because a helpdesk whose every
 * category lands on BAAK demonstrates nothing about disposition.
 */
class HelpdeskSeeder extends Seeder
{
    public function run(): void
    {
        $unit = $this->unitKerja();
        $kategori = $this->kategori($unit);

        $this->faq($kategori);
        $this->tiket($kategori, $unit);
    }

    /** @return array<string, UnitKerja> */
    private function unitKerja(): array
    {
        $rektorat = UnitKerja::where('kode', 'REKTORAT')->first();

        $bauk = UnitKerja::firstOrCreate(['kode' => 'BAUK'], [
            'nama' => 'Biro Administrasi Umum & Keuangan',
            'jenis' => JenisUnitKerja::Struktural,
            'parent_id' => $rektorat?->id,
        ]);

        $tik = UnitKerja::firstOrCreate(['kode' => 'UPT-TIK'], [
            'nama' => 'UPT Teknologi Informasi',
            'jenis' => JenisUnitKerja::Penunjang,
            'parent_id' => $rektorat?->id,
        ]);

        // The finance account belongs in the finance office; the system
        // administrator in IT. MutuSeeder placed BAAK and LPM, not these two.
        Staff::where('email', 'keuangan@demo.test')->whereNull('unit_kerja_id')->update(['unit_kerja_id' => $bauk->id]);
        Staff::where('email', 'admin@demo.test')->whereNull('unit_kerja_id')->update(['unit_kerja_id' => $tik->id]);

        return [
            'baak' => UnitKerja::where('kode', 'BAAK')->first() ?? $bauk,
            'bauk' => $bauk,
            'tik' => $tik,
        ];
    }

    /**
     * @param array<string, UnitKerja> $unit
     * @return array<string, HelpdeskKategori>
     */
    private function kategori(array $unit): array
    {
        $daftar = [
            'akademik' => ['Akademik & KRS', 'Rencana studi, nilai, presensi, dan jadwal.', $unit['baak'], 48],
            'keuangan' => ['Keuangan & Tagihan', 'Tagihan, pembayaran yang belum tercatat, keringanan.', $unit['bauk'], 72],
            'akun' => ['Akun & Akses Sistem', 'Kata sandi, verifikasi dua langkah, akun terkunci.', $unit['tik'], 24],
            'surat' => ['Surat & Dokumen', 'Surat keterangan, transkrip, legalisir.', $unit['baak'], 72],
            'lainnya' => ['Lainnya', 'Yang tidak termasuk kategori lain; didisposisi manual.', null, null],
        ];

        $hasil = [];

        foreach (array_values($daftar) as $urutan => [$nama, $deskripsi, $unitTujuan, $sla]) {
            $hasil[array_keys($daftar)[$urutan]] = HelpdeskKategori::firstOrCreate(['nama' => $nama], [
                'deskripsi' => $deskripsi,
                'unit_kerja_id' => $unitTujuan?->id,
                'sla_jam' => $sla,
                'urutan' => $urutan,
            ]);
        }

        return $hasil;
    }

    /** @param array<string, HelpdeskKategori> $kategori */
    private function faq(array $kategori): void
    {
        $daftar = [
            ['akun', 'Saya lupa kata sandi portal. Bagaimana mengatur ulangnya?',
                "Gunakan tautan \"Lupa kata sandi\" pada halaman masuk. Tautan pengaturan ulang dikirim ke surel yang terdaftar.\n\nBila surel itu sudah tidak dapat diakses, ajukan tiket kategori Akun & Akses Sistem dengan menyertakan NIM/NIDN; UPT TIK akan memverifikasi identitas Anda lebih dulu.", true],
            ['akademik', 'KRS saya terkunci padahal sudah membayar. Apa yang harus dilakukan?',
                'Pembayaran lewat transfer manual dicatat bagian keuangan pada hari kerja berikutnya. Periksa menu Tagihan & Pembayaran; bila statusnya sudah Lunas tetapi KRS masih terkunci, ajukan tiket kategori Akademik & KRS.', true],
            ['akademik', 'Nilai saya belum muncul di KHS.',
                'Nilai tampil setelah dosen pengampu memfinalisasinya dan periode penilaian ditutup. Bila teman sekelas sudah melihat nilainya dan Anda belum, ajukan tiket dengan menyebut nama kelasnya.', true],
            ['keuangan', 'Saya sudah transfer tetapi tagihan masih tercatat belum lunas.',
                'Pembayaran transfer dicocokkan secara manual dengan rekening koran kampus. Lampirkan bukti transfer pada tiket kategori Keuangan & Tagihan agar pencocokannya dapat dilakukan hari itu juga.', true],
            ['surat', 'Berapa lama surat keterangan aktif kuliah terbit?',
                'Seketika. Surat keterangan aktif kuliah diterbitkan otomatis dari menu Surat & Dokumen selama status Anda aktif, lengkap dengan nomor dan kode QR verifikasi.', true],
            ['lainnya', 'Apakah helpdesk ini untuk pengaduan tentang dosen atau sesama mahasiswa?',
                'Bukan. Helpdesk ini untuk layanan administratif kampus. Pengaduan etik ditangani lewat jalur yang ditetapkan Komisi Etik; tanyakan kepada program studi Anda.', false],
        ];

        foreach ($daftar as $urutan => [$kunci, $pertanyaan, $jawaban, $terbit]) {
            HelpdeskFaq::firstOrCreate(['pertanyaan' => $pertanyaan], [
                'helpdesk_kategori_id' => $kategori[$kunci]->id,
                'jawaban' => $jawaban,
                'urutan' => $urutan,
                'is_published' => $terbit,
            ]);
        }
    }

    /**
     * @param array<string, HelpdeskKategori> $kategori
     * @param array<string, UnitKerja> $unit
     */
    private function tiket(array $kategori, array $unit): void
    {
        if (HelpdeskTiket::exists()) {
            return;
        }

        $helpdesk = app(HelpdeskService::class);

        $mahasiswa = Mahasiswa::query()->orderBy('id')->limit(4)->get();
        $dosen = Dosen::where('email', 'dosen1@demo.test')->first();
        $baak = Staff::where('email', 'baak@demo.test')->first();
        $keuangan = Staff::where('email', 'keuangan@demo.test')->first();
        $admin = Staff::where('email', 'admin@demo.test')->first();

        if ($mahasiswa->count() < 4 || $dosen === null || $baak === null || $keuangan === null || $admin === null) {
            return;
        }

        // 1. New, untouched — the top of BAAK's queue.
        $helpdesk->buat($mahasiswa[0], new TiketBaruData(
            kategoriId: $kategori['akademik']->id,
            judul: 'Kelas Basis Data tidak muncul di KRS',
            isi: 'Saya sudah mengambil kelas Basis Data (kelas A) dan dosen wali menyetujui KRS pekan lalu, tetapi kelas itu tidak muncul di jadwal maupun presensi mandiri. Mata kuliah lain tampil normal.',
        ));

        // 2. Dispositioned to a named officer, with an internal note.
        $t2 = $helpdesk->buat($mahasiswa[1], new TiketBaruData(
            kategoriId: $kategori['keuangan']->id,
            judul: 'Transfer UKT belum tercatat',
            isi: 'Saya transfer UKT semester ini pada tanggal 3 lewat ATM, tetapi di menu Tagihan masih tertulis belum lunas. Bukti transfer saya simpan dan dapat dilampirkan bila diperlukan.',
            prioritas: PrioritasTiket::Tinggi,
        ));
        $helpdesk->disposisi($t2, $baak, new DisposisiData(
            unitKerjaId: $unit['bauk']->id,
            petugasStaffId: $keuangan->id,
            catatan: 'Mohon cocokkan dengan rekening koran tanggal 3.',
        ));

        // 3. Answered by staff; waiting on the applicant.
        $t3 = $helpdesk->buat($mahasiswa[2], new TiketBaruData(
            kategoriId: $kategori['akun']->id,
            judul: 'Tidak bisa masuk setelah ganti nomor HP',
            isi: 'Saya mengganti nomor HP dan sekarang verifikasi dua langkah tidak bisa dilewati karena aplikasi autentikatornya ada di HP lama yang hilang.',
            prioritas: PrioritasTiket::Mendesak,
        ));
        $helpdesk->balas($t3, $admin, new BalasanData(
            isi: 'Kami dapat mengatur ulang verifikasi dua langkah setelah identitas terverifikasi. Mohon balas tiket ini dengan foto KTM dan nomor HP baru Anda, atau datang langsung ke UPT TIK dengan membawa KTM.',
        ));

        // 4. Resolved, with the note the applicant reads.
        $t4 = $helpdesk->buat($dosen, new TiketBaruData(
            kategoriId: $kategori['akademik']->id,
            judul: 'Komponen nilai UTS tidak bisa dihapus',
            isi: 'Pada rencana evaluasi kelas Pemrograman Web, komponen UTS yang saya buat keliru tidak bisa dihapus. Tombol hapusnya tidak muncul.',
        ));
        $helpdesk->ubahStatus($t4, $baak, StatusTiket::Diproses);
        $helpdesk->selesaikan($t4, $baak,
            'Komponen yang sudah berisi nilai memang tidak dapat dihapus — itu pengaman agar nilai yang terisi tidak lenyap. Ubah nama, bobot, atau jenisnya saja; bila benar-benar harus hilang, kosongkan nilainya lebih dulu lewat jalur koreksi nilai.');

        // 5. Closed without resolution — duplicate of #1.
        $t5 = $helpdesk->buat($mahasiswa[0], new TiketBaruData(
            kategoriId: $kategori['lainnya']->id,
            judul: 'Kelas tidak muncul (lagi)',
            isi: 'Mengulangi laporan sebelumnya karena belum ada jawaban: kelas Basis Data tidak tampil di jadwal saya.',
            prioritas: PrioritasTiket::Rendah,
        ));
        $helpdesk->tutup($t5, $baak, 'Digabung dengan tiket pertama Anda tentang hal yang sama; jawabannya akan diberikan di sana.');

        // 6. Past its SLA — filed three days ago on a 24-hour category and never
        //    picked up. Backdated directly because the service fixes the clock
        //    at filing time, which is the whole point of the clock.
        $t6 = $helpdesk->buat($mahasiswa[3], new TiketBaruData(
            kategoriId: $kategori['akun']->id,
            judul: 'Akun terkunci setelah salah kata sandi',
            isi: 'Setelah tiga kali salah memasukkan kata sandi, akun saya terkunci dan sampai sekarang belum bisa masuk lagi.',
        ));
        $t6->forceFill([
            'created_at' => now()->subDays(3),
            'balasan_terakhir_at' => now()->subDays(3),
            'tenggat_sla_at' => now()->subDays(2),
        ])->saveQuietly();

        $helpdesk->tandaiLewatSla();
    }
}
