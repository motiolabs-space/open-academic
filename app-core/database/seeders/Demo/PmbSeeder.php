<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\Agama;
use App\Enums\ApplicantStatus;
use App\Enums\Gender;
use App\Enums\JalurPmb;
use App\Enums\PaymentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Pmb\PmbAkun;
use App\Models\Pmb\PmbBerkas;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbGelombangProdi;
use App\Models\Pmb\PmbJadwalSeleksi;
use App\Models\Pmb\PmbPembayaran;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Two admission waves with applicants spread across the funnel, so the PMB
 * screen opens with a funnel that actually narrows.
 */
class PmbSeeder extends Seeder
{
    public function run(): void
    {
        $term = TahunAkademik::aktif();
        $prodi = Prodi::all();

        $gelombang1 = PmbGelombang::create([
            'tahun_akademik_id' => $term->id,
            'kode' => 'PMB-'.$term->kode.'-G1',
            'nama' => 'Gelombang I — Jalur Prestasi',
            'jalur' => 'prestasi',
            'tanggal_mulai' => $term->tanggal_mulai->copy()->subMonths(6),
            'tanggal_selesai' => $term->tanggal_mulai->copy()->subMonths(4),
            'biaya_pendaftaran' => 250_000,
            'kuota' => 120,
            'is_active' => false,
        ]);

        $gelombang2 = PmbGelombang::create([
            'tahun_akademik_id' => $term->id,
            'kode' => 'PMB-'.$term->kode.'-G2',
            'nama' => 'Gelombang II — Jalur Reguler',
            'jalur' => 'reguler',
            'tanggal_mulai' => $term->tanggal_mulai->copy()->subMonths(3),
            'tanggal_selesai' => $term->tanggal_mulai->copy()->subMonth(),
            'biaya_pendaftaran' => 300_000,
            'kuota' => 200,
            'is_active' => true,
        ]);

        // A funnel that narrows the way a real one does.
        $sebaran = [
            [ApplicantStatus::Mendaftar, 34],
            [ApplicantStatus::Verifikasi, 22],
            [ApplicantStatus::Seleksi, 18],
            [ApplicantStatus::Lulus, 15],
            [ApplicantStatus::TidakLulus, 9],
            [ApplicantStatus::DaftarUlang, 11],
            [ApplicantStatus::Batal, 4],
        ];

        $nomor = 1;

        foreach ([$gelombang1, $gelombang2] as $gelombang) {
            foreach ($sebaran as [$status, $jumlah]) {
                for ($i = 0; $i < intdiv($jumlah, 2); $i++) {
                    $pilihan1 = $prodi->random();

                    PmbPendaftar::create([
                        'pmb_gelombang_id' => $gelombang->id,
                        'nomor_pendaftaran' => sprintf('%s-%04d', $gelombang->kode, $nomor++),
                        'nama' => fake()->name(),
                        'email' => fake()->unique()->safeEmail(),
                        'telepon' => fake()->phoneNumber(),

                        // Applicants still early in the funnel have not yet
                        // submitted a NIK — precisely the gap the Feeder
                        // readiness check surfaces before enrolment.
                        'nik' => $status->funnelStep() >= 3
                            ? (string) fake()->unique()->numerify('################')
                            : null,

                        'nisn' => (string) fake()->unique()->numerify('##########'),
                        'tempat_lahir' => fake()->city(),
                        'tanggal_lahir' => fake()->dateTimeBetween('-20 years', '-17 years'),
                        'jenis_kelamin' => fake()->randomElement(Gender::cases()),
                        'alamat' => fake()->streetAddress(),
                        'asal_sekolah' => 'SMA Negeri '.fake()->numberBetween(1, 20).' '.fake()->city(),
                        'tahun_lulus_sekolah' => (int) date('Y'),
                        'prodi_pilihan_1_id' => $pilihan1->id,
                        'prodi_pilihan_2_id' => $prodi->where('id', '!=', $pilihan1->id)->first()?->id,
                        'prodi_diterima_id' => $status->funnelStep() >= 3 ? $pilihan1->id : null,
                        'status' => $status,
                        'nilai_seleksi' => $status->funnelStep() >= 2 ? fake()->randomFloat(2, 55, 95) : null,
                    ]);
                }
            }
        }

        $this->gelombangPindahan($term, $prodi->first(), $nomor);
        $this->gelombangDaring($term, $prodi);
    }

