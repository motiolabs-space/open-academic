<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Kemahasiswaan\KegiatanLapanganData;
use App\DTOs\Kemahasiswaan\KelompokKegiatanData;
use App\DTOs\Kemahasiswaan\LogbookData;
use App\DTOs\Kemahasiswaan\LokasiKegiatanData;
use App\Enums\JenisLogbook;
use App\Enums\JenisLokasiKegiatan;
use App\Enums\StudentActivityType;
use App\Enums\StudentStatus;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Kemahasiswaan\KegiatanLapanganService;
use App\Services\Kemahasiswaan\LogbookKegiatanService;
use Illuminate\Database\Seeder;

/**
 * One KKN period in the active term, through the same service the screen uses.
 *
 * Three villages, active students from every programme formed into mixed
 * groups by the automatic rule, a field supervisor per group — dosen1 leads
 * the first, so the lecturer demo account has a group to open — and a few
 * log entries from the first group. Nobody is marked yet: entering the marks
 * and converting them into the KKN course is the demo.
 */
class KegiatanLapanganSeeder extends Seeder
{
    private const DESA = [
        ['Sukamaju', 'Cibodas', 'Kabupaten Bandung Barat'],
        ['Mekarsari', 'Lembang', 'Kabupaten Bandung Barat'],
        ['Cipanas', 'Pacet', 'Kabupaten Cianjur'],
    ];

    public function run(KegiatanLapanganService $layanan, LogbookKegiatanService $logbook): void
    {
        $term = TahunAkademik::aktif();

        if ($term === null) {
            return;
        }

        $mulai = $term->tanggal_mulai->copy()->addWeeks(2);

        $kegiatan = $layanan->simpan(KegiatanLapanganData::fromArray([
            'tahun_akademik_id' => $term->id,
            'jenis' => StudentActivityType::Kkn->value,
            'nama' => 'KKN Reguler '.$term->nama,
            'nomor_sk' => '045/UN/LPPM/KKN/'.$term->tahun_mulai,
            'tanggal_sk' => $term->tanggal_mulai->toDateString(),
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $mulai->copy()->addWeeks(6)->toDateString(),
            'ukuran_kelompok' => 6,
            'min_sks' => 0,
            'bobot_mitra' => 40,
            'keterangan' => 'Pengabdian kepada masyarakat di tiga desa mitra.',
        ]));

        foreach (self::DESA as [$desa, $kecamatan, $kabupaten]) {
            $layanan->simpanLokasi($kegiatan, LokasiKegiatanData::fromArray([
                'jenis' => JenisLokasiKegiatan::Desa->value,
                'nama' => 'Desa '.$desa,
                'kecamatan' => $kecamatan,
                'kabupaten' => $kabupaten,
                'provinsi' => 'Jawa Barat',
                'kuota_kelompok' => 1,
            ]));
        }

        $peserta = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->orderByRaw("CASE WHEN email = 'mahasiswa1@demo.test' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->limit(18)
            ->get();

        foreach ($peserta as $mahasiswa) {
            $layanan->daftarkan($kegiatan, $mahasiswa);
        }

        if ($peserta->isEmpty()) {
            return;
        }

        $kelompok = $layanan->bentukKelompokOtomatis($kegiatan);

        $dosen = Dosen::query()
            ->aktif()
            ->orderByRaw("CASE WHEN email = 'dosen1@demo.test' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->limit($kelompok->count())
            ->get()
            ->values();

        foreach ($kelompok->values() as $i => $g) {
            $layanan->aturKelompok($g, new KelompokKegiatanData(
                lokasiId: $g->lokasi_id,
                dosenPembimbingId: $dosen->get($i % max(1, $dosen->count()))?->id,
                pembimbingMitraNama: ['Bapak Ujang Suherman', 'Ibu Neneng Rohaeti', 'Bapak Asep Saepudin'][$i % 3],
                pembimbingMitraJabatan: 'Kepala Desa',
                pembimbingMitraTelepon: '08122'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                ketuaAktivitasId: $g->anggota()->orderBy('id')->value('id'),
            ));
        }

        $pertama = $kelompok->first();

        foreach ($pertama->anggota()->orderBy('id')->limit(3)->get() as $urutan => $aktivitas) {
            // A demo installed before the period starts has nothing to log yet.
            if ($mulai->copy()->addDays($urutan)->isFuture()) {
                break;
            }

            $logbook->catat($aktivitas, new LogbookData(
                jenis: JenisLogbook::Harian,
                tanggal: $mulai->copy()->addDays($urutan)->toDateString(),
                judul: ['Pembukaan dan perkenalan dengan perangkat desa', 'Survei potensi UMKM', 'Penyuluhan pengelolaan sampah'][$urutan],
                uraian: 'Kegiatan berjalan sesuai rencana kerja kelompok.',
            ));
        }
    }
}
