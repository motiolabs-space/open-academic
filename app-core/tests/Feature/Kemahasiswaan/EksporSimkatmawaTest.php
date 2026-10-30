<?php

declare(strict_types=1);

use App\DTOs\Kemahasiswaan\DataSimkatmawa;
use App\DTOs\Kemahasiswaan\FilterSimkatmawa;
use App\Enums\BentukKepesertaan;
use App\Enums\BidangLomba;
use App\Enums\JenisPoin;
use App\Enums\JenisRekognisi;
use App\Enums\KategoriSimkatmawa;
use App\Enums\ModaPelaksanaan;
use App\Enums\PeringkatPrestasi;
use App\Enums\SemesterType;
use App\Enums\TingkatSimkatmawa;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Services\Feeder\Mappers\PrestasiMapper;
use App\Services\Kemahasiswaan\EksporSimkatmawa;
use App\Services\Kemahasiswaan\PoinKemahasiswaanService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
 * The SIMKATMAWA report (ADR-037 §1). What matters is what a row MEANS: which
 * table it lands in, what keeps it out of the file, and that the file holds
 * exactly the reportable rows — written through Csv, so a title typed by a
 * student cannot become a formula.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    config(['bkd.ekspor.pemisah_csv' => ',']);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => '55201', 'nama' => 'Informatika']);
    $this->ekspor = app(EksporSimkatmawa::class);
    $this->poin = app(PoinKemahasiswaanService::class);
    $this->filter = new FilterSimkatmawa(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->dosen = Dosen::factory()->create(['nama' => 'Rina Pembimbing', 'nidn' => '0011223344', 'gelar_depan' => null, 'gelar_belakang' => null]);
});

function simkatKategori(?KategoriSimkatmawa $kategori, string $tingkat = 'nasional', JenisPoin $jenis = JenisPoin::Prestasi): PoinKategori
{
    return PoinKategori::create([
        'kode' => 'SK-'.uniqid(),
        'nama' => 'Kategori uji',
        'jenis' => $jenis,
        'tingkat' => $tingkat,
        'poin' => 30,
        'simkatmawa_kategori' => $kategori,
        'wajib_bukti' => false,
    ]);
}

/** A verified competition row with every field SIMKATMAWA needs. */
function simkatPrestasi(PoinKategori $kategori, array $atribut = [], ?Mahasiswa $mahasiswa = null): PoinMahasiswa
{
    $mahasiswa ??= Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id, 'nim' => '2201'.random_int(100000, 999999), 'feeder_id' => 'feeder-'.uniqid(),
    ]);

    return PoinMahasiswa::create(array_merge([
        'mahasiswa_id' => $mahasiswa->id,
        'poin_kategori_id' => $kategori->id,
        'tanggal' => '2026-05-14',
        'judul' => 'Kompetisi Perangkat Lunak',
        'poin' => $kategori->poin,
        'jenis' => $kategori->jenis,
        'is_verified' => true,
        'peringkat' => PeringkatPrestasi::Juara1,
        'bentuk_kepesertaan' => BentukKepesertaan::Kelompok,
        'moda_pelaksanaan' => ModaPelaksanaan::Luring,
        'bidang_lomba' => BidangLomba::RisetStem,
        'penyelenggara' => 'Asosiasi Uji',
        'jumlah_pt_peserta' => 12,
        'tautan_sertifikat' => 'https://berkas.demo.test/s.pdf',
        'tautan_bukti' => 'https://berita.demo.test/juara',
        'tautan_surat_tugas' => 'https://berkas.demo.test/st.pdf',
        'dosen_pembimbing_id' => test()->dosen->id,
    ], $atribut));
}

