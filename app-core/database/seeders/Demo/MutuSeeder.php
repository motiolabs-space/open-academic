<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\JenisUnitKerja;
use App\Enums\StatusPeriodeKinerja;
use App\Enums\SumberRealisasi;
use App\Models\Akademik\Prodi;
use App\Models\Kinerja\PeriodeKinerja;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Models\Spmi\StandarMutu;
use App\Services\Kinerja\KinerjaService;
use App\Services\Spmi\SpmiService;
use Illuminate\Database\Seeder;

/**
 * The organisation tree, the performance plan on top of it, and one audit
 * running against it.
 *
 * Three modules share this seeder because they share one subject: the unit
 * tree. Seeding them apart would mean inventing three organisations for one
 * campus.
 *
 * The audit is deliberately caught **mid-cycle**, not finished. A demo where
 * every finding is closed shows none of the screens that matter — no overdue
 * deadline, no correction awaiting somebody else's verification, no refusal to
 * close. What is arranged here instead:
 *
 *   - one major finding past its deadline, still open;
 *   - one minor finding whose correction is recorded but **not yet verified**,
 *     because verification by a second person is the whole point;
 *   - one observation already closed without any correction, which is allowed
 *     and is the case people misread as a bug.
 */
class MutuSeeder extends Seeder
{
    public function run(): void
    {
        $unit = $this->seedUnitKerja();

        $this->seedRencanaKinerja($unit);
        $this->seedSpmi($unit);
    }

    /**
     * @return array<string, UnitKerja>
     */
    private function seedUnitKerja(): array
    {
        $rektorat = UnitKerja::create([
            'kode' => 'REKTORAT',
            'nama' => 'Rektorat',
            'jenis' => JenisUnitKerja::Struktural,
        ]);

        $lpm = UnitKerja::create([
            'kode' => 'LPM',
            'nama' => 'Lembaga Penjaminan Mutu',
            'jenis' => JenisUnitKerja::Struktural,
            'parent_id' => $rektorat->id,
        ]);

        $baak = UnitKerja::create([
            'kode' => 'BAAK',
            'nama' => 'Biro Administrasi Akademik',
            'jenis' => JenisUnitKerja::Struktural,
            'parent_id' => $rektorat->id,
        ]);

        /*
         * Unit akademik menunjuk prodi lewat `prodi_id`, dan itulah yang membuat
         * indikator berlingkup prodi dapat dihitung untuknya. Tanpa penunjuk
         * itu, sasaran prodi hanya judul.
         */
        $prodi = Prodi::query()->orderBy('id')->first();

        $unitProdi = $prodi === null ? null : UnitKerja::create([
            'kode' => 'PRODI-'.$prodi->id,
            'nama' => 'Program Studi '.$prodi->nama,
            'jenis' => JenisUnitKerja::Akademik,
            'parent_id' => $rektorat->id,
            'prodi_id' => $prodi->id,
        ]);

        // Auditor ditempatkan di LPM, bukan di unit yang diaudit — kalau tidak,
        // layanan menolak auditnya, dan benar demikian.
        $staf = Staff::query()->orderBy('id')->take(2)->get();

        if ($staf->count() >= 2) {
            $staf[0]->update(['unit_kerja_id' => $baak->id]);
            $staf[1]->update(['unit_kerja_id' => $lpm->id]);
        }

        return array_filter([
            'rektorat' => $rektorat,
            'lpm' => $lpm,
            'baak' => $baak,
            'prodi' => $unitProdi,
        ]);
    }

    /** @param array<string, UnitKerja> $unit */
    private function seedRencanaKinerja(array $unit): void
    {
        $kinerja = app(KinerjaService::class);

        $periode = PeriodeKinerja::create([
            'nama' => 'Rencana Kinerja '.now()->year,
            'tahun' => now()->year,
            'mulai' => now()->startOfYear()->toDateString(),
            'selesai' => now()->endOfYear()->toDateString(),
            'status' => StatusPeriodeKinerja::Draf,
        ]);

        $sasaranKampus = $kinerja->buatSasaran($periode, $unit['rektorat'], [
            'judul' => 'Meningkatkan mutu lulusan dan keterlibatan mitra',
            'deskripsi' => 'Sasaran tingkat institusi yang diturunkan ke unit di bawahnya.',
            'urutan' => 1,
        ]);

        $kinerja->tambahUkuran($sasaranKampus, [
            'nama' => 'Mahasiswa aktif',
            'sumber_realisasi' => SumberRealisasi::Dihitung,
            'indikator_kunci' => 'mahasiswa_aktif',
            'target' => 500,
        ]);

        // Diketik, karena angkanya memang tidak dimiliki aplikasi ini — dan
        // ukuran yang dihitung akan menolak diketik, jadi keduanya harus benar
        // sejak awal.
        $kinerja->tambahUkuran($sasaranKampus, [
            'nama' => 'Kerja sama mitra baru',
            'satuan' => 'dokumen',
            'sumber_realisasi' => SumberRealisasi::Dilaporkan,
            'target' => 12,
        ]);

        if (isset($unit['prodi'])) {
            $sasaranProdi = $kinerja->buatSasaran($periode, $unit['prodi'], [
                'judul' => 'Meningkatkan capaian akademik mahasiswa',
                'parent_id' => $sasaranKampus->id,
                'urutan' => 1,
            ]);

            $kinerja->tambahUkuran($sasaranProdi, [
                'nama' => 'Rerata IPK',
                'sumber_realisasi' => SumberRealisasi::Dihitung,
                'indikator_kunci' => 'rerata_ipk',
                'target' => 3.25,
            ]);
        }

        // Dijalankan, lalu diukur: periode draf tidak menerima capaian, dan
        // periode yang tidak pernah diukur menampilkan realisasi kosong yang
        // tidak menguji apa pun.
        $kinerja->jalankan($periode);
        $kinerja->ukurOtomatis($periode);

        $pelapor = Staff::query()->orderBy('id')->first();
        $dilaporkan = $sasaranKampus->ukuran()
            ->where('sumber_realisasi', SumberRealisasi::Dilaporkan->value)
            ->first();

        if ($dilaporkan !== null) {
            $kinerja->catatCapaian(
                $dilaporkan,
                7,
                now()->subMonth()->toDateString(),
                $pelapor,
                'Rekap paruh tahun.',
            );
        }
    }

