<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Kemahasiswaan\AjuanCutiData;
use App\DTOs\Kemahasiswaan\DataSimkatmawa;
use App\DTOs\Kemahasiswaan\LaporanPrestasiData;
use App\DTOs\Keuangan\AjuanBeasiswaData;
use App\DTOs\Keuangan\PeriodeBeasiswaData;
use App\Enums\BidangLomba;
use App\Enums\JenisCuti;
use App\Enums\KrsStatus;
use App\Enums\LeaveStatus;
use App\Enums\PeringkatPrestasi;
use App\Enums\StatusKonversi;
use App\Enums\StudentStatus;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Keuangan\Beasiswa;
use App\Models\Sdm\Staff;
use App\Services\Akademik\KonversiService;
use App\Services\Kemahasiswaan\CutiService;
use App\Services\Kemahasiswaan\PoinKemahasiswaanService;
use App\Services\Keuangan\AjuanBeasiswaService;
use App\Support\Portal;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Student self-service applications in every state (ADR-040).
 *
 * Built through the services, like the conversions and the letters: each
 * application passes the same rules a real one does, and the uploads land on
 * the private disk through BerkasService. mahasiswa1@demo.test gets one
 * waiting application of each kind so the portal screens have something to
 * show on first login; other students carry the decided ones.
 *
 * Runs after BeasiswaSeeder (schemes), KonversiSeeder (the undecided
 * proposal) and PrestasiSeeder (the achievement catalogue).
 */
class SwalayanSeeder extends Seeder
{
    /** Same placeholder PDF the admissions and proposal seeders store. */
    private const PDF_CONTOH = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
        ."trailer<</Root 1 0 R>>\n%%EOF\n";

    public function run(): void
    {
        Portal::lupakanTerm();

        $term = TahunAkademik::aktif();
        $baak = Staff::where('email', 'baak@demo.test')->first();
        $keuangan = Staff::where('email', 'keuangan@demo.test')->first();
        $utama = Mahasiswa::where('email', 'mahasiswa1@demo.test')->first();

        if ($term === null || $baak === null || $keuangan === null || $utama === null) {
            return;
        }

        $this->cuti($term, $baak);
        $this->beasiswa($term, $keuangan, $utama);
        $this->prestasi($baak, $utama);
        $this->konversi($baak);
    }

    /**
     * One waiting and one refused leave application, plus the record behind
     * a student the demo already lists as on leave.
     */
    private function cuti(TahunAkademik $term, Staff $baak): void
    {
        $layanan = app(CutiService::class);

        // Leave is refused while a study plan is live, so the applicants are
        // active students with none in the active term.
        $calon = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->whereDoesntHave('krs', fn ($q) => $q
                ->where('tahun_akademik_id', $term->id)
                ->whereIn('status', [KrsStatus::Diajukan->value, KrsStatus::Disetujui->value]))
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($calon->count() < 2) {
            return;
        }

        $layanan->ajukanMandiri($calon[0], new AjuanCutiData(
            $term->id,
            JenisCuti::Sakit,
            'Menjalani perawatan pascaoperasi lutut selama kurang lebih empat bulan sesuai surat dokter terlampir.',
            $this->pdf('surat-dokter.pdf'),
        ));

        $ditolak = $layanan->ajukanMandiri($calon[1], new AjuanCutiData(
            $term->id,
            JenisCuti::Lainnya,
            'Mengikuti program magang mandiri di luar kota selama satu semester.',
        ));
        $layanan->tolak($ditolak, $baak, 'Magang dapat diakui lewat konversi MBKM tanpa cuti; silakan ajukan konversi kegiatan.');

        // The demo seeds a few students already on leave without the
        // application behind it. Their record is completed directly: the
        // service only grants leave to an active student, and these were
        // never active in this demo.
        Mahasiswa::query()
            ->where('status', StudentStatus::Cuti->value)
            ->whereDoesntHave('cuti')
            ->orderBy('id')
            ->limit(1)
            ->get()
            ->each(fn (Mahasiswa $m) => CutiMahasiswa::create([
                'mahasiswa_id' => $m->id,
                'tahun_akademik_id' => $term->id,
                'jenis' => JenisCuti::Akademik->value,
                'alasan' => 'Bekerja penuh waktu untuk membantu usaha keluarga selama satu semester.',
                'status' => LeaveStatus::Disetujui,
                'diajukan_at' => now()->subWeeks(6),
                'diproses_by_staff_id' => $baak->id,
                'diproses_at' => now()->subWeeks(5),
                'catatan' => 'Disetujui; aktif kembali paling lambat awal semester berikutnya.',
            ]));
    }

