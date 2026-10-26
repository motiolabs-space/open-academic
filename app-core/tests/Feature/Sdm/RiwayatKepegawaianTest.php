<?php

declare(strict_types=1);

use App\DTOs\Sdm\RiwayatKepegawaianData;
use App\Enums\JenisRiwayat;
use App\Enums\JenisSertifikasi;
use App\Enums\JenisUnitKerja;
use App\Enums\SemesterType;
use App\Enums\StatusRiwayat;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\BahasaDosen;
use App\Models\Sdm\DiklatDosen;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PangkatDosen;
use App\Models\Sdm\PensiunDosen;
use App\Models\Sdm\SertifikasiDosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\TugasBelajarDosen;
use App\Models\Sdm\UnitKerja;
use App\Services\Sdm\BkdService;
use App\Services\Sdm\EksporSister;
use App\Services\Sdm\RiwayatKepegawaianService;
use App\Support\Sdm\FormulirRiwayat;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Riwayat kepegawaian #39: empat belas riwayat, satu alur validasi.
 *
 * Yang diuji terutama apa yang TIDAK terjadi sebelum disetujui — pangkat tidak
 * berpindah, ekspor tidak memuatnya — dan tiga aturan yang memang ada: pangkat
 * berlaku, tugas belajar membebaskan BKD, pensiun menutup akun.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'tanggal_mulai' => now()->subMonth(),
        'tanggal_selesai' => now()->addMonths(4),
    ]);

    $this->dosen = Dosen::factory()->create(['is_active' => true]);
    $this->dosen->assignRole('dosen');

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');

    $this->riwayat = app(RiwayatKepegawaianService::class);
});

/** @param array<string, mixed> $isian */
function isianRiwayat(JenisRiwayat $jenis, array $isian, ?UploadedFile $dokumen = null): RiwayatKepegawaianData
{
    return RiwayatKepegawaianData::fromArray($jenis, $isian, $dokumen);
}

function ajukanPangkat(string $golongan, string $tmt, ?Dosen $dosen = null): PangkatDosen
{
    return test()->riwayat->ajukan($dosen ?? test()->dosen, isianRiwayat(JenisRiwayat::Pangkat, [
        'golongan' => $golongan,
        'tmt' => $tmt,
    ]));
}

function unitRiwayat(string $kode): UnitKerja
{
    return UnitKerja::create(['kode' => $kode, 'nama' => 'Unit '.$kode, 'jenis' => JenisUnitKerja::Struktural]);
}

function unduhanRiwayat(StreamedResponse $respons): string
{
    ob_start();
    $respons->sendContent();

    return (string) ob_get_clean();
}

