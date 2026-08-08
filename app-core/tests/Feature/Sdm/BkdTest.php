<?php

declare(strict_types=1);

use App\Enums\JabatanFungsional;
use App\Enums\JenisSertifikasi;
use App\Enums\KesimpulanBkd;
use App\Enums\PeranPembimbing;
use App\Enums\PeranPenguji;
use App\Enums\SemesterType;
use App\Enums\StatusBkd;
use App\Enums\StatusUjian;
use App\Enums\TugasAkhirStatus;
use App\Enums\UnsurBkd;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\JabatanFungsionalDosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\SertifikasiDosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\Pembimbing;
use App\Models\TugasAkhir\Penguji;
use App\Models\TugasAkhir\TugasAkhir;
use App\Models\TugasAkhir\Ujian;
use App\Services\Sdm\BebanKerjaService;
use App\Services\Sdm\BkdService;
use App\Services\Sdm\PortofolioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'tanggal_mulai' => now()->subMonths(3),
        'tanggal_selesai' => now()->addMonths(2),
    ]);

    $this->prodi = Prodi::factory()->create();
    $this->dosen = Dosen::factory()->create(['is_active' => true, 'nidn' => '0012345678']);

    $this->bkd = app(BkdService::class);
    $this->beban = app(BebanKerjaService::class);
});

/** Sebuah kelas yang diampu dosen uji, dengan porsi SKS eksplisit. */
function kelasDiampu(int $sks = 3, ?float $porsi = null, ?Dosen $dosen = null): KelasKuliah
{
    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->term->id,
        'prodi_id' => test()->prodi->id,
        'mata_kuliah_id' => MataKuliah::factory()->create([
            'prodi_id' => test()->prodi->id,
            'sks' => $sks,
        ])->id,
        'sks' => $sks,
    ]);

    $kelas->dosen()->attach(($dosen ?? test()->dosen)->id, [
        'peran' => 'pengampu',
        'porsi_sks' => $porsi,
    ]);

    return $kelas;
}

/** Tugas akhir yang berjalan sepanjang semester uji, dibimbing dosen uji. */
function bimbingan(PeranPembimbing $peran = PeranPembimbing::Utama): TugasAkhir
{
    $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => test()->prodi->id]);

    $ta = TugasAkhir::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'mahasiswa_aktif_id' => $mahasiswa->id,
        'judul' => 'Judul uji '.$mahasiswa->id,
        'status' => TugasAkhirStatus::Dibimbing,
        'tanggal_pengajuan' => test()->term->tanggal_mulai,
    ]);

    Pembimbing::create([
        'tugas_akhir_id' => $ta->id,
        'dosen_id' => test()->dosen->id,
        'peran' => $peran,
        'ditetapkan_pada' => test()->term->tanggal_mulai,
    ]);

    return $ta;
}

/**
 * Token konsumen Bridge. Diberi akhiran berkas sendiri karena fungsi Pest
 * bersifat global dan tokenBridge() sudah dipakai berkas tes EDOM.
 *
 * @param array<int, string> $scopes
 */
function tokenBridgeSdm(array $scopes): string
{
    $consumer = BridgeConsumer::create([
        'nama' => 'Open Campus',
        'slug' => 'open-campus-'.Str::random(6),
        'scopes' => $scopes,
        'is_active' => true,
    ]);

    return $consumer->createToken('uji', $scopes)->plainTextToken;
}

function serdos(?Dosen $dosen = null): SertifikasiDosen
{
    return SertifikasiDosen::create([
        'dosen_id' => ($dosen ?? test()->dosen)->id,
        'jenis' => JenisSertifikasi::Serdos,
        'nama' => 'Sertifikat Pendidik',
        'tanggal' => now()->subYears(3),
    ]);
}

