<?php

declare(strict_types=1);

use App\DTOs\Sdm\AngkaKreditManualData;
use App\Enums\EducationLevel;
use App\Enums\JabatanFungsional;
use App\Enums\KesimpulanBkd;
use App\Enums\SemesterType;
use App\Enums\StatusBkd;
use App\Enums\SumberAngkaKredit;
use App\Enums\UnsurAngkaKredit;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\AngkaKreditDosen;
use App\Models\Sdm\BkdLaporan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\RiwayatPendidikanDosen;
use App\Models\Sdm\Staff;
use App\Services\Sdm\AngkaKreditService;
use App\Services\Sdm\BkdService;
use App\Services\Sdm\PortofolioService;
use Database\Seeders\RolePermissionSeeder;

/**
 * Buku besar angka kredit #40.
 *
 * Tiga hal yang dipaku: konversi SKS × 4 hanya untuk kinerja sebelum 2026,
 * batas Kum D/E 10 % yang ditegakkan pada total SESUDAH pembatasan, dan bentuk
 * lembar penyetaraan sampai ke jenis selnya. Selebihnya memastikan buku besar
 * tetap buku besar: baris turunan mengikuti sumbernya, dan tidak ada yang
 * "menilai".
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();

    $this->dosen = Dosen::factory()->create([
        'is_active' => true, 'nama' => 'Siti Rahmawati', 'nuptk' => '1234567890123456', 'status_kepegawaian' => 'tetap',
    ]);
    $this->dosen->assignRole('dosen');

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');

    $this->ak = app(AngkaKreditService::class);
});

function jabatanAk(JabatanFungsional $jabatan, string $tmt, float $angkaKredit, ?Dosen $dosen = null): void
{
    app(PortofolioService::class)->catatJabatan($dosen ?? test()->dosen, $jabatan, $tmt, $angkaKredit);
}

/** @param array<string, int> $sks hundredths per UnsurBkd value */
function bkdDisahkan(int $tahun, SemesterType $semester, array $sks, ?Dosen $dosen = null): BkdLaporan
{
    $term = TahunAkademik::query()->where('kode', $semester->termCode($tahun))->first()
        ?? TahunAkademik::factory()->term($tahun, $semester)->create();

    return BkdLaporan::create([
        'dosen_id' => ($dosen ?? test()->dosen)->id,
        'tahun_akademik_id' => $term->id,
        'status' => StatusBkd::Disahkan,
        'sks_pendidikan' => $sks['pendidikan'] ?? 0,
        'sks_penelitian' => $sks['penelitian'] ?? 0,
        'sks_pengabdian' => $sks['pengabdian'] ?? 0,
        'sks_penunjang' => $sks['penunjang'] ?? 0,
        'sks_total' => array_sum($sks),
    ]);
}

function manualAk(SumberAngkaKredit $sumber, UnsurAngkaKredit $unsur, array $isian = []): AngkaKreditManualData
{
    return new AngkaKreditManualData(
        sumber: $sumber,
        unsur: $unsur,
        semesterKode: $isian['semester'] ?? null,
        sksRatus: $isian['sks'] ?? null,
        nilaiRatus: $isian['nilai'] ?? null,
        tanggal: $isian['tanggal'] ?? null,
        keterangan: $isian['keterangan'] ?? null,
    );
}

/** Cell values of the first sheet of an .xlsx, row by row. */
function selXlsx(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
    $zip->close();

    $baris = [];

    foreach ($xml->sheetData->row as $row) {
        $sel = [];

        foreach ($row->c as $c) {
            $sel[] = [
                'tipe' => (string) $c['t'] === 'inlineStr' ? 'teks' : 'angka',
                'nilai' => (string) $c['t'] === 'inlineStr' ? (string) $c->is->t : (string) $c->v,
            ];
        }

        $baris[] = $sel;
    }

    return $baris;
}