describe('ajuan dosen', function () {
    it('mencatat ajuan sebagai menunggu validasi, siapa pun yang masuk', function () {
        $baris = $this->riwayat->ajukan($this->dosen, isianRiwayat(JenisRiwayat::Diklat, [
            'nama' => 'Pekerti',
            'jenis' => 'fungsional',
            'tanggal_mulai' => '2024-02-01',
        ]));

        expect($baris)->toBeInstanceOf(DiklatDosen::class)
            ->and($baris->status)->toBe(StatusRiwayat::Diajukan)
            ->and($baris->divalidasi_oleh)->toBeNull();
    });

    it('menyimpan dokumen pendukung di disk privat lewat BerkasService', function () {
        $baris = $this->riwayat->ajukan($this->dosen, isianRiwayat(JenisRiwayat::Diklat, [
            'nama' => 'Pekerti', 'jenis' => 'fungsional', 'tanggal_mulai' => '2024-02-01',
        ], UploadedFile::fake()->create('sertifikat.pdf', 50, 'application/pdf')));

        expect($baris->dokumen_path)->toStartWith('kepegawaian/'.$this->dosen->uuid.'/')
            ->and($baris->dokumen_path)->not->toContain('sertifikat');
        Storage::disk('local')->assertExists($baris->dokumen_path);
    });

    it('menurunkan nama pangkat dari golongan', function () {
        expect(ajukanPangkat('III/c', '2022-04-01')->pangkat)->toBe('Penata');
    });

    it('menolak mutasi, kontrak, dan pensiun dari portal dosen', function (JenisRiwayat $jenis) {
        expect(fn () => $this->riwayat->ajukan($this->dosen, isianRiwayat($jenis, [])))
            ->toThrow(AturanAkademikException::class, 'bagian kepegawaian');
    })->with([JenisRiwayat::Mutasi, JenisRiwayat::Kontrak, JenisRiwayat::Pensiun]);

    it('menolak bahasa yang sudah tercatat, tetapi memulihkan yang pernah dihapus', function () {
        $isian = isianRiwayat(JenisRiwayat::Bahasa, ['bahasa' => 'Inggris', 'kemampuan' => 'mahir']);

        $pertama = $this->riwayat->ajukan($this->dosen, $isian);

        expect(fn () => $this->riwayat->ajukan($this->dosen, $isian))
            ->toThrow(AturanAkademikException::class, 'sudah tercatat');

        // Indeks unik bahasa_dosen ikut menghitung baris terhapus; menambah
        // ulang harus memulihkan baris lama, bukan menabrak indeksnya.
        $this->riwayat->hapus($pertama);
        $kedua = $this->riwayat->ajukan($this->dosen, $isian);

        expect($kedua->id)->toBe($pertama->id)
            ->and($kedua->trashed())->toBeFalse()
            ->and(BahasaDosen::withTrashed()->count())->toBe(1);
    });
});

describe('validasi', function () {
    it('menyetujui ajuan dan mencatat siapa yang memvalidasi', function () {
        $baris = ajukanPangkat('III/a', '2020-04-01');

        $this->riwayat->setujui($baris, $this->staf);

        expect($baris->refresh()->status)->toBe(StatusRiwayat::Disetujui)
            ->and($baris->divalidasi_oleh)->toBe($this->staf->id)
            ->and($baris->divalidasi_at)->not->toBeNull();
    });

    it('tidak menjadikan pangkat berlaku sebelum disetujui', function () {
        ajukanPangkat('III/a', '2020-04-01');

        expect($this->riwayat->pangkatBerlaku($this->dosen))->toBeNull();
    });

    it('menjadikan pangkat ber-TMT terakhir yang berlaku, bukan yang terakhir disetujui', function () {
        /*
         * Dosen yang memasukkan riwayat dua puluh tahun mengajukan SK lama
         * paling akhir. Menyetujuinya tidak boleh menurunkan pangkat siapa pun.
         */
        $baru = ajukanPangkat('III/c', '2024-04-01');
        $lama = ajukanPangkat('III/a', '2016-04-01');

        $this->riwayat->setujui($baru, $this->staf);
        $this->riwayat->setujui($lama, $this->staf);

        expect($this->riwayat->pangkatBerlaku($this->dosen)?->id)->toBe($baru->id)
            ->and($lama->refresh()->berlaku())->toBeFalse();
    });

    it('menolak dengan alasan, dan alasannya wajib', function () {
        $baris = ajukanPangkat('III/a', '2020-04-01');

        expect(fn () => $this->riwayat->tolak($baris, $this->staf, 'x'))
            ->toThrow(AturanAkademikException::class, 'alasan');

        $this->riwayat->tolak($baris, $this->staf, 'SK yang dilampirkan tidak terbaca.');

        expect($baris->refresh()->status)->toBe(StatusRiwayat::Ditolak)
            ->and($baris->alasan_tolak)->toBe('SK yang dilampirkan tidak terbaca.');
    });

    it('tidak memvalidasi dua kali', function () {
        $baris = ajukanPangkat('III/a', '2020-04-01');
        $this->riwayat->setujui($baris, $this->staf);

        expect(fn () => $this->riwayat->setujui($baris, $this->staf))
            ->toThrow(AturanAkademikException::class, 'sudah divalidasi')
            ->and(fn () => $this->riwayat->tolak($baris, $this->staf, 'terlambat sekali'))
            ->toThrow(AturanAkademikException::class, 'sudah divalidasi');
    });

    it('mengantrekan ajuan dari seluruh riwayat, terlama dulu, dan dapat disaring per jenis', function () {
        $this->travel(-2)->days();
        ajukanPangkat('III/a', '2020-04-01');
        $this->travelBack();

        $this->riwayat->ajukan($this->dosen, isianRiwayat(JenisRiwayat::Organisasi, [
            'nama' => 'Asosiasi Dosen', 'tahun_mulai' => 2020,
        ]));

        $antrean = $this->riwayat->antrean();

        expect($antrean)->toHaveCount(2)
            ->and($antrean->first())->toBeInstanceOf(PangkatDosen::class)
            ->and($this->riwayat->antrean(JenisRiwayat::Organisasi))->toHaveCount(1)
            ->and($this->riwayat->jumlahMenunggu())->toBe(2);
    });
});

