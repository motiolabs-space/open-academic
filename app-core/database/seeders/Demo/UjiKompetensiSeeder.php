<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Kemahasiswaan\HasilUjiKompetensiData;
use App\Enums\EducationLevel;
use App\Enums\HasilUjiKompetensi;
use App\Enums\LembagaAkreditasi;
use App\Enums\PeringkatAkreditasi;
use App\Enums\StudentStatus;
use App\Models\Akademik\AkreditasiProdi;
use App\Models\Akademik\Fakultas;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\UjiKompetensiService;
use Illuminate\Database\Seeder;

/**
 * A health programme with one graduate waiting on the competency examination.
 *
 * Small on purpose: the demo campus is an IT faculty, and this programme
 * exists to show one path — yudisium confirmed, "Menunggu Uji Kompetensi" on
 * the yudisium screen and in the term's activity report, one failed sitting in
 * the history — not to be a second campus. It has no curriculum of its own.
 *
 * The waiting state is written directly, as KelulusanSeeder writes its
 * graduates: the demo carries no nursing coursework for YudisiumService to
 * check. Everything after it goes through UjiKompetensiService, so the
 * per-term record and the failed sitting are the ones the application itself
 * would have produced.
 */
class UjiKompetensiSeeder extends Seeder
{
    public function run(UjiKompetensiService $ukom): void
    {
        $aktif = TahunAkademik::aktif();
        $lalu = TahunAkademik::query()->where('kode', '<', $aktif->kode)->orderByDesc('kode')->first();

        if ($lalu === null) {
            return;
        }

        $fakultas = Fakultas::create([
            'kode' => 'FIKES',
            'nama' => 'Fakultas Ilmu Kesehatan',
            'singkatan' => 'FIKES',
        ]);

        $keperawatan = Prodi::create([
            'fakultas_id' => $fakultas->id,
            'kode' => '14401',
            'kode_pddikti' => fake()->uuid(),
            'nama' => 'Keperawatan',
            'jenjang' => EducationLevel::D3,
            'gelar_pendek' => 'A.Md.Kep.',
            'gelar_panjang' => 'Ahli Madya Keperawatan',
            'akreditasi' => 'Baik Sekali',
            'sks_lulus' => 108,
            'wajib_tugas_akhir' => false,
            'wajib_ukom' => true,
            'is_active' => true,
        ]);

        AkreditasiProdi::create([
            'prodi_id' => $keperawatan->id,
            'lembaga' => LembagaAkreditasi::LamPtkes,
            'peringkat' => PeringkatAkreditasi::BaikSekali,
            'nomor_sk' => '0877/LAM-PTKes/DEMO/2023',
            'tanggal_sk' => now()->subYears(3)->startOfDay(),
            'tanggal_berlaku' => now()->subYears(3)->startOfDay(),
            'tanggal_kedaluwarsa' => now()->addYears(2)->startOfDay(),
        ]);

        $angkatan = (int) substr($lalu->kode, 0, 4) - 2;

        $mahasiswa = Mahasiswa::factory()->create([
            'nim' => $keperawatan->kode.$angkatan.'001',
            'nama' => 'Ratna Kusumawati',
            'email' => 'mahasiswa.ukom@demo.test',
            'password' => 'password',
            'prodi_id' => $keperawatan->id,
            'angkatan' => $angkatan,
            'term_masuk' => $angkatan.'1',
            'status' => StudentStatus::MenungguUkom,
        ])->assignRole('mahasiswa');

        // Her last term of coursework, frozen: the record the waiting terms
        // carry IPK and credits forward from.
        StatusMahasiswa::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $lalu->id,
            'status' => StudentStatus::Aktif,
            'semester_ke' => 6,
            'sks_semester' => 18,
            'sks_kumulatif' => 108,
            'ips' => 3.42,
            'ipk' => 3.36,
            'is_final' => true,
            'finalized_at' => $lalu->tanggal_selesai,
        ]);

        $yudisium = Yudisium::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $lalu->id,
            'nomor_sk' => sprintf('SK/YUD/FIKES/%d/001', $lalu->tanggal_selesai->year),
            'tanggal_yudisium' => $lalu->tanggal_selesai,
            'tanggal_lulus' => null,
            'total_sks' => 108,
            'ipk' => 3.36,
            'predikat' => Yudisium::predikatUntuk(3.36),
            'status' => 'ditetapkan',
            'ditetapkan_at' => $lalu->tanggal_selesai,
        ]);

        // The active term's record: what the AKM push reports for her.
        $ukom->catatSemesterMenunggu($aktif);

        // One sitting already behind her, not passed — the history the
        // yudisium screen shows next to the form for the next one.
        $tanggal = $yudisium->tanggal_yudisium->copy()->addWeeks(3);

        if ($tanggal->isFuture()) {
            $tanggal = today();
        }

        $ukom->catatHasil($mahasiswa, new HasilUjiKompetensiData(
            tanggalUjian: $tanggal,
            hasil: HasilUjiKompetensi::TidakLulus,
            catatan: 'Uji kompetensi periode pertama.',
        ), Staff::query()->orderBy('id')->firstOrFail());
    }
}