describe('konversi', function () {
    it('mengonversi SKS BKD × 4 menjadi angka kredit, dalam perseratus', function () {
        expect($this->ak->konversiSks(1250))->toBe(5000)
            ->and($this->ak->konversiSks(1))->toBe(4)
            ->and($this->ak->konversiSks(1001, 350))->toBe(3504);
    });

    it('menerapkan faktor ketentuan hanya pada kinerja yang mulai sebelum 2026', function () {
        expect($this->ak->faktorUntuk(now()->setDate(2025, 9, 1)))->toBe(['faktor_ratus' => 400, 'perkiraan' => false])
            ->and($this->ak->faktorUntuk(now()->setDate(2026, 2, 1)))->toBe(['faktor_ratus' => 400, 'perkiraan' => true]);
    });
});

describe('baris turunan', function () {
    it('mencatat BKD yang disahkan per unsur, Kum B sampai E', function () {
        $laporan = bkdDisahkan(2024, SemesterType::Ganjil, [
            'pendidikan' => 1000, 'penelitian' => 300, 'pengabdian' => 150, 'penunjang' => 0,
        ]);

        $baris = $this->ak->catatBkd($laporan)->keyBy(fn ($b) => $b->unsur->value);

        expect($baris)->toHaveCount(3)
            ->and($baris['pengajaran']->nilai_ratus)->toBe(4000)
            ->and($baris['penelitian']->nilai_ratus)->toBe(1200)
            ->and($baris['pengabdian']->nilai_ratus)->toBe(600)
            ->and($baris['pengajaran']->perkiraan)->toBeFalse()
            ->and($baris['pengajaran']->semester_kode)->toBe('20241');
    });

    it('tidak menggandakan baris saat disusun ulang', function () {
        $laporan = bkdDisahkan(2024, SemesterType::Ganjil, ['pendidikan' => 1000]);

        $this->ak->catatBkd($laporan);
        $this->ak->catatBkd($laporan);
        $this->ak->susun($this->dosen);

        expect(AngkaKreditDosen::count())->toBe(1);
    });

    it('tidak mencatat BKD yang belum disahkan', function (StatusBkd $status) {
        $laporan = bkdDisahkan(2024, SemesterType::Ganjil, ['pendidikan' => 1000]);
        $laporan->update(['status' => $status]);

        expect($this->ak->catatBkd($laporan))->toBeEmpty()
            ->and(AngkaKreditDosen::count())->toBe(0);
    })->with([StatusBkd::Diajukan, StatusBkd::Dinilai, StatusBkd::Dikembalikan]);

    it('menandai kinerja sejak 2026 sebagai perkiraan', function () {
        $laporan = bkdDisahkan(2025, SemesterType::Genap, ['pendidikan' => 1000]);

        expect($this->ak->catatBkd($laporan)->first()->perkiraan)->toBeTrue();
    });

    it('mencatat BKD saat laporan disahkan lewat alurnya', function () {
        $term = TahunAkademik::factory()->term(2024, SemesterType::Genap)->create();
        $laporan = BkdLaporan::create([
            'dosen_id' => $this->dosen->id, 'tahun_akademik_id' => $term->id,
            'status' => StatusBkd::Dinilai, 'kesimpulan' => KesimpulanBkd::Memenuhi,
            'sks_pendidikan' => 1200, 'sks_total' => 1200,
        ]);

        app(BkdService::class)->sahkan($laporan, $this->staf);

        expect((int) AngkaKreditDosen::where('bkd_laporan_id', $laporan->id)->sum('nilai_ratus'))->toBe(4800);
    });

    it('mencatat ijazah magister dan doktor sebagai Kum A, dan menariknya bila ijazahnya dihapus', function () {
        foreach ([[EducationLevel::S1, 2008], [EducationLevel::S2, 2012], [EducationLevel::S3, 2019]] as [$jenjang, $tahun]) {
            RiwayatPendidikanDosen::create([
                'dosen_id' => $this->dosen->id, 'jenjang' => $jenjang, 'perguruan_tinggi' => 'Kampus',
                'negara' => 'Indonesia', 'tahun_lulus' => $tahun,
            ]);
        }

        $baris = $this->ak->catatPendidikan($this->dosen);

        expect($baris->pluck('nilai_ratus')->sort()->values()->all())->toBe([15000, 20000])
            ->and($baris->every(fn ($b) => $b->unsur === UnsurAngkaKredit::Pendidikan))->toBeTrue();

        RiwayatPendidikanDosen::where('jenjang', EducationLevel::S3->value)->first()->delete();
        $this->ak->catatPendidikan($this->dosen);

        expect((int) AngkaKreditDosen::sum('nilai_ratus'))->toBe(15000);
    });

    it('membiarkan baris manual saat menyusun ulang', function () {
        $this->ak->catatManual($this->dosen, $this->staf, manualAk(SumberAngkaKredit::KonversiManual, UnsurAngkaKredit::Penelitian, [
            'semester' => '20191', 'sks' => 300,
        ]));

        $this->ak->susun($this->dosen);

        expect(AngkaKreditDosen::where('sumber', SumberAngkaKredit::KonversiManual->value)->count())->toBe(1);
    });
});