describe('sunting dan hapus', function () {
    it('mengembalikan ajuan yang ditolak ke antrean saat disunting dosen', function () {
        $baris = ajukanPangkat('III/a', '2020-04-01');
        $this->riwayat->tolak($baris, $this->staf, 'TMT tidak sesuai SK.');

        $this->riwayat->ubah($baris, isianRiwayat(JenisRiwayat::Pangkat, ['golongan' => 'III/a', 'tmt' => '2020-03-01']));

        expect($baris->refresh()->status)->toBe(StatusRiwayat::Diajukan)
            ->and($baris->alasan_tolak)->toBeNull()
            ->and($baris->tmt->toDateString())->toBe('2020-03-01');
    });

    it('menolak suntingan dosen atas isian yang sudah disetujui', function () {
        $baris = ajukanPangkat('III/a', '2020-04-01');
        $this->riwayat->setujui($baris, $this->staf);

        expect(fn () => $this->riwayat->ubah($baris, isianRiwayat(JenisRiwayat::Pangkat, [
            'golongan' => 'IV/a', 'tmt' => '2020-04-01',
        ])))->toThrow(AturanAkademikException::class, 'sudah disetujui');
    });

    it('membiarkan staf mengoreksi isian yang disetujui tanpa mencabut persetujuannya', function () {
        $baris = ajukanPangkat('III/a', '2020-04-01');
        $this->riwayat->setujui($baris, $this->staf);

        $this->riwayat->ubah($baris, isianRiwayat(JenisRiwayat::Pangkat, [
            'golongan' => 'III/b', 'tmt' => '2020-04-01',
        ]), $this->staf);

        expect($baris->refresh()->status)->toBe(StatusRiwayat::Disetujui)
            ->and($baris->golongan)->toBe('III/b')
            ->and($this->riwayat->pangkatBerlaku($this->dosen)?->id)->toBe($baris->id);
    });

    it('menyerahkan status berlaku ke pangkat berikutnya saat pangkat berlaku dihapus', function () {
        $lama = ajukanPangkat('III/a', '2016-04-01');
        $baru = ajukanPangkat('III/b', '2020-04-01');
        $this->riwayat->setujui($lama, $this->staf);
        $this->riwayat->setujui($baru, $this->staf);

        $this->riwayat->hapus($baru->refresh());

        expect($baru->refresh()->trashed())->toBeTrue()
            ->and($this->riwayat->pangkatBerlaku($this->dosen)?->id)->toBe($lama->id);
    });

    it('tidak menghapus atau menyunting mutasi — koreksinya mutasi baru', function () {
        $mutasi = $this->riwayat->catat($this->dosen, $this->staf, isianRiwayat(JenisRiwayat::Mutasi, [
            'jenis' => 'masuk', 'unit_tujuan_id' => unitRiwayat('A')->id, 'tmt' => '2024-01-01',
        ]))->baris;

        expect(fn () => $this->riwayat->hapus($mutasi))->toThrow(AturanAkademikException::class, 'mutasi baru')
            ->and(fn () => $this->riwayat->ubah($mutasi, isianRiwayat(JenisRiwayat::Mutasi, [
                'jenis' => 'keluar', 'tmt' => '2024-01-01',
            ]), $this->staf))->toThrow(AturanAkademikException::class, 'mutasi baru');
    });
});