describe('tabel', function () {
    it('memilah prestasi terverifikasi ke tabelnya, dan melewatkan klaim, pelanggaran, dan kategori yang tidak dilaporkan', function () {
        $kompetisi = simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi));
        $rekognisi = simkatPrestasi(simkatKategori(KategoriSimkatmawa::Rekognisi), ['jenis_rekognisi' => JenisRekognisi::Karya]);
        $sertifikasi = simkatPrestasi(simkatKategori(KategoriSimkatmawa::Sertifikasi), ['nomor_sertifikat' => 'LSP-1']);

        simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi), ['is_verified' => false]);
        simkatPrestasi(simkatKategori(null));
        simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi, 'berat', JenisPoin::Pelanggaran));

        $tabel = $this->ekspor->tabel($this->filter);

        expect($tabel['kompetisi']->map(fn ($b) => $b->poin->id)->all())->toBe([$kompetisi->id])
            ->and($tabel['rekognisi']->map(fn ($b) => $b->poin->id)->all())->toBe([$rekognisi->id])
            ->and($tabel['sertifikasi']->map(fn ($b) => $b->poin->id)->all())->toBe([$sertifikasi->id])
            ->and($this->ekspor->menungguVerifikasi($this->filter))->toBe(1);
    });

    it('memuat hari pertama dan terakhir periode, dan menyaring per prodi', function () {
        $kategori = simkatKategori(KategoriSimkatmawa::Kompetisi);
        $awal = simkatPrestasi($kategori, ['tanggal' => '2026-01-01']);
        $akhir = simkatPrestasi($kategori, ['tanggal' => '2026-12-31']);
        simkatPrestasi($kategori, ['tanggal' => '2025-12-31']);

        $lain = Prodi::factory()->create(['kode' => '57401']);
        simkatPrestasi($kategori, [], Mahasiswa::factory()->create(['prodi_id' => $lain->id]));

        $semua = $this->ekspor->tabel($this->filter)['kompetisi'];
        $satuProdi = $this->ekspor->tabel(new FilterSimkatmawa($this->filter->dari, $this->filter->sampai, $this->prodi->id))['kompetisi'];

        expect($semua)->toHaveCount(3)
            ->and($satuProdi->map(fn ($b) => $b->poin->id)->all())->toBe([$awal->id, $akhir->id]);
    });
});

describe('pemeriksaan pra-ekspor', function () {
    it('menyatakan baris lengkap siap, tanpa galat maupun peringatan', function () {
        $baris = $this->ekspor->periksa(simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi)));

        expect($baris->siap())->toBeTrue()
            ->and($baris->galat)->toBe([])
            ->and($baris->peringatan)->toBe([]);
    });

    it('melaporkan seluruh kekurangan kompetisi sekaligus, bukan satu per satu', function () {
        $baris = $this->ekspor->periksa(simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi, 'lokal'), [
            'peringkat' => null, 'bentuk_kepesertaan' => null, 'moda_pelaksanaan' => null, 'bidang_lomba' => null,
            'penyelenggara' => null, 'tautan_sertifikat' => null, 'jumlah_pt_peserta' => null,
        ]));

        expect($baris->siap())->toBeFalse()
            ->and($baris->galat)->toBe([
                'Tingkat belum diisi.',
                'Penyelenggara belum diisi.',
                'Tautan sertifikat belum diisi.',
                'Bidang lomba wajib untuk prestasi kompetisi.',
                'Pelaksanaan wajib untuk prestasi kompetisi.',
                'Kepesertaan wajib untuk prestasi kompetisi.',
                'Capaian wajib untuk prestasi kompetisi.',
            ]);
    });

    it('membaca tingkat nasional/internasional dari katalog, tetapi tidak menebak "lokal" atau melaporkan kegiatan internal', function () {
        $nasional = $this->ekspor->periksa(simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi, 'nasional')));
        $lokal = $this->ekspor->periksa(simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi, 'lokal')));
        $lokalDiisi = $this->ekspor->periksa(simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi, 'lokal'), ['tingkat' => TingkatSimkatmawa::Provinsi]));
        $internal = $this->ekspor->periksa(simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi, 'internal')));

        expect($nasional->nilai['tingkat'])->toBe('Nasional')
            ->and($lokal->galat)->toContain('Tingkat belum diisi.')
            ->and($lokalDiisi->nilai['tingkat'])->toBe('Provinsi')
            ->and($lokalDiisi->siap())->toBeTrue()
            ->and($internal->galat[0])->toStartWith('Kegiatan internal kampus tidak dilaporkan');
    });

    it('menimbang kompetisi internasional dengan jumlah negara, bukan jumlah PT, dan menandai judul pameran', function () {
        $kategori = simkatKategori(KategoriSimkatmawa::Kompetisi, 'internasional');

        $tanpaNegara = $this->ekspor->periksa(simkatPrestasi($kategori, ['jumlah_negara_peserta' => null]));
        $pameran = $this->ekspor->periksa(simkatPrestasi($kategori, [
            'jumlah_negara_peserta' => 9, 'jumlah_pt_peserta' => null, 'judul' => 'International Invention Expo',
        ]));

        expect($tanpaNegara->galat)->toBe(['Jumlah negara peserta belum diisi.'])
            ->and($pameran->siap())->toBeTrue()
            ->and($pameran->peringatan)->toHaveCount(1)
            ->and($pameran->peringatan[0])->toContain('dihitung sebagai rekognisi');
    });

    it('menolak tingkat yang tidak dikenal kategorinya dan tautan yang bukan alamat web', function () {
        $rekognisi = $this->ekspor->periksa(simkatPrestasi(simkatKategori(KategoriSimkatmawa::Rekognisi), [
            'tingkat' => TingkatSimkatmawa::KabupatenKota, 'jenis_rekognisi' => null,
        ]));
        $sertifikasi = $this->ekspor->periksa(simkatPrestasi(simkatKategori(KategoriSimkatmawa::Sertifikasi), [
            'tautan_sertifikat' => 'javascript:alert(1)',
        ]));

        expect($rekognisi->galat)->toBe([
            'Tingkat Kabupaten/Kota tidak diterima untuk Rekognisi / karya.',
            'Jenis rekognisi belum dipilih.',
        ])->and($sertifikasi->galat)->toBe([
            'Tautan Sertifikat bukan alamat web yang sah.',
            'Nomor sertifikat belum diisi.',
        ]);
    });

    it('memberi peringatan, bukan galat, untuk bukti yang menurunkan skor dan mahasiswa yang belum di PDDikti', function () {
        $m = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'feeder_id' => null]);
        $baris = $this->ekspor->periksa(simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi), [
            'tautan_bukti' => null, 'tautan_surat_tugas' => null, 'dosen_pembimbing_id' => null,
        ], $m));

        expect($baris->siap())->toBeTrue()
            ->and($baris->peringatan)->toBe([
                'Tanpa tautan bukti kegiatan, skor dapat diturunkan.',
                'Mahasiswa belum tersinkron ke PDDikti.',
                'Tanpa surat tugas, pendamping tidak tercatat.',
                'Belum ada dosen pembimbing.',
            ]);
    });

    it('tidak memeriksa prestasi yang kategori katalognya tidak dilaporkan', function () {
        expect($this->ekspor->periksa(simkatPrestasi(simkatKategori(null))))->toBeNull();
    });
});