describe('baris manual', function () {
    it('mengonversi semester lama × 4 atas nama staf yang mencatat', function () {
        $baris = $this->ak->catatManual($this->dosen, $this->staf, manualAk(SumberAngkaKredit::KonversiManual, UnsurAngkaKredit::Pengajaran, [
            'semester' => '20182', 'sks' => 1200,
        ]));

        expect($baris->nilai_ratus)->toBe(4800)
            ->and($baris->faktor_ratus)->toBe(400)
            ->and($baris->dicatat_oleh)->toBe($this->staf->id)
            ->and($baris->tanggal->toDateString())->toBe('2019-07-31');
    });

    it('menolak konversi untuk semester sejak 2026', function () {
        expect(fn () => $this->ak->catatManual($this->dosen, $this->staf, manualAk(SumberAngkaKredit::KonversiManual, UnsurAngkaKredit::Pengajaran, [
            'semester' => '20252', 'sks' => 1200,
        ])))->toThrow(AturanAkademikException::class, 'sebelum 2026');
    });

    it('menolak mengonversi semester yang sudah terhitung dari BKD disahkan', function () {
        $this->ak->catatBkd(bkdDisahkan(2024, SemesterType::Ganjil, ['pendidikan' => 1000]));

        expect(fn () => $this->ak->catatManual($this->dosen, $this->staf, manualAk(SumberAngkaKredit::KonversiManual, UnsurAngkaKredit::Pengajaran, [
            'semester' => '20241', 'sks' => 1000,
        ])))->toThrow(AturanAkademikException::class, 'dua kali');
    });

    it('menerima penyesuaian negatif dengan keterangan, dan menolaknya tanpa keterangan', function () {
        $baris = $this->ak->catatManual($this->dosen, $this->staf, manualAk(SumberAngkaKredit::Penyesuaian, UnsurAngkaKredit::Penunjang, [
            'nilai' => -250, 'tanggal' => '2025-01-15', 'keterangan' => 'Kepanitiaan tercatat ganda.',
        ]));

        expect($baris->nilai_ratus)->toBe(-250)
            ->and(fn () => $this->ak->catatManual($this->dosen, $this->staf, manualAk(SumberAngkaKredit::Penyesuaian, UnsurAngkaKredit::Penunjang, [
                'nilai' => 100, 'tanggal' => '2025-01-15',
            ])))->toThrow(AturanAkademikException::class, 'keterangan');
    });

    it('tidak menghapus baris turunan dengan tangan', function () {
        $turunan = $this->ak->catatBkd(bkdDisahkan(2024, SemesterType::Ganjil, ['pendidikan' => 1000]))->first();

        expect(fn () => $this->ak->hapusManual($turunan))->toThrow(AturanAkademikException::class, 'mengikuti sumbernya')
            ->and(fn () => $this->ak->catatManual($this->dosen, $this->staf, manualAk(SumberAngkaKredit::Bkd, UnsurAngkaKredit::Pengajaran)))
            ->toThrow(AturanAkademikException::class);
    });
});