describe('isian staf', function () {
    it('langsung disetujui, atas nama staf yang mencatat', function () {
        $hasil = $this->riwayat->catat($this->dosen, $this->staf, isianRiwayat(JenisRiwayat::Kontrak, [
            'nomor_kontrak' => 'PKWT/2026/001', 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31',
        ]));

        expect($hasil->baris->status)->toBe(StatusRiwayat::Disetujui)
            ->and($hasil->baris->divalidasi_oleh)->toBe($this->staf->id)
            ->and($hasil->catatan)->toBe([]);
    });

    it('memindahkan unit kerja dosen saat mutasi dicatat', function () {
        $asal = unitRiwayat('A');
        $tujuan = unitRiwayat('B');
        $this->dosen->update(['unit_kerja_id' => $asal->id]);

        $mutasi = $this->riwayat->catat($this->dosen, $this->staf, isianRiwayat(JenisRiwayat::Mutasi, [
            'jenis' => 'pindah', 'unit_tujuan_id' => $tujuan->id, 'tmt' => '2026-01-01',
        ]))->baris;

        expect($this->dosen->refresh()->unit_kerja_id)->toBe($tujuan->id)
            ->and($mutasi->unit_asal_id)->toBe($asal->id)
            ->and($mutasi->status)->toBe(StatusRiwayat::Disetujui);
    });

    it('naikPangkat dan catatMutasi tetap menulis baris yang disetujui', function () {
        // Basis datanya kini bawaan "diajukan"; jalur lama staf tidak boleh
        // diam-diam menghasilkan pangkat yang menunggu validasi.
        $pangkat = $this->riwayat->naikPangkat($this->dosen, ['pangkat' => 'Penata', 'golongan' => 'III/c', 'tmt' => '2024-04-01']);

        expect($pangkat->refresh()->status)->toBe(StatusRiwayat::Disetujui);
    });
});

describe('pensiun', function () {
    it('menutup akun saat pensiun yang TMT-nya sudah tiba dicatat', function () {
        $hasil = $this->riwayat->catat($this->dosen, $this->staf, isianRiwayat(JenisRiwayat::Pensiun, [
            'jenis' => 'batas_usia', 'tmt' => now()->subDay()->toDateString(),
        ]));

        expect($this->dosen->refresh()->is_active)->toBeFalse()
            ->and($hasil->catatan[0])->toContain('ditutup');
    });

    it('menunggu TMT untuk pensiun yang dicatat lebih dulu', function () {
        $this->riwayat->catat($this->dosen, $this->staf, isianRiwayat(JenisRiwayat::Pensiun, [
            'jenis' => 'batas_usia', 'tmt' => now()->addMonth()->toDateString(),
        ]));

        expect($this->dosen->refresh()->is_active)->toBeTrue();

        $this->travel(32)->days();
        $hasil = $this->riwayat->tutupAkunPensiun();

        expect($hasil['ditutup'])->toHaveCount(1)
            ->and($this->dosen->refresh()->is_active)->toBeFalse();
    });

    it('menahan akun pensiunan yang masih mengampu kelas semester berjalan, dan menyebut sebabnya', function () {
        $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id]);
        $kelas->dosen()->attach($this->dosen->id, ['peran' => 'pengampu']);

        $hasil = $this->riwayat->catat($this->dosen, $this->staf, isianRiwayat(JenisRiwayat::Pensiun, [
            'jenis' => 'batas_usia', 'tmt' => now()->subDay()->toDateString(),
        ]));

        expect($this->dosen->refresh()->is_active)->toBeTrue()
            ->and($hasil->catatan[0])->toContain('belum dapat ditutup')
            ->and($hasil->catatan[0])->toContain('mengampu 1 kelas')
            // Faktanya tetap tercatat; yang tertahan hanya akunnya.
            ->and(PensiunDosen::disetujui()->count())->toBe(1);
    });

    it('menjalankan penutupan harian lewat perintah artisan', function () {
        PensiunDosen::create([
            'dosen_id' => $this->dosen->id, 'jenis' => 'batas_usia', 'tmt' => now()->subDay(),
            'status' => StatusRiwayat::Disetujui,
        ]);

        $this->artisan('openacademic:tutup-akun-pensiun')->assertSuccessful();

        expect($this->dosen->refresh()->is_active)->toBeFalse();
    });

    it('tidak menutup akun karena pensiun yang belum disetujui', function () {
        PensiunDosen::create(['dosen_id' => $this->dosen->id, 'jenis' => 'batas_usia', 'tmt' => now()->subDay()]);

        expect($this->riwayat->tutupAkunPensiun()['ditutup'])->toBeEmpty()
            ->and($this->dosen->refresh()->is_active)->toBeTrue();
    });
});