    /** @param array<string, UnitKerja> $unit */
    private function seedSpmi(array $unit): void
    {
        $spmi = app(SpmiService::class);

        $standarKurikulum = StandarMutu::create([
            'kode' => 'SM-01',
            'nama' => 'Standar Kompetensi Lulusan',
            'pernyataan' => 'Setiap program studi menetapkan capaian pembelajaran lulusan '
                .'dan meninjaunya sekurang-kurangnya setiap dua tahun.',
            'kategori' => 'pendidikan',
            'siklus' => 'pelaksanaan',
            'unit_penanggung_jawab_id' => $unit['prodi']->id ?? null,
        ]);

        StandarMutu::create([
            'kode' => 'SM-02',
            'nama' => 'Standar Proses Pembelajaran',
            'pernyataan' => 'Setiap mata kuliah memiliki RPS yang terbit sebelum perkuliahan '
                .'dimulai dan jurnal yang terisi setiap pertemuan.',
            'kategori' => 'pendidikan',
            'siklus' => 'evaluasi',
            'melampaui_sndikti' => true,
            'unit_penanggung_jawab_id' => $unit['baak']->id,
        ]);

        $auditor = Staff::query()->where('unit_kerja_id', $unit['lpm']->id)->first()
            ?? Staff::query()->orderBy('id')->first();

        if ($auditor === null) {
            return;
        }

        $audit = $spmi->rencanakanAudit($unit['baak'], [
            'nama' => 'AMI '.now()->year.' — Biro Administrasi Akademik',
            'tahun' => now()->year,
            'auditor_staff_id' => $auditor->id,
            'tanggal_audit' => now()->subMonths(2)->toDateString(),
        ]);

        $spmi->mulaiAudit($audit);

        $mayor = $spmi->catatTemuan($audit, [
            'jenis' => 'mayor',
            'standar_mutu_id' => $standarKurikulum->id,
            'uraian' => 'Peninjauan kurikulum tidak terdokumentasi pada dua program studi.',
            'akar_masalah' => 'Belum ada prosedur baku untuk merekam berita acara peninjauan.',
        ]);

        $minor = $spmi->catatTemuan($audit, [
            'jenis' => 'minor',
            'uraian' => 'Sebagian jurnal perkuliahan terisi setelah pertemuan berakhir.',
        ]);

        $observasi = $spmi->catatTemuan($audit, [
            'jenis' => 'observasi',
            'uraian' => 'Penyimpanan berkas akademik dapat dirapikan agar penelusuran lebih cepat.',
        ]);

        /*
         * Temuan mayor dibuat lewat tenggat, karena angka "lewat tenggat" adalah
         * satu-satunya di layar itu yang menuntut tindakan hari ini — dan angka
         * yang selalu nol tidak pernah diperiksa siapa pun.
         *
         * Ditulis langsung ke kolomnya: tenggat diturunkan dari beratnya temuan,
         * dan layanan memang tidak menyediakan jalan untuk mengubahnya.
         */
        $mayor->forceFill(['tenggat' => now()->subWeeks(2)->toDateString()])->save();

        // Perbaikan tercatat tapi belum diverifikasi — verifikasi oleh orang
        // kedua adalah seluruh maksudnya, jadi keadaan inilah yang perlu
        // terlihat di layar.
        $spmi->catatTindakLanjut($minor, [
            'rencana' => 'Menetapkan batas pengisian jurnal 1x24 jam setelah pertemuan.',
            'target_selesai' => now()->addMonth()->toDateString(),
            'realisasi' => 'Surat edaran terbit dan disosialisasikan ke seluruh dosen.',
            'tanggal_realisasi' => now()->subWeek()->toDateString(),
        ], $auditor);

        // Observasi ditutup tanpa perbaikan. Ini diizinkan, dan justru kasus
        // inilah yang paling sering dibaca orang sebagai cacat.
        $spmi->tutupTemuan($observasi, $auditor);

        $spmi->tutupAudit($audit->refresh(), 'Dua ketidaksesuaian dan satu observasi.');
    }
}