describe('tabel pembimbing', function () {
    it('memuat satu baris per prestasi berpembimbing, dan menahan pembimbing tanpa nomor atau prestasi yang belum lengkap', function () {
        $kategori = simkatKategori(KategoriSimkatmawa::Kompetisi);
        $lengkap = simkatPrestasi($kategori);
        $belumLengkap = simkatPrestasi($kategori, ['penyelenggara' => null]);
        $tanpaNomor = simkatPrestasi($kategori, [
            'dosen_pembimbing_id' => Dosen::factory()->create(['nidn' => null, 'nuptk' => null])->id,
        ]);
        simkatPrestasi($kategori, ['dosen_pembimbing_id' => null]);

        $pembimbing = $this->ekspor->tabel($this->filter)['pembimbing']->keyBy(fn ($b) => $b->poin->id);

        expect($pembimbing->keys()->all())->toBe([$lengkap->id, $belumLengkap->id, $tanpaNomor->id])
            ->and($pembimbing[$lengkap->id]->siap())->toBeTrue()
            ->and($pembimbing[$lengkap->id]->nilai['pembimbing_nidn'])->toBe('0011223344')
            ->and($pembimbing[$belumLengkap->id]->galat)->toBe(['Prestasi yang dibimbing sendiri belum lengkap.'])
            ->and($pembimbing[$tanpaNomor->id]->galat)->toBe(['Dosen pembimbing belum memiliki NIDN maupun NUPTK.']);
    });
});