describe('tugas belajar dan BKD', function () {
    beforeEach(function () {
        SertifikasiDosen::create([
            'dosen_id' => $this->dosen->id, 'jenis' => JenisSertifikasi::Serdos,
            'nama' => 'Serdos', 'tanggal' => '2020-01-01',
        ]);
    });

    function tugasBelajar(string $jenis, StatusRiwayat $status, ?string $selesai = null): TugasBelajarDosen
    {
        return TugasBelajarDosen::create([
            'dosen_id' => test()->dosen->id, 'jenis' => $jenis, 'jenjang' => '40',
            'perguruan_tinggi' => 'Universitas Indonesia',
            'tanggal_mulai' => now()->subYear(), 'tanggal_selesai' => $selesai, 'status' => $status,
        ]);
    }

    it('membebaskan kewajiban BKD selama tugas belajar yang disetujui', function () {
        tugasBelajar('tugas_belajar', StatusRiwayat::Disetujui);

        expect(app(BkdService::class)->wajibMelapor($this->term)->pluck('id'))->not->toContain($this->dosen->id)
            ->and($this->dosen->wajibBkd($this->term))->toBeFalse()
            ->and(app(BkdService::class)->belumMelapor($this->term)->pluck('id'))->not->toContain($this->dosen->id);
    });

    it('tetap mewajibkan BKD bagi izin belajar', function () {
        tugasBelajar('izin_belajar', StatusRiwayat::Disetujui);

        expect(app(BkdService::class)->wajibMelapor($this->term)->pluck('id'))->toContain($this->dosen->id);
    });

    it('tidak membebaskan berdasarkan ajuan yang belum disetujui', function () {
        tugasBelajar('tugas_belajar', StatusRiwayat::Diajukan);

        expect($this->dosen->wajibBkd($this->term))->toBeTrue();
    });

    it('mewajibkan BKD lagi untuk semester sesudah tugas belajar selesai', function () {
        tugasBelajar('tugas_belajar', StatusRiwayat::Disetujui, now()->subMonths(2)->toDateString());

        expect($this->dosen->wajibBkd($this->term))->toBeTrue()
            ->and($this->dosen->sedangTugasBelajar($this->term))->toBeFalse();
    });
});

describe('formulir', function () {
    it('menyimpan angka kredit inpassing dalam perseratus, bukan float', function () {
        $atribut = FormulirRiwayat::atribut(JenisRiwayat::Inpassing, [
            'golongan' => 'III/b', 'angka_kredit' => '150.25', 'nomor_sk' => 'X', 'tmt' => '2020-01-01',
        ]);

        expect($atribut['angka_kredit_ratus'])->toBe(15025)
            ->and($atribut)->not->toHaveKey('angka_kredit')
            ->and($atribut['pangkat'])->toBe('Penata Muda Tingkat I');
    });

    it('memiliki definisi bidang dan ringkasan untuk keempat belas riwayat', function () {
        foreach (JenisRiwayat::cases() as $jenis) {
            expect(FormulirRiwayat::bidang($jenis))->not->toBeEmpty()
                ->and(FormulirRiwayat::aturan($jenis))->not->toBeEmpty()
                ->and(__('kepegawaian.jenis.'.$jenis->value))->not->toStartWith('kepegawaian.');
        }
    });

    it('mengelompokkan berkas satu dosen per jenis riwayat', function () {
        ajukanPangkat('III/a', '2020-04-01');

        $berkas = $this->riwayat->riwayat($this->dosen);

        expect(array_keys($berkas))->toBe(array_map(fn (JenisRiwayat $j) => $j->value, JenisRiwayat::cases()))
            ->and($berkas['pangkat'])->toHaveCount(1)
            ->and($berkas['diklat'])->toHaveCount(0);
    });
});