    /**
     * An online wave open today, with one applicant account at every stage of
     * the funnel — so the applicant portal, the cashier's queue, the score
     * import and the announcement can each be demonstrated from a fresh
     * install. Not yet announced: pressing "Umumkan Hasil" is the demo.
     *
     * Every account signs in at /pmb/masuk with password "password".
     *
     * @param Collection<int, Prodi> $prodi
     */
    private function gelombangDaring(TahunAkademik $term, $prodi): void
    {
        $gelombang = PmbGelombang::create([
            'tahun_akademik_id' => $term->id,
            'kode' => 'PMB-'.$term->kode.'-D1',
            'nama' => 'Gelombang Daring — Jalur Reguler',
            'jalur' => JalurPmb::Reguler,
            // Anchored to today (ADR-010), so the wave is open whenever the
            // demo is installed.
            'tanggal_mulai' => today()->subWeeks(2),
            'tanggal_selesai' => today()->addMonth(),
            'biaya_pendaftaran' => 250_000,
            'kuota' => 300,
            'is_active' => true,
            'is_daring' => true,
            'dokumen_wajib' => ['ijazah', 'ktp', 'foto'],
            'keterangan' => 'Tes Potensi Akademik berbasis komputer dan wawancara. Pembayaran formulir melalui transfer ke rekening resmi kampus.',
        ]);

        $ditawarkan = $prodi->take(3)->values();

        foreach ($ditawarkan as $i => $p) {
            PmbGelombangProdi::create([
                'pmb_gelombang_id' => $gelombang->id,
                'prodi_id' => $p->id,
                'daya_tampung' => 40,
                'ambang_lulus' => 60,
                // The first programme asks for school reports as well.
                'dokumen_wajib' => $i === 0 ? ['rapor'] : null,
            ]);
        }

        PmbJadwalSeleksi::create([
            'pmb_gelombang_id' => $gelombang->id,
            'nama' => 'Tes Potensi Akademik (CBT)',
            'mulai' => today()->addMonth()->addWeek()->setTime(8, 0),
            'selesai' => today()->addMonth()->addWeek()->setTime(10, 0),
            'lokasi' => 'Laboratorium Komputer Gedung B',
        ]);

        PmbJadwalSeleksi::create([
            'pmb_gelombang_id' => $gelombang->id,
            'nama' => 'Wawancara',
            'mulai' => today()->addMonth()->addWeek()->addDays(2)->setTime(9, 0),
            'selesai' => today()->addMonth()->addWeek()->addDays(2)->setTime(12, 0),
            'lokasi' => 'Ruang Sidang Rektorat',
        ]);

        $kasir = Staff::where('email', 'admin@demo.test')->first();
        $pilihan = $ditawarkan->first();

        $calon = [
            ['draf', 'Rani Puspitasari', ApplicantStatus::Draf, null, false],
            ['bayar', 'Dimas Pratama', ApplicantStatus::Mendaftar, null, false],
            ['verifikasi', 'Ayu Lestari', ApplicantStatus::Verifikasi, null, true],
            ['seleksi', 'Fajar Nugroho', ApplicantStatus::Seleksi, 78.5, true],
            ['lulus', 'Nabila Zahra', ApplicantStatus::Lulus, 84.25, false],
            ['tidaklulus', 'Rizky Saputra', ApplicantStatus::TidakLulus, 52.0, false],
        ];

        foreach ($calon as $urut => [$slug, $nama, $status, $nilai, $kip]) {
            $akun = PmbAkun::create([
                'nama' => $nama,
                'email' => "calon.{$slug}@demo.test",
                'password' => 'password',
                'email_verified_at' => now(),
            ]);

            $lengkap = $status !== ApplicantStatus::Draf;
            $dokumen = $gelombang->dokumenWajibUntuk($gelombang->prodiDitawarkan()->where('prodi_id', $pilihan->id)->first());

            $pendaftar = PmbPendaftar::create([
                'pmb_gelombang_id' => $gelombang->id,
                'pmb_akun_id' => $akun->id,
                'nomor_pendaftaran' => sprintf('%s-%04d', $gelombang->kode, $urut + 1),
                'nama' => $nama,
                'email' => $akun->email,
                'telepon' => '08123456'.str_pad((string) ($urut + 10), 4, '0', STR_PAD_LEFT),
                'nik' => '32010'.str_pad((string) (1_234_560 + $urut), 11, '0', STR_PAD_LEFT),
                'nisn' => '00'.str_pad((string) (61_234_500 + $urut), 8, '0', STR_PAD_LEFT),
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2008-0'.(($urut % 9) + 1).'-15',
                'jenis_kelamin' => $urut % 2 === 0 ? Gender::Perempuan : Gender::LakiLaki,
                'agama_kode' => $lengkap ? Agama::Islam : null,
                'nama_ibu' => $lengkap ? 'Siti Aminah' : null,
                'alamat' => 'Jl. Merdeka No. '.($urut + 3),
                'kelurahan' => $lengkap ? 'Citarum' : null,
                'kecamatan' => $lengkap ? 'Bandung Wetan' : null,
                'kabupaten' => $lengkap ? 'Kota Bandung' : null,
                'provinsi' => $lengkap ? 'Jawa Barat' : null,
                'kode_pos' => '40115',
                'asal_sekolah' => 'SMA Negeri 3 Bandung',
                'tahun_lulus_sekolah' => (int) date('Y'),
                'prodi_pilihan_1_id' => $pilihan->id,
                'prodi_pilihan_2_id' => $ditawarkan->get(1)?->id,
                'prodi_diterima_id' => $status === ApplicantStatus::Lulus ? $pilihan->id : null,
                'is_calon_kip' => $kip,
                'nomor_kip' => $kip ? 'KIP-'.str_pad((string) ($urut + 1), 6, '0', STR_PAD_LEFT) : null,
                'biaya_formulir' => 250_000,
                'diajukan_at' => $lengkap ? now()->subDays(10 - $urut) : null,
                'status' => $status,
                'nilai_seleksi' => $nilai,
            ]);

            // Documents: none for the draft but a photo; all accepted from the
            // selection pool on; waiting for checking at Verifikasi.
            foreach ($status === ApplicantStatus::Draf ? ['foto'] : $dokumen as $jenis) {
                $this->berkasContoh($pendaftar, $jenis, in_array($status, [
                    ApplicantStatus::Seleksi, ApplicantStatus::Lulus, ApplicantStatus::TidakLulus,
                ], true));
            }

            if ($status === ApplicantStatus::Mendaftar) {
                $this->berkasContoh($pendaftar, 'bukti_bayar', false);
            }

            if (!in_array($status, [ApplicantStatus::Draf, ApplicantStatus::Mendaftar], true)) {
                PmbPembayaran::create([
                    'pmb_pendaftar_id' => $pendaftar->id,
                    'nominal' => 250_000,
                    'channel' => 'transfer',
                    'referensi' => 'TRF-DEMO-'.($urut + 1),
                    'status' => PaymentStatus::Settlement,
                    'dicatat_oleh_id' => $kasir?->id,
                    'paid_at' => now()->subDays(8 - $urut),
                ]);
            }
        }
    }