    /**
     * An open call with requested documents and applications in all three
     * states, granted through the ordinary award path.
     */
    private function beasiswa(TahunAkademik $term, Staff $keuangan, Mahasiswa $utama): void
    {
        // Mitra rather than Prestasi: the demo login already holds the
        // Prestasi award from BeasiswaSeeder and would be refused here.
        $skema = Beasiswa::where('kode', 'BS-MITRA')->first();

        if ($skema === null) {
            return;
        }

        $layanan = app(AjuanBeasiswaService::class);

        $periode = $layanan->bukaPeriode($skema, new PeriodeBeasiswaData(
            tahunAkademikId: $term->id,
            nama: 'Pendaftaran Beasiswa Mitra Industri '.$term->nama,
            dibukaMulai: Carbon::today()->subDays(7),
            dibukaSampai: Carbon::today()->addDays(21),
            syarat: "Mahasiswa aktif dengan IPK sekurang-kurangnya 2,50.\nDiutamakan yang berminat magang di perusahaan mitra.",
            ipkMinimum: 2.50,
            berkasDiminta: ['Transkrip nilai terakhir', 'Surat rekomendasi dosen wali'],
        ), $keuangan);

        $pemohon = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->whereKeyNot($utama->id)
            ->orderBy('id')
            ->limit(30)
            ->get()
            ->filter(fn (Mahasiswa $m): bool => $layanan->halangan($periode, $m) === null)
            ->values();

        $ajukan = fn (Mahasiswa $m, string $motivasi) => $layanan->ajukan($periode, $m, new AjuanBeasiswaData(
            $motivasi,
            [$this->pdf('transkrip.pdf'), $this->pdf('rekomendasi.pdf')],
        ));

        if ($layanan->halangan($periode, $utama) === null) {
            $ajukan($utama, 'IPK saya stabil di atas 3,0 dan saya aktif sebagai asisten praktikum; beasiswa ini akan meringankan biaya kuliah keluarga.');
        }

        if ($pemohon->count() >= 2) {
            $layanan->tetapkan(
                $ajukan($pemohon[0], 'Saya juara kompetisi pemrograman tingkat wilayah dan ingin fokus pada riset tugas akhir.'),
                $keuangan,
                nomorSk: 'SK/DEMO/BS/001',
                catatan: 'Memenuhi syarat dan berkas lengkap.',
            );

            $layanan->tolak(
                $ajukan($pemohon[1], 'Saya membutuhkan dukungan biaya untuk semester ini.'),
                $keuangan,
                'Surat rekomendasi belum ditandatangani dosen wali; silakan mengajukan kembali pada periode berikutnya.',
            );
        }
    }

    /** A waiting report for the demo login, and one verified and one refused elsewhere. */
    private function prestasi(Staff $baak, Mahasiswa $utama): void
    {
        $kategori = PoinKategori::where('kode', 'LOMBA-NAS')->first();

        if ($kategori === null) {
            return;
        }

        $layanan = app(PoinKemahasiswaanService::class);

        $laporan = fn (string $judul, int $hariLalu) => new LaporanPrestasiData(
            tanggal: now()->subDays($hariLalu)->toDateString(),
            judul: $judul,
            keterangan: 'Dilaporkan dari portal mahasiswa.',
            tahunAkademikId: TahunAkademik::aktif()?->id,
            bukti: $this->pdf('piagam.pdf'),
            simkatmawa: new DataSimkatmawa(
                peringkat: PeringkatPrestasi::Juara2,
                bidangLomba: BidangLomba::RisetStem,
                penyelenggara: 'Asosiasi Kompetisi Mahasiswa (demo)',
                jumlahPtPeserta: 18,
            ),
        );

        $layanan->laporkan($utama, $kategori, $laporan('Juara 2 Kompetisi Desain Antarmuka Nasional', 12));

        $lain = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->whereKeyNot($utama->id)
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        if ($lain->count() < 2) {
            return;
        }

        $layanan->verifikasi($layanan->laporkan($lain[0], $kategori, $laporan('Juara 2 Hackathon Layanan Publik', 20)), $baak);
        $layanan->tolak(
            $layanan->laporkan($lain[1], $kategori, $laporan('Peserta Lomba Esai Nasional', 35)),
            $baak,
            'Piagam menyatakan peserta, bukan juara; laporkan ulang dengan kategori yang sesuai.',
        );
    }

    /** The proposal KonversiSeeder left undecided now waits on a document. */
    private function konversi(Staff $baak): void
    {
        $usulan = KonversiKredit::query()
            ->where('status', StatusKonversi::Diajukan->value)
            ->orderBy('id')
            ->first();

        if ($usulan === null) {
            return;
        }

        app(KonversiService::class)->mintaDokumen(
            $usulan,
            $baak,
            'Silabus mata kuliah asal yang dilegalisasi, untuk membandingkan capaian pembelajarannya.',
        );
    }

    private function pdf(string $nama): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'oa-demo-');
        file_put_contents($path, self::PDF_CONTOH);

        return new UploadedFile($path, $nama, 'application/pdf', null, true);
    }
}