describe('ekspor SISTER', function () {
    it('hanya memuat isian yang disetujui', function () {
        $this->dosen->update(['nidn' => '0011223344']);

        $disetujui = $this->riwayat->ajukan($this->dosen, isianRiwayat(JenisRiwayat::Organisasi, [
            'nama' => 'Ikatan Ahli Informatika', 'tahun_mulai' => 2019,
        ]));
        $this->riwayat->setujui($disetujui, $this->staf);

        $this->riwayat->ajukan($this->dosen, isianRiwayat(JenisRiwayat::Organisasi, [
            'nama' => 'Perkumpulan Belum Diperiksa', 'tahun_mulai' => 2021,
        ]));

        $isi = unduhanRiwayat(app(EksporSister::class)->csv('organisasi'));

        expect($isi)->toContain('Ikatan Ahli Informatika')
            ->and($isi)->not->toContain('Perkumpulan Belum Diperiksa')
            ->and(app(EksporSister::class)->katalog()['organisasi']['baris'])->toBe(1);
    });
});

describe('portal dosen', function () {
    it('menampilkan berkas kepegawaian sendiri', function () {
        ajukanPangkat('III/a', '2020-04-01');

        $this->actingAs($this->dosen, 'dosen')
            ->get('/dosen/kepegawaian')
            ->assertOk()
            ->assertSee('Penata Muda')
            ->assertSee('Menunggu Validasi');
    });

    it('mengajukan isian lewat formulir, divalidasi FormRequest', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/kepegawaian/diklat', ['nama' => '', 'jenis' => 'fungsional'])
            ->assertSessionHasErrors(['nama', 'tanggal_mulai']);

        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/kepegawaian/diklat', [
                'nama' => 'Pekerti', 'jenis' => 'fungsional', 'tanggal_mulai' => '2024-02-01',
            ])
            ->assertRedirect();

        expect(DiklatDosen::where('dosen_id', $this->dosen->id)->first()?->status)->toBe(StatusRiwayat::Diajukan);
    });

    it('menolak pengajuan jenis yang hanya diisi staf', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/kepegawaian/pensiun', ['jenis' => 'batas_usia', 'tmt' => '2030-01-01'])
            ->assertForbidden();
    });

    it('tidak membiarkan dosen menyunting atau menghapus isian rekannya', function () {
        $rekan = Dosen::factory()->create();
        $milikRekan = ajukanPangkat('III/a', '2020-04-01', $rekan);

        $this->actingAs($this->dosen, 'dosen')
            ->put("/dosen/kepegawaian/pangkat/{$milikRekan->uuid}", ['golongan' => 'IV/e', 'tmt' => '2020-04-01'])
            ->assertForbidden();

        $this->actingAs($this->dosen, 'dosen')
            ->delete("/dosen/kepegawaian/pangkat/{$milikRekan->uuid}")
            ->assertForbidden();

        expect($milikRekan->refresh()->golongan)->toBe('III/a');
    });

    it('tidak membiarkan dosen menyunting isian yang sudah disetujui', function () {
        $baris = ajukanPangkat('III/a', '2020-04-01');
        $this->riwayat->setujui($baris, $this->staf);

        $this->actingAs($this->dosen, 'dosen')
            ->put("/dosen/kepegawaian/pangkat/{$baris->uuid}", ['golongan' => 'IV/e', 'tmt' => '2020-04-01'])
            ->assertForbidden();
    });

    it('mengunduh dokumen sendiri, tetapi tidak dokumen rekan', function () {
        $milik = $this->riwayat->ajukan($this->dosen, isianRiwayat(JenisRiwayat::Diklat, [
            'nama' => 'Pekerti', 'jenis' => 'fungsional', 'tanggal_mulai' => '2024-02-01',
        ], UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')));

        $rekan = Dosen::factory()->create();
        $rekan->assignRole('dosen');

        $this->actingAs($this->dosen, 'dosen')->get("/berkas/kepegawaian/diklat/{$milik->uuid}")->assertOk();
        $this->actingAs($rekan, 'dosen')->get("/berkas/kepegawaian/diklat/{$milik->uuid}")->assertForbidden();
    });

    it('menjawab 404 untuk jenis yang tidak dikenal', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/kepegawaian/entah', [])
            ->assertNotFound();
    });
});