describe('unsur pendidikan dihitung, bukan diketik', function () {
    it('menarik kelas yang diampu beserta SKS-nya', function () {
        kelasDiampu(sks: 3);
        kelasDiampu(sks: 2);

        $ringkas = $this->beban->ringkas($this->beban->hitung($this->dosen, $this->term));

        expect($ringkas[UnsurBkd::Pendidikan->value])->toBe(500);
    });

    it('membagi kelas yang diampu berdua alih-alih menghitungnya dua kali', function () {
        /*
         * Tanpa pembagian ini, satu kelas 4 SKS yang diampu dua orang terhitung
         * 8 SKS di tingkat kampus — angka yang tidak pernah benar dan tidak
         * pernah kentara pada satu laporan pun.
         */
        $kelas = kelasDiampu(sks: 4);
        $kelas->dosen()->attach(Dosen::factory()->create()->id, ['peran' => 'pengampu']);

        $ringkas = $this->beban->ringkas($this->beban->hitung($this->dosen, $this->term));

        expect($ringkas[UnsurBkd::Pendidikan->value])->toBe(200);
    });

    it('memakai porsi SKS yang sudah ditetapkan kampus bila ada', function () {
        // Seseorang sudah memutuskan pembagiannya; menghitung ulang di atasnya
        // akan menghapus keputusan itu.
        kelasDiampu(sks: 4, porsi: 1.5);

        $ringkas = $this->beban->ringkas($this->beban->hitung($this->dosen, $this->term));

        expect($ringkas[UnsurBkd::Pendidikan->value])->toBe(150);
    });

    it('membedakan pembimbing utama dan pendamping', function () {
        bimbingan(PeranPembimbing::Utama);
        bimbingan(PeranPembimbing::Pendamping);

        $ringkas = $this->beban->ringkas($this->beban->hitung($this->dosen, $this->term));

        // 1,00 + 0,50 sesuai rubrik bawaan config/bkd.php
        expect($ringkas[UnsurBkd::Pendidikan->value])->toBe(150);
    });

    it('menghitung bimbingan yang dimulai semester lalu dan masih berjalan', function () {
        // Bimbingan terjadi pada semester ini, bukan pada semester judulnya
        // disetujui.
        $ta = bimbingan();
        $ta->update(['tanggal_pengajuan' => $this->term->tanggal_mulai->copy()->subYear()]);

        expect($this->beban->ringkas($this->beban->hitung($this->dosen, $this->term))[UnsurBkd::Pendidikan->value])->toBe(100);
    });

    it('tidak menghitung sidang yang dibatalkan', function () {
        $ta = bimbingan();

        foreach ([StatusUjian::Selesai, StatusUjian::Dibatalkan] as $status) {
            $ujian = Ujian::create([
                'tugas_akhir_id' => $ta->id,
                'jenis' => 'sidang',
                'tanggal' => $this->term->tanggal_mulai->copy()->addMonth(),
                'jam_mulai' => '09:00',
                'jam_selesai' => '10:00',
                'status' => $status,
            ]);

            Penguji::create([
                'tugas_akhir_ujian_id' => $ujian->id,
                'dosen_id' => $this->dosen->id,
                'peran' => PeranPenguji::Ketua,
            ]);
        }

        // 1,00 bimbingan + 0,25 satu pengujian. Sidang yang batal bukan
        // pekerjaan yang dikerjakan.
        expect($this->beban->ringkas($this->beban->hitung($this->dosen, $this->term))[UnsurBkd::Pendidikan->value])->toBe(125);
    });

    it('menghitung perwalian per rombongan, bukan per kepala', function () {
        Mahasiswa::factory()->count(13)->create([
            'prodi_id' => $this->prodi->id,
            'dosen_wali_id' => $this->dosen->id,
        ]);

        // 13 mahasiswa, 12 per rombongan → 2 rombongan.
        expect($this->beban->ringkas($this->beban->hitung($this->dosen, $this->term))[UnsurBkd::Pendidikan->value])->toBe(200);
    });

    it('mengabaikan kegiatan berunsur pendidikan yang dilaporkan sendiri', function () {
        // Kelasnya sudah ditarik otomatis; baris laporan sendiri berunsur
        // pendidikan adalah kelas yang sama dihitung dua kali.
        kelasDiampu(sks: 3);

        PenugasanDosen::create([
            'dosen_id' => $this->dosen->id,
            'tahun_akademik_id' => $this->term->id,
            'jenis' => 'penelitian',
            'unsur' => UnsurBkd::Pendidikan,
            'judul' => 'Mengajar kelas yang sama, diketik ulang',
            'tanggal_mulai' => $this->term->tanggal_mulai,
            'sks_ekuivalen' => 3,
        ]);

        expect($this->beban->ringkas($this->beban->hitung($this->dosen, $this->term))[UnsurBkd::Pendidikan->value])->toBe(300);
    });
});