describe('unduhan', function () {
    it('menulis kolom kompetisi persis, hanya baris yang siap, dan menetralkan rumus', function () {
        $kategori = simkatKategori(KategoriSimkatmawa::Kompetisi);
        $m = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'nim' => '220155', 'nama' => 'Ayu Lestari', 'feeder_id' => 'f-1']);
        simkatPrestasi($kategori, ['judul' => '=HYPERLINK("https://jahat.test")', 'tanggal_selesai' => '2026-05-16'], $m);
        simkatPrestasi($kategori, ['penyelenggara' => null]);

        $isi = $this->actingAs($this->baak, 'staff')
            ->get(route('admin.simkatmawa.unduh', ['dari' => '2026-01-01', 'sampai' => '2026-12-31', 'kategori' => 'kompetisi', 'format' => 'csv']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        expect(explode("\n", trim(ltrim($isi, "\xEF\xBB\xBF"))))->toBe([
            'NIM,"Nama Mahasiswa","Kode Prodi","Program Studi","Nama Kegiatan / Karya",Bidang,Tingkat,Pelaksanaan,Kepesertaan,Capaian,"Penyelenggara / Pemberi","Tanggal Mulai","Tanggal Selesai","Jumlah PT Peserta","Jumlah Negara Peserta","Tautan Sertifikat","Tautan Bukti Kegiatan","Tautan Surat Tugas","NIDN Pembimbing","Nama Pembimbing"',
            '220155,"Ayu Lestari",55201,Informatika,"\'=HYPERLINK(""https://jahat.test"")","Riset & inovasi — sains dan teknologi",Nasional,"Luring / hibrida",Kelompok,"Juara I","Asosiasi Uji",2026-05-14,2026-05-16,12,,https://berkas.demo.test/s.pdf,https://berita.demo.test/juara,https://berkas.demo.test/st.pdf,0011223344,"Rina Pembimbing"',
        ]);
    });

    it('menulis XLSX dengan sel teks apa adanya', function () {
        simkatPrestasi(simkatKategori(KategoriSimkatmawa::Sertifikasi), ['nomor_sertifikat' => '0012-LSP', 'judul' => 'Junior Web Developer']);

        $respons = $this->actingAs($this->baak, 'staff')
            ->get(route('admin.simkatmawa.unduh', ['dari' => '2026-01-01', 'sampai' => '2026-12-31', 'kategori' => 'sertifikasi', 'format' => 'xlsx']))
            ->assertOk();

        $zip = new ZipArchive;
        $zip->open($respons->baseResponse->getFile()->getPathname());
        $lembar = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        expect($lembar)->toContain('Nomor Sertifikat')
            ->and($lembar)->toContain('>0012-LSP<')
            ->and($lembar)->toContain('>Junior Web Developer<');
    });
});

describe('melengkapi data', function () {
    it('menyimpan data SIMKATMAWA tanpa menyentuh poin dan verifikasi, dan mencatatnya di log', function () {
        $poin = simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi), [
            'penyelenggara' => null, 'tautan_sertifikat' => null, 'peringkat' => null, 'dosen_pembimbing_id' => null,
        ]);

        $this->actingAs($this->baak, 'staff')
            // The whole form, as the screen posts it: an empty field clears.
            ->put(route('admin.poin-kemahasiswaan.simkatmawa.simpan', $poin), [
                'tingkat' => 'provinsi',
                'peringkat' => 'juara_2',
                'bentuk_kepesertaan' => 'individu',
                'moda_pelaksanaan' => 'luring',
                'bidang_lomba' => 'seni_budaya',
                'jenis_rekognisi' => '',
                'penyelenggara' => 'Dinas Pendidikan Provinsi',
                'tanggal_selesai' => '',
                'jumlah_pt_peserta' => 9,
                'jumlah_negara_peserta' => '',
                'nomor_sertifikat' => '',
                'tautan_sertifikat' => 'https://berkas.demo.test/baru.pdf',
                'tautan_bukti' => 'https://berita.demo.test/baru',
                'tautan_surat_tugas' => '',
                'dosen_pembimbing' => $this->dosen->uuid,
            ])
            ->assertRedirect(route('admin.poin-kemahasiswaan.simkatmawa', $poin))
            ->assertSessionHasNoErrors();

        $poin->refresh();

        expect($poin->tingkat)->toBe(TingkatSimkatmawa::Provinsi)
            ->and($poin->peringkat)->toBe(PeringkatPrestasi::Juara2)
            ->and($poin->dosen_pembimbing_id)->toBe($this->dosen->id)
            ->and($poin->tautan_surat_tugas)->toBeNull()
            ->and($poin->poin)->toBe(30)
            ->and($poin->is_verified)->toBeTrue()
            ->and($this->ekspor->periksa($poin)->siap())->toBeTrue()
            ->and(LogAktivitas::query()->forSubject($poin)->where('event', 'updated')->exists())->toBeTrue();
    });

    it('menolak tautan yang bukan http(s)', function () {
        $poin = simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi));

        $this->actingAs($this->baak, 'staff')
            ->put(route('admin.poin-kemahasiswaan.simkatmawa.simpan', $poin), ['tautan_bukti' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('tautan_bukti');
    });

    it('tidak melengkapi catatan pelanggaran, lewat layar maupun service', function () {
        $pelanggaran = simkatPrestasi(simkatKategori(null, 'berat', JenisPoin::Pelanggaran));

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.poin-kemahasiswaan.simkatmawa', $pelanggaran))
            ->assertForbidden();

        expect(fn () => $this->poin->lengkapiSimkatmawa($pelanggaran, new DataSimkatmawa(penyelenggara: 'X')))
            ->toThrow(AturanAkademikException::class);
    });

    it('menetapkan tabel SIMKATMAWA sebuah kategori katalog, dan menolaknya untuk pelanggaran', function () {
        $prestasi = simkatKategori(null);
        $pelanggaran = simkatKategori(null, 'berat', JenisPoin::Pelanggaran);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.poin-kemahasiswaan.kategori.simkatmawa', $prestasi), ['simkatmawa_kategori' => 'rekognisi'])
            ->assertSessionHas('sukses');

        expect($prestasi->fresh()->simkatmawa_kategori)->toBe(KategoriSimkatmawa::Rekognisi)
            ->and($this->poin->tetapkanKategoriSimkatmawa($prestasi->fresh(), null)->simkatmawa_kategori)->toBeNull()
            ->and(fn () => $this->poin->tetapkanKategoriSimkatmawa($pelanggaran, KategoriSimkatmawa::Kompetisi))
            ->toThrow(AturanAkademikException::class);
    });
});