describe('portal staf', function () {
    it('menampilkan antrean validasi dan berkas per dosen', function () {
        ajukanPangkat('III/a', '2020-04-01');

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/kepegawaian/validasi')
            ->assertOk()
            ->assertSee($this->dosen->nama)
            ->assertSee('Penata Muda');

        $this->actingAs($this->staf, 'staff')
            ->get("/admin/dosen/{$this->dosen->uuid}/kepegawaian")
            ->assertOk()
            ->assertSee('Riwayat Kepegawaian');
    });

    it('menyetujui dan menolak lewat layar', function () {
        $satu = ajukanPangkat('III/a', '2020-04-01');
        $dua = ajukanPangkat('III/b', '2022-04-01');

        $this->actingAs($this->staf, 'staff')->post("/admin/kepegawaian/pangkat/{$satu->uuid}/setujui")->assertRedirect();
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kepegawaian/pangkat/{$dua->uuid}/tolak", ['alasan' => ''])
            ->assertSessionHasErrors('alasan');
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kepegawaian/pangkat/{$dua->uuid}/tolak", ['alasan' => 'SK tidak terbaca.'])
            ->assertRedirect();

        expect($satu->refresh()->status)->toBe(StatusRiwayat::Disetujui)
            ->and($dua->refresh()->status)->toBe(StatusRiwayat::Ditolak);
    });

    it('mencatat isian staf yang langsung disetujui', function () {
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/dosen/{$this->dosen->uuid}/kepegawaian/kontrak", [
                'nomor_kontrak' => 'PKWT/1', 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31',
            ])
            ->assertRedirect();

        expect($this->dosen->kontrak()->first()?->status)->toBe(StatusRiwayat::Disetujui);
    });

    it('membiarkan pimpinan membaca tetapi tidak memvalidasi', function () {
        $pimpinan = Staff::factory()->create();
        $pimpinan->assignRole('pimpinan');
        $baris = ajukanPangkat('III/a', '2020-04-01');

        $this->actingAs($pimpinan, 'staff')->get('/admin/kepegawaian/validasi')->assertOk();
        $this->actingAs($pimpinan, 'staff')->post("/admin/kepegawaian/pangkat/{$baris->uuid}/setujui")->assertForbidden();
        $this->actingAs($pimpinan, 'staff')
            ->post("/admin/dosen/{$this->dosen->uuid}/kepegawaian/kontrak", [
                'nomor_kontrak' => 'PKWT/1', 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31',
            ])
            ->assertForbidden();
    });

    it('menolak staf tanpa izin dosen.view', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get('/admin/kepegawaian/validasi')->assertForbidden();
    });

    it('tidak membuka layar staf bagi mahasiswa', function () {
        $mahasiswa = Mahasiswa::factory()->create();

        $this->actingAs($mahasiswa, 'mahasiswa')->get('/admin/kepegawaian/validasi')->assertRedirect();
        $this->actingAs($mahasiswa, 'mahasiswa')->get('/dosen/kepegawaian')->assertRedirect();
    });
});