describe('batas beban', function () {
    it('menyebut unsur mana yang kurang, bukan sekadar gagal', function () {
        kelasDiampu(sks: 3);

        $pesan = $this->beban->pelanggaranBatas(
            $this->beban->ringkas($this->beban->hitung($this->dosen, $this->term)),
        );

        expect(implode(' ', $pesan))
            ->toContain('di bawah batas minimum')
            ->toContain('Penelitian');
    });

    it('melaporkan kelebihan beban alih-alih menolaknya', function () {
        // Dosen yang benar-benar memikul dua puluh SKS punya masalah beban yang
        // layak terlihat; menolak laporannya hanya menyembunyikannya.
        foreach (range(1, 8) as $i) {
            kelasDiampu(sks: 3);
        }

        $pesan = $this->beban->pelanggaranBatas(
            $this->beban->ringkas($this->beban->hitung($this->dosen, $this->term)),
        );

        expect(implode(' ', $pesan))->toContain('melampaui batas maksimum');
    });
});

describe('pengajuan membekukan laporan', function () {
    it('tidak berubah ketika kelas disunting setelah diajukan', function () {
        /*
         * Aturan yang menentukan seluruh bentuk modul ini.
         *
         * Laporan dinilai, dan penilaiannya menentukan tunjangan. Bila barisnya
         * membaca data hidup, kelas yang dialihkan bulan depan diam-diam menulis
         * ulang penilaian yang sudah ditandatangani — dan tanda tangan itu
         * melekat pada angka yang tak pernah dilihat siapa pun.
         */
        $kelas = kelasDiampu(sks: 3);

        $laporan = $this->bkd->ajukan($this->bkd->laporan($this->dosen, $this->term));

        expect($laporan->sks_total)->toBe(300);

        $kelas->dosen()->detach($this->dosen->id);

        expect($laporan->fresh()->sks_total)->toBe(300)
            ->and($laporan->fresh()->baris()->count())->toBe(1);
    });

    it('menolak pengajuan kedua', function () {
        kelasDiampu();
        $laporan = $this->bkd->ajukan($this->bkd->laporan($this->dosen, $this->term));

        expect(fn () => $this->bkd->ajukan($laporan->fresh()))
            ->toThrow(AturanAkademikException::class, 'tidak dapat diajukan ulang');
    });

    it('menolak laporan tanpa satu pun kegiatan', function () {
        expect(fn () => $this->bkd->ajukan($this->bkd->laporan($this->dosen, $this->term)))
            ->toThrow(AturanAkademikException::class, 'Belum ada satu pun kegiatan');
    });

    it('hanya menyimpan satu laporan per dosen per semester', function () {
        $pertama = $this->bkd->laporan($this->dosen, $this->term);
        $kedua = $this->bkd->laporan($this->dosen->fresh(), $this->term);

        expect($kedua->id)->toBe($pertama->id);
    });
});

describe('penilaian', function () {
    beforeEach(function () {
        kelasDiampu();

        $this->laporan = $this->bkd->ajukan($this->bkd->laporan($this->dosen, $this->term));
        $this->asesor = Dosen::factory()->create(['is_active' => true]);
        $this->bkd->tetapkanAsesor($this->laporan, $this->asesor);
        $this->laporan->refresh();
    });

    it('menolak dosen menjadi asesor laporannya sendiri', function () {
        // Bukan konflik kepentingan yang perlu dikelola, melainkan ketiadaan
        // penilaian.
        expect(fn () => $this->bkd->tetapkanAsesor($this->laporan, $this->dosen))
            ->toThrow(AturanAkademikException::class, 'laporannya sendiri');
    });

    it('menolak dua asesor yang sama', function () {
        $lain = Dosen::factory()->create();

        expect(fn () => $this->bkd->tetapkanAsesor($this->laporan, $lain, $lain))
            ->toThrow(AturanAkademikException::class, 'orang yang berbeda');
    });

    it('menolak penilaian dari dosen yang bukan asesornya', function () {
        $orangLain = Dosen::factory()->create();

        expect(fn () => $this->bkd->nilai($this->laporan, $orangLain, KesimpulanBkd::Memenuhi))
            ->toThrow(AturanAkademikException::class, 'bukan asesor');
    });

    it('mewajibkan catatan bila kesimpulannya bukan memenuhi', function () {
        // "Tidak memenuhi" tanpa alasan mengirim orang ke loket untuk bertanya,
        // dan jawabannya justru satu-satunya hal yang harus dihasilkan asesor.
        expect(fn () => $this->bkd->nilai($this->laporan, $this->asesor, KesimpulanBkd::TidakMemenuhi))
            ->toThrow(AturanAkademikException::class, 'wajib disertai catatan');
    });

    it('mencatat kesimpulan dan memberi tahu dosennya', function () {
        $this->bkd->nilai($this->laporan, $this->asesor, KesimpulanBkd::Memenuhi, 'Lengkap.');

        expect($this->laporan->fresh()->status)->toBe(StatusBkd::Dinilai)
            ->and($this->dosen->notifications()->count())->toBe(1);
    });

    it('membuka kembali laporan yang dikembalikan', function () {
        $this->bkd->kembalikan($this->laporan, $this->asesor, 'Bukti penelitian belum dilampirkan.');

        expect($this->laporan->fresh()->status->dapatDisunting())->toBeTrue();
    });

    it('menghapus penilaian lama saat laporan diajukan ulang', function () {
        // Penilaian lama menggambarkan cuplikan yang lama; membiarkannya
        // menempel akan menjadikannya penilaian atas laporan yang tidak pernah
        // dinilai.
        $this->bkd->kembalikan($this->laporan, $this->asesor, 'Perbaiki.');

        $diajukanUlang = $this->bkd->ajukan($this->laporan->fresh());

        expect($diajukanUlang->kesimpulan)->toBeNull()
            ->and($diajukanUlang->catatan_asesor)->toBeNull()
            ->and($diajukanUlang->status)->toBe(StatusBkd::Diajukan);
    });
});