    /**
     * A tiny placeholder PDF on the private disk, so the demo's document links
     * open something rather than answering 404.
     */
    private function berkasContoh(PmbPendaftar $pendaftar, string $jenis, bool $terverifikasi): void
    {
        $path = 'pmb/'.$pendaftar->uuid.'/'.Str::uuid().'.pdf';

        // Not under test: the suite seeds the demo campus dozens of times per
        // run, in parallel, against the real storage directory. A missing file
        // is answered with a readable 404 by BerkasController anyway.
        if (!app()->runningUnitTests()) {
            Storage::disk((string) config('berkas.disk'))->put($path, self::PDF_CONTOH);
        }

        PmbBerkas::create([
            'pmb_pendaftar_id' => $pendaftar->id,
            'jenis' => $jenis,
            'nama_file' => $jenis.'.pdf',
            'file_path' => $path,
            'is_verified' => $terverifikasi,
        ]);
    }

    /** The smallest PDF most readers will open: one blank page. */
    private const PDF_CONTOH = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
        ."trailer<</Root 1 0 R>>\n%%EOF\n";

    /**
     * A transfer wave with one accepted applicant left unregistered, so the
     * first door of the transfer intake has someone waiting at it.
     */
    private function gelombangPindahan(TahunAkademik $term, Prodi $prodi, int $nomor): void
    {
        $gelombang = PmbGelombang::create([
            'tahun_akademik_id' => $term->id,
            'kode' => 'PMB-'.$term->kode.'-TR',
            'nama' => 'Gelombang Pindahan',
            'jalur' => 'transfer',
            'tanggal_mulai' => $term->tanggal_mulai->copy()->subMonths(3),
            'tanggal_selesai' => $term->tanggal_mulai->copy()->subMonth(),
            'biaya_pendaftaran' => 300_000,
            'kuota' => 20,
            'is_active' => true,
        ]);

        PmbPendaftar::create([
            'pmb_gelombang_id' => $gelombang->id,
            'nomor_pendaftaran' => sprintf('%s-%04d', $gelombang->kode, $nomor),
            'nama' => 'Bagas Wicaksono',
            'email' => 'bagas.pindahan@demo.test',
            'nik' => '9900000000000002',
            'tempat_lahir' => 'Semarang',
            'tanggal_lahir' => '2003-03-12',
            'jenis_kelamin' => Gender::LakiLaki,
            'alamat' => 'Jl. Pemuda No. 18, Semarang',
            'prodi_pilihan_1_id' => $prodi->id,
            'prodi_diterima_id' => $prodi->id,
            'status' => ApplicantStatus::Lulus,
            'nilai_seleksi' => 81.5,
        ]);
    }
}