describe('batas Kum D dan E', function () {
    $kum = fn (int $a, int $b, int $c, int $d, int $e): array => [
        'pendidikan' => $a, 'pengajaran' => $b, 'penelitian' => $c, 'pengabdian' => $d, 'penunjang' => $e,
    ];

    it('tidak menyentuh unsur yang masih di bawah 10 %', function () use ($kum) {
        expect($this->ak->batasiUnsur($kum(0, 8000, 2000, 500, 500)))->toBe($kum(0, 8000, 2000, 500, 500));
    });

    it('membatasi Kum D terhadap total sesudah pembatasan, bukan sebelumnya', function () use ($kum) {
        /*
         * A+B+C = 100, D = 50. Sepuluh persen dari total mentah (150) adalah 15
         * — tetapi 15 dari total 115 adalah 13 %, dan lembar yang diunggah
         * memeriksa terhadap total yang tertulis di lembar itu. Titik tetapnya
         * (S + E) / 9 = 11,11 → 11,11.
         */
        $diakui = $this->ak->batasiUnsur($kum(0, 7000, 3000, 5000, 0));

        expect($diakui['pengabdian'])->toBe(1111)
            ->and($diakui['pengabdian'] * 100)->toBeLessThanOrEqual(array_sum($diakui) * 10);
    });

    it('membatasi D dan E bersama-sama', function () use ($kum) {
        // Keduanya terbatas: D' = E' = S / 8.
        $diakui = $this->ak->batasiUnsur($kum(0, 8000, 0, 9000, 9000));

        expect($diakui['pengabdian'])->toBe(1000)
            ->and($diakui['penunjang'])->toBe(1000)
            ->and(array_sum($diakui))->toBe(10000);
    });

    it('tidak pernah menaikkan unsur di atas yang dikerjakan', function () use ($kum) {
        $diakui = $this->ak->batasiUnsur($kum(20000, 0, 0, 100, 0));

        expect($diakui['pengabdian'])->toBe(100);
    });

    it('menerapkan batas pada rekap, bukan pada baris buku besar', function () {
        $this->ak->catatBkd(bkdDisahkan(2024, SemesterType::Ganjil, ['pendidikan' => 1000, 'pengabdian' => 1000]));

        $rekap = $this->ak->rekap($this->dosen);

        expect($rekap->kum(UnsurAngkaKredit::Pengabdian))->toBe(4000)
            ->and($rekap->diakui(UnsurAngkaKredit::Pengabdian))->toBe(444)
            ->and($rekap->dipangkas(UnsurAngkaKredit::Pengabdian))->toBeTrue()
            ->and((int) AngkaKreditDosen::where('unsur', 'pengabdian')->sum('nilai_ratus'))->toBe(4000);
    });
});