describe('pengesahan', function () {
    it('menolak pengesahan sebelum dinilai asesor', function () {
        // Mengesahkan laporan yang belum dinilai menjadikan asesor sekadar
        // hiasan, dan tanda tangannya menjamin penilaian yang tak pernah ada.
        kelasDiampu();
        $laporan = $this->bkd->ajukan($this->bkd->laporan($this->dosen, $this->term));

        expect(fn () => $this->bkd->sahkan($laporan, Staff::factory()->create()))
            ->toThrow(AturanAkademikException::class, 'setelah dinilai asesor');
    });
});

describe('kewajiban melapor', function () {
    it('hanya menyasar pemegang sertifikat pendidik', function () {
        // BKD adalah syarat tunjangan sertifikasi; menuntutnya dari semua dosen
        // membebankan administrasi yang tidak diminta regulasi.
        $bersertifikat = $this->dosen;
        serdos($bersertifikat);

        Dosen::factory()->create(['is_active' => true]);

        expect($this->bkd->belumMelapor($this->term)->pluck('id')->all())
            ->toBe([$bersertifikat->id]);
    });

    it('mengikuti pengaturan kampus saat diminta menyasar semua dosen', function () {
        config(['bkd.wajib' => 'semua']);
        Dosen::factory()->create(['is_active' => true]);

        expect($this->bkd->belumMelapor($this->term))->toHaveCount(2);
    });
});