describe('akses', function () {
    it('membuka layar laporan dan layar lengkapi untuk staf kemahasiswaan, dengan temuan tertulis', function () {
        $poin = simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi), ['penyelenggara' => null]);

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.simkatmawa', ['dari' => '2026-01-01', 'sampai' => '2026-12-31']))
            ->assertOk()
            ->assertSee('Penyelenggara belum diisi.')
            ->assertSee('1 belum lengkap');

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.poin-kemahasiswaan.simkatmawa', $poin))
            ->assertOk()
            ->assertSee('Penyelenggara belum diisi.');
    });

    it('tidak menjadikan tautan non-http sebagai href di layar laporan', function () {
        simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi), ['tautan_bukti' => 'javascript:alert(1)']);

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.simkatmawa', ['dari' => '2026-01-01', 'sampai' => '2026-12-31']))
            ->assertOk()
            ->assertDontSee('href="javascript:', false)
            ->assertSee('Tautan Bukti Kegiatan bukan alamat web yang sah.');
    });

    it('menolak staf tanpa izin mengelola mahasiswa, termasuk unduhannya', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');
        $poin = simkatPrestasi(simkatKategori(KategoriSimkatmawa::Kompetisi));

        $this->actingAs($keuangan, 'staff')->get(route('admin.simkatmawa'))->assertForbidden();
        $this->actingAs($keuangan, 'staff')->get(route('admin.simkatmawa.unduh', ['kategori' => 'kompetisi']))->assertForbidden();
        $this->actingAs($keuangan, 'staff')->get(route('admin.poin-kemahasiswaan.simkatmawa', $poin))->assertForbidden();
    });
});

it('mengirim penyelenggara dari kolomnya sendiri ke Feeder, dengan keterangan sebagai cadangan catatan lama', function () {
    $kategori = simkatKategori(KategoriSimkatmawa::Kompetisi);
    $mapper = app(PrestasiMapper::class);

    $baru = simkatPrestasi($kategori, ['penyelenggara' => 'Asosiasi Baru', 'keterangan' => 'catatan bebas']);
    $lama = simkatPrestasi($kategori, ['penyelenggara' => null, 'keterangan' => 'Panitia Lama']);

    expect($mapper->payload($baru->load(['mahasiswa', 'kategori']))['penyelenggara'])->toBe('Asosiasi Baru')
        ->and($mapper->payload($lama->load(['mahasiswa', 'kategori']))['penyelenggara'])->toBe('Panitia Lama');
});