describe('rekap dan kesiapan', function () {
    it('menjumlah AK terakhir dengan DUPAK sesudah TMT jabatan berlaku', function () {
        jabatanAk(JabatanFungsional::AsistenAhli, '2022-03-01', 150);

        $this->ak->catatBkd(bkdDisahkan(2021, SemesterType::Ganjil, ['pendidikan' => 1200])); // sebelum TMT
        $this->ak->catatBkd(bkdDisahkan(2023, SemesterType::Ganjil, ['pendidikan' => 1000, 'penelitian' => 500]));

        $rekap = $this->ak->rekap($this->dosen);

        expect($rekap->jabatan)->toBe(JabatanFungsional::AsistenAhli)
            ->and($rekap->akTerakhirRatus)->toBe(15000)
            ->and($rekap->totalDupakRatus)->toBe(6000)
            ->and($rekap->akKumulatifRatus)->toBe(21000)
            ->and($rekap->berikutnya)->toBe(JabatanFungsional::Lektor)
            ->and($rekap->syaratRatus)->toBe(20000)
            ->and($rekap->selisihRatus)->toBe(0);
    });

    it('tidak menghitung ijazah yang lulus pada tahun TMT', function () {
        jabatanAk(JabatanFungsional::AsistenAhli, '2019-03-01', 150);

        foreach ([2019, 2024] as $tahun) {
            RiwayatPendidikanDosen::create([
                'dosen_id' => $this->dosen->id, 'jenjang' => $tahun === 2019 ? EducationLevel::S2 : EducationLevel::S3,
                'perguruan_tinggi' => 'Kampus', 'negara' => 'Indonesia', 'tahun_lulus' => $tahun,
            ]);
        }

        $this->ak->catatPendidikan($this->dosen);

        expect($this->ak->rekap($this->dosen)->kum(UnsurAngkaKredit::Pendidikan))->toBe(20000);
    });

    it('menyatakan siap bila AK dan bagian penelitian mencukupi', function () {
        jabatanAk(JabatanFungsional::AsistenAhli, '2022-03-01', 150);

        // Butuh 50 lagi; 35 % dari 50 = 17,50 dari penelitian.
        $this->ak->catatBkd(bkdDisahkan(2023, SemesterType::Ganjil, ['pendidikan' => 800, 'penelitian' => 500]));

        $rekap = $this->ak->rekap($this->dosen);

        expect($rekap->penelitianMinimumRatus)->toBe(1750)
            ->and($rekap->memenuhiPenelitian)->toBeTrue()
            ->and($rekap->siap)->toBeTrue();
    });

    it('belum siap bila AK cukup tetapi penelitiannya kurang', function () {
        jabatanAk(JabatanFungsional::AsistenAhli, '2022-03-01', 150);
        $this->ak->catatBkd(bkdDisahkan(2023, SemesterType::Ganjil, ['pendidikan' => 1600, 'penelitian' => 100]));

        $rekap = $this->ak->rekap($this->dosen);

        expect($rekap->selisihRatus)->toBe(0)
            ->and($rekap->memenuhiPenelitian)->toBeFalse()
            ->and($rekap->siap)->toBeFalse();
    });

    it('membaca syarat jenjang dari konfigurasi', function () {
        config(['angka_kredit.syarat.lektor.kumulatif_ratus' => 30000]);
        jabatanAk(JabatanFungsional::AsistenAhli, '2022-03-01', 150);

        expect($this->ak->rekap($this->dosen)->selisihRatus)->toBe(15000);
    });

    it('tidak menunjuk jenjang berikutnya bagi guru besar', function () {
        jabatanAk(JabatanFungsional::GuruBesar, '2020-03-01', 1000);

        $rekap = $this->ak->rekap($this->dosen);

        expect($rekap->berikutnya)->toBeNull()
            ->and($rekap->siap)->toBeFalse();
    });

    it('merekap per dosen dan per jenjang', function () {
        $lain = Dosen::factory()->create(['is_active' => true]);
        jabatanAk(JabatanFungsional::AsistenAhli, '2022-03-01', 150);
        jabatanAk(JabatanFungsional::Lektor, '2021-03-01', 220, $lain);
        $this->ak->catatBkd(bkdDisahkan(2023, SemesterType::Ganjil, ['pendidikan' => 1000], $lain));

        $rekap = $this->ak->rekapSemua();
        $jenjang = collect($this->ak->rekapPerJenjang($rekap))->keyBy(fn ($j) => $j['jabatan']->value);

        expect($rekap)->toHaveCount(2)
            ->and($jenjang['lektor']['jumlah'])->toBe(1)
            ->and($jenjang['lektor']['dupak_ratus'])->toBe(4000)
            ->and($jenjang['asisten_ahli']['diakui_ratus']['pengajaran'])->toBe(0);
    });
});