describe('layar', function () {
    it('membuka lembar kerja yang sudah terisi tanpa diketik', function () {
        $kelas = kelasDiampu(sks: 3);
        serdos();

        $this->actingAs($this->dosen, 'dosen')
            ->get('/dosen/bkd')
            ->assertOk()
            ->assertSee($kelas->mataKuliah->nama)
            ->assertSee('Rekaman sistem');
    });

    it('tetap menampilkan rincian beku setelah kelasnya dialihkan', function () {
        /*
         * Sisi layar dari aturan pembekuan. Service sudah menyimpan cuplikannya;
         * yang diuji di sini adalah layar tidak diam-diam kembali membaca data
         * hidup — yang akan memperlihatkan rincian berbeda dari total yang
         * tercetak di sebelahnya.
         */
        $kelas = kelasDiampu(sks: 3);
        serdos();

        $this->bkd->ajukan($this->bkd->laporan($this->dosen, $this->term));

        $kelas->dosen()->detach($this->dosen->id);

        $this->actingAs($this->dosen, 'dosen')
            ->get('/dosen/bkd')
            ->assertOk()
            ->assertSee($kelas->mataKuliah->nama);
    });

    it('mengatakan terus terang kepada dosen yang belum wajib melapor', function () {
        kelasDiampu();

        $this->actingAs($this->dosen, 'dosen')
            ->get('/dosen/bkd')
            ->assertOk()
            ->assertSee('belum tercatat memegang Sertifikat Pendidik');
    });

    it('tidak pernah menyimpan kegiatan yang dilaporkan sendiri sebagai terverifikasi', function () {
        // Baris ini menjadi baris BKD sekaligus bukti IKU. Yang datang sudah
        // terverifikasi adalah indikator yang bertumpu pada klaim tak diperiksa.
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', [
                'jenis' => 'penelitian',
                'unsur' => UnsurBkd::Penelitian->value,
                'judul' => 'Riset uji',
                'tanggal_mulai' => $this->term->tanggal_mulai->toDateString(),
                'is_verified' => 1,
            ])
            ->assertRedirect();

        expect(PenugasanDosen::where('dosen_id', $this->dosen->id)->first()->is_verified)
            ->toBeFalse();
    });

    it('menolak kegiatan berunsur pendidikan dari borang dosen', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->post('/dosen/portofolio/kegiatan', [
                'jenis' => 'penelitian',
                'unsur' => UnsurBkd::Pendidikan->value,
                'judul' => 'Mengajar, diketik ulang',
                'tanggal_mulai' => $this->term->tanggal_mulai->toDateString(),
            ])
            ->assertSessionHasErrors('unsur');
    });

    it('hanya melepas lembar BKD kepada pemiliknya dan asesornya', function () {
        kelasDiampu();
        $laporan = $this->bkd->ajukan($this->bkd->laporan($this->dosen, $this->term));

        $asesor = Dosen::factory()->create(['is_active' => true]);
        $this->bkd->tetapkanAsesor($laporan, $asesor);

        $this->actingAs($this->dosen, 'dosen')->get('/dosen/bkd/'.$laporan->uuid.'/unduh')->assertOk();
        $this->actingAs($asesor, 'dosen')->get('/dosen/bkd/'.$laporan->uuid.'/unduh')->assertOk();

        $orangLain = Dosen::factory()->create(['is_active' => true]);
        $this->actingAs($orangLain, 'dosen')->get('/dosen/bkd/'.$laporan->uuid.'/unduh')->assertForbidden();
    });
});

describe('Campus Bridge', function () {
    it('menerbitkan cacahan tanpa menerapkan ambang apa pun', function () {
        kelasDiampu(sks: 3);
        serdos();
        $this->bkd->ajukan($this->bkd->laporan($this->dosen, $this->term));

        $respons = $this->getJson('/api/bridge/v1/lecturer-workload?semester='.$this->term->kode, [
            'Authorization' => 'Bearer '.tokenBridgeSdm(array_keys(config('bridge.scopes'))),
        ])->assertOk();

        $respons->assertJsonPath('data.pelaporan_bkd.total_laporan', 1)
            ->assertJsonPath('data.beban_kerja.rerata_sks.total', 3)

            // Rentangnya disertakan sebagai pengaturan kampus, bukan sebagai
            // lulus/tidak — konsumen yang menerapkannya.
            ->assertJsonPath('data.batas_kampus.minimum_sks', 12)
            ->assertJsonPath('data.beban_kerja.sebaran_total.di_bawah_minimum', 1);
    });

    it('mengembalikan berkas satu dosen ketika NIDN disebut', function () {
        serdos();
        app(PortofolioService::class)
            ->catatJabatan($this->dosen, JabatanFungsional::Lektor, now()->toDateString());

        $this->getJson('/api/bridge/v1/lecturer-workload?semester='.$this->term->kode.'&nidn='.$this->dosen->nidn, [
            'Authorization' => 'Bearer '.tokenBridgeSdm(array_keys(config('bridge.scopes'))),
        ])
            ->assertOk()
            ->assertJsonPath('data.dosen.nidn', $this->dosen->nidn)
            ->assertJsonPath('data.dosen.jabatan_fungsional', JabatanFungsional::Lektor->value)
            ->assertJsonPath('data.dosen.wajib_bkd', true);
    });

    it('tidak pernah membawa NIK maupun alamat rumah', function () {
        // Bentuk yang sama dipakai Campus Bridge, ekspor CSV, dan berkas JSON
        // yang dikirim lewat surel. Payload yang aman di satu kanal dan tidak di
        // kanal lain akan bocor lewat kanal yang paling ceroboh.
        $this->dosen->update(['nik' => '3201010101900001', 'alamat' => 'Jalan Rahasia 1']);
        serdos();

        $isi = $this->getJson('/api/bridge/v1/lecturer-workload?semester='.$this->term->kode.'&nidn='.$this->dosen->nidn, [
            'Authorization' => 'Bearer '.tokenBridgeSdm(array_keys(config('bridge.scopes'))),
        ])->assertOk()->getContent();

        expect($isi)->not->toContain('3201010101900001')
            ->and($isi)->not->toContain('Jalan Rahasia');
    });

    it('menolak token tanpa scope beban kerja', function () {
        $this->getJson('/api/bridge/v1/lecturer-workload?semester='.$this->term->kode, [
            'Authorization' => 'Bearer '.tokenBridgeSdm(['lecturers.read']),
        ])->assertForbidden();
    });
});

describe('ekspor', function () {
    beforeEach(function () {
        $this->staff = Staff::factory()->create()->assignRole('super-admin');
        kelasDiampu(sks: 3);
        serdos();
    });

    it('menstreamkan rekap BKD sebagai CSV', function () {
        $this->bkd->ajukan($this->bkd->laporan($this->dosen, $this->term));

        $respons = $this->actingAs($this->staff, 'staff')
            ->get('/admin/bkd/ekspor/rekap?semester='.$this->term->kode)
            ->assertOk();

        $isi = $respons->streamedContent();

        expect($isi)->toContain($this->dosen->nidn)
            ->and($isi)->toContain('SKS Total')

            // BOM UTF-8: Excel di Windows berbahasa Indonesia membaca CSV tanpa
            // BOM sebagai mojibake, dan yang membaca berkas ini justru orang
            // yang harus memeriksa nama bergelar.
            ->and(substr($isi, 0, 3))->toBe("\xEF\xBB\xBF");
    });

    it('menstreamkan kegiatan dosen sebagai CSV', function () {
        PenugasanDosen::create([
            'dosen_id' => $this->dosen->id,
            'tahun_akademik_id' => $this->term->id,
            'jenis' => 'penelitian',
            'unsur' => UnsurBkd::Penelitian,
            'judul' => 'Riset yang harus muncul di CSV',
            'tanggal_mulai' => $this->term->tanggal_mulai,
        ]);

        $isi = $this->actingAs($this->staff, 'staff')
            ->get('/admin/bkd/ekspor/kegiatan?semester='.$this->term->kode)
            ->assertOk()
            ->streamedContent();

        expect($isi)->toContain('Riset yang harus muncul di CSV');
    });

    it('menurunkan portofolio satu dosen sebagai JSON bervesi', function () {
        // Bentuk yang akan dikonsumsi skrip integrasi nanti. Dapat diunduh
        // sekarang justru supaya pemetaannya ditulis atas data sungguhan.
        $this->actingAs($this->staff, 'staff')
            ->get('/admin/bkd/ekspor/portofolio/'.$this->dosen->uuid.'?semester='.$this->term->kode)
            ->assertOk()
            ->assertJsonPath('versi', '1')
            ->assertJsonPath('dosen.nidn', $this->dosen->nidn)
            ->assertJsonPath('semester.semester', $this->term->kode);
    });
});

describe('portofolio', function () {
    it('hanya menyisakan satu jabatan berlaku', function () {
        $portofolio = app(PortofolioService::class);

        $portofolio->catatJabatan($this->dosen, JabatanFungsional::AsistenAhli, now()->subYears(5)->toDateString());
        $portofolio->catatJabatan($this->dosen, JabatanFungsional::Lektor, now()->subYear()->toDateString());

        expect(JabatanFungsionalDosen::where('dosen_id', $this->dosen->id)->count())->toBe(2)
            ->and(JabatanFungsionalDosen::aktif()->where('dosen_id', $this->dosen->id)->count())->toBe(1);
    });

    it('menyelaraskan kolom datar pada tabel dosen', function () {
        // Kolom datar itu singgahan dari baris riwayat. Bila keduanya berbeda,
        // yang tercetak di blok tanda tangan adalah yang salah.
        app(PortofolioService::class)
            ->catatJabatan($this->dosen, JabatanFungsional::LektorKepala, now()->toDateString());

        expect($this->dosen->fresh()->jabatan_fungsional)
            ->toBe(JabatanFungsional::LektorKepala->label());
    });

    it('menandai angka kredit yang tidak mencukupi tanpa menolak barisnya', function () {
        // Kampus yang memasukkan riwayat dua puluh tahun punya SK dengan skema
        // angka kredit berbeda; menolaknya berarti riwayat itu tak pernah masuk.
        $jabatan = app(PortofolioService::class)->catatJabatan(
            $this->dosen,
            JabatanFungsional::LektorKepala,
            now()->toDateString(),
            angkaKredit: 100,
        );

        expect($jabatan->exists)->toBeTrue()
            ->and($jabatan->angkaKreditMencukupi())->toBeFalse();
    });
});