describe('lembar penyetaraan', function () {
    it('memuat dosen tetap AA/L/LK dengan TMT paling lambat 2023, dengan JF disingkat dan TMT TTTT-BB-HH', function () {
        jabatanAk(JabatanFungsional::Lektor, '2021-04-01', 200);
        $this->ak->catatBkd(bkdDisahkan(2023, SemesterType::Ganjil, [
            'pendidikan' => 1000, 'penelitian' => 500, 'pengabdian' => 100, 'penunjang' => 100,
        ]));

        $baris = $this->ak->lembarPenyetaraan()['baris'];

        expect($baris)->toHaveCount(1)
            ->and($baris[0])->toBe([
                '1234567890123456', 'Siti Rahmawati', 'L', '2021-04-01', 200.0,
                0.0, 40.0, 20.0, 4.0, 4.0, 68.0, 268.0,
            ]);
    });

    it('meninggalkan yang tidak memenuhi syarat', function () {
        $baru = Dosen::factory()->create(['is_active' => true, 'nuptk' => '1', 'status_kepegawaian' => 'tetap']);
        $tidakTetap = Dosen::factory()->create(['is_active' => true, 'nuptk' => '2', 'status_kepegawaian' => 'tidak_tetap']);
        $guruBesar = Dosen::factory()->create(['is_active' => true, 'nuptk' => '3', 'status_kepegawaian' => 'tetap']);

        jabatanAk(JabatanFungsional::Lektor, '2024-04-01', 200, $baru);
        jabatanAk(JabatanFungsional::Lektor, '2020-04-01', 200, $tidakTetap);
        jabatanAk(JabatanFungsional::GuruBesar, '2020-04-01', 900, $guruBesar);

        expect($this->ak->lembarPenyetaraan()['baris'])->toBeEmpty();
    });

    it('melaporkan dosen tanpa NUPTK alih-alih menuliskannya ke lembar', function () {
        $this->dosen->update(['nuptk' => null]);
        jabatanAk(JabatanFungsional::Lektor, '2021-04-01', 200);

        $lembar = $this->ak->lembarPenyetaraan();

        expect($lembar['baris'])->toBeEmpty()
            ->and($lembar['tertahan'])->toHaveCount(1)
            ->and($lembar['tertahan'][0]['alasan'])->toBe('NUPTK kosong');
    });

    it('mengekspor .xlsx dengan kolom tetap, NUPTK dan TMT sebagai teks, AK sebagai angka', function () {
        jabatanAk(JabatanFungsional::AsistenAhli, '2020-01-02', 150.5);
        $this->ak->catatBkd(bkdDisahkan(2023, SemesterType::Ganjil, ['pendidikan' => 1000]));

        $path = $this->ak->eksporPenyetaraan();
        $sel = selXlsx($path);
        @unlink($path);

        expect(array_column($sel[0], 'nilai'))->toBe([
            'NUPTK', 'Nama', 'Jabatan Fungsional', 'TMT Jabatan Fungsional', 'AK Kumulatif Terakhir',
            'Kum A', 'Kum B', 'Kum C', 'Kum D', 'Kum E', 'Total DUPAK', 'AK Penyetaraan',
        ])
            ->and($sel[1])->toBe([
                ['tipe' => 'teks', 'nilai' => '1234567890123456'],
                ['tipe' => 'teks', 'nilai' => 'Siti Rahmawati'],
                ['tipe' => 'teks', 'nilai' => 'AA'],
                ['tipe' => 'teks', 'nilai' => '2020-01-02'],
                ['tipe' => 'angka', 'nilai' => '150.50'],
                ['tipe' => 'angka', 'nilai' => '0.00'],
                ['tipe' => 'angka', 'nilai' => '40.00'],
                ['tipe' => 'angka', 'nilai' => '0.00'],
                ['tipe' => 'angka', 'nilai' => '0.00'],
                ['tipe' => 'angka', 'nilai' => '0.00'],
                ['tipe' => 'angka', 'nilai' => '40.00'],
                ['tipe' => 'angka', 'nilai' => '190.50'],
            ]);
    });
});

describe('layar dan policy', function () {
    it('menampilkan rekap kampus dan buku besar per dosen kepada staf', function () {
        jabatanAk(JabatanFungsional::Lektor, '2021-04-01', 200);

        $this->actingAs($this->staf, 'staff')->get('/admin/angka-kredit')
            ->assertOk()->assertSee('Siti Rahmawati')->assertSee('Rekap per Jenjang');

        $this->actingAs($this->staf, 'staff')->get("/admin/angka-kredit/{$this->dosen->uuid}")
            ->assertOk()->assertSee('Baris Buku Besar');
    });

    it('mengunduh lembar penyetaraan sebagai .xlsx', function () {
        jabatanAk(JabatanFungsional::Lektor, '2021-04-01', 200);

        $this->actingAs($this->staf, 'staff')->get('/admin/angka-kredit/ekspor')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('penyetaraan-ak-'.now()->format('Y-m-d').'.xlsx');
    });

    it('mencatat baris manual lewat formulir, divalidasi FormRequest', function () {
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/angka-kredit/{$this->dosen->uuid}/manual", ['sumber' => 'konversi_manual', 'unsur' => 'penelitian'])
            ->assertSessionHasErrors(['semester_kode', 'sks']);

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/angka-kredit/{$this->dosen->uuid}/manual", [
                'sumber' => 'konversi_manual', 'unsur' => 'penelitian', 'semester_kode' => '20201', 'sks' => '3.5',
            ])
            ->assertRedirect();

        expect(AngkaKreditDosen::first()?->nilai_ratus)->toBe(1400);
    });

    it('membiarkan pimpinan membaca tetapi tidak mencatat atau mengekspor', function () {
        $pimpinan = Staff::factory()->create();
        $pimpinan->assignRole('pimpinan');

        $this->actingAs($pimpinan, 'staff')->get('/admin/angka-kredit')->assertOk();
        $this->actingAs($pimpinan, 'staff')->get('/admin/angka-kredit/ekspor')->assertForbidden();
        $this->actingAs($pimpinan, 'staff')
            ->post("/admin/angka-kredit/{$this->dosen->uuid}/manual", [
                'sumber' => 'konversi_manual', 'unsur' => 'penelitian', 'semester_kode' => '20201', 'sks' => '3',
            ])
            ->assertForbidden();
    });

    it('menyimpan NUPTK dari layar buku besar, dan menolak yang bukan 16 digit', function () {
        $this->actingAs($this->staf, 'staff')
            ->put("/admin/angka-kredit/{$this->dosen->uuid}/nuptk", ['nuptk' => '12345'])
            ->assertSessionHasErrors('nuptk');

        $this->actingAs($this->staf, 'staff')
            ->put("/admin/angka-kredit/{$this->dosen->uuid}/nuptk", ['nuptk' => '9876543210123456'])
            ->assertRedirect();

        expect($this->dosen->refresh()->nuptk)->toBe('9876543210123456');
    });

    it('menyusun ulang seluruh buku besar lewat perintah artisan', function () {
        bkdDisahkan(2024, SemesterType::Ganjil, ['pendidikan' => 1000]);

        $this->artisan('openacademic:susun-angka-kredit')->assertSuccessful();

        expect((int) AngkaKreditDosen::sum('nilai_ratus'))->toBe(4000);
    });

    it('mencatat Kum A saat dosen menyimpan ijazah di portofolio', function () {
        $this->actingAs($this->dosen, 'dosen')->post('/dosen/portofolio/pendidikan', [
            'jenjang' => EducationLevel::S3->value, 'perguruan_tinggi' => 'Universitas Indonesia',
            'negara' => 'Indonesia', 'tahun_lulus' => 2024,
        ])->assertRedirect();

        expect(AngkaKreditDosen::where('dosen_id', $this->dosen->id)->first()?->nilai_ratus)->toBe(20000);
    });

    it('menampilkan buku besar dosen sendiri, dan tidak membuka layar staf baginya', function () {
        jabatanAk(JabatanFungsional::Lektor, '2021-04-01', 200);

        $this->actingAs($this->dosen, 'dosen')->get('/dosen/angka-kredit')
            ->assertOk()->assertSee('Angka Kredit Saya')->assertSee('Lektor Kepala');

        $this->actingAs($this->dosen, 'dosen')->get('/admin/angka-kredit')->assertRedirect();
    });
});
