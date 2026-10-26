<?php

declare(strict_types=1);

use App\DTOs\Kemahasiswaan\KegiatanLapanganData;
use App\DTOs\Kemahasiswaan\KelompokKegiatanData;
use App\DTOs\Kemahasiswaan\LogbookData;
use App\DTOs\Kemahasiswaan\LokasiKegiatanData;
use App\Enums\FeederSyncStatus;
use App\Enums\Gender;
use App\Enums\GradeLetter;
use App\Enums\JenisLogbook;
use App\Enums\JenisLokasiKegiatan;
use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Enums\StudentActivityType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\NilaiKomponen;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Models\Kemahasiswaan\KegiatanLapanganKelompok;
use App\Models\Kemahasiswaan\KegiatanLapanganLogbook;
use App\Models\Kemahasiswaan\KegiatanLapanganLokasi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Services\Akademik\PenilaianService;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Kemahasiswaan\KegiatanLapanganService;
use App\Services\Kemahasiswaan\LogbookKegiatanService;
use App\Services\Kemahasiswaan\PembentukKelompok;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * KKN and PKL as managed field programmes (#37).
 *
 * The thread running through these tests: a participant is an activity row
 * like an MBKM internship, and what PDDIKTI receives — the activity, its
 * supervisor, the credit conversion — is the same chain. The tests pin what
 * that row ends up holding, not merely that "a group was made".
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodiA = Prodi::factory()->create(['nama' => 'Informatika', 'kode_pddikti' => 'prodi-a']);
    $this->prodiB = Prodi::factory()->create(['nama' => 'Agribisnis', 'kode_pddikti' => 'prodi-b']);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->layanan = app(KegiatanLapanganService::class);
    $this->logbook = app(LogbookKegiatanService::class);
});

function kknKegiatan(array $atribut = []): KegiatanLapangan
{
    return app(KegiatanLapanganService::class)->simpan(KegiatanLapanganData::fromArray(array_merge([
        'tahun_akademik_id' => test()->term->id,
        'jenis' => StudentActivityType::Kkn->value,
        'nama' => 'KKN Reguler 2026',
        'tanggal_mulai' => test()->term->tanggal_mulai->copy()->addWeek()->toDateString(),
        'tanggal_selesai' => test()->term->tanggal_mulai->copy()->addWeeks(7)->toDateString(),
        'nomor_sk' => '123/UN/KKN/2026',
        'tanggal_sk' => test()->term->tanggal_mulai->toDateString(),
        'ukuran_kelompok' => 10,
        'bobot_mitra' => 40,
    ], $atribut)));
}

function kknMahasiswa(?Prodi $prodi = null, Gender $jk = Gender::LakiLaki, int $sks = 110, ?string $nim = null): Mahasiswa
{
    $m = Mahasiswa::factory()->create(array_filter([
        'prodi_id' => ($prodi ?? test()->prodiA)->id,
        'jenis_kelamin' => $jk,
        'status' => StudentStatus::Aktif,
        'nim' => $nim,
    ]));

    $m->assignRole('mahasiswa');

    StatusMahasiswa::create([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 7,
        'status' => StudentStatus::Aktif,
        'sks_semester' => 20,
        'sks_kumulatif' => $sks,
        'ips' => 3.2,
        'ipk' => 3.2,
    ]);

    return $m;
}

/**
 * Signs in as exactly one person.
 *
 * Routes shared by several guards pick the first signed-in guard, so a test
 * that switches actors must forget the previous one or keep acting as them.
 */
function kknSebagai(object $pengguna, string $guard): TestCase
{
    auth()->forgetGuards();

    return test()->actingAs($pengguna, $guard);
}

function kknLokasi(KegiatanLapangan $k, string $nama = 'Sukamaju', int $kuota = 1): KegiatanLapanganLokasi
{
    return app(KegiatanLapanganService::class)->simpanLokasi($k, LokasiKegiatanData::fromArray([
        'jenis' => JenisLokasiKegiatan::Desa->value,
        'nama' => 'Desa '.$nama,
        'kecamatan' => 'Cibodas',
        'kabupaten' => 'Kabupaten Bandung Barat',
        'provinsi' => 'Jawa Barat',
        'kuota_kelompok' => $kuota,
    ]));
}

/** A group with a field supervisor, holding the given students. */
function kknKelompokBerisi(KegiatanLapangan $k, array $mahasiswa, ?Dosen $dpl = null, ?KegiatanLapanganLokasi $lokasi = null): KegiatanLapanganKelompok
{
    $layanan = app(KegiatanLapanganService::class);

    foreach ($mahasiswa as $m) {
        $layanan->daftarkan($k, $m);
    }

    $kelompok = $layanan->bentukKelompokOtomatis($k)->first();

    return $layanan->aturKelompok($kelompok, new KelompokKegiatanData(
        lokasiId: $lokasi?->id ?? $kelompok->lokasi_id,
        dosenPembimbingId: $dpl?->id,
        pembimbingMitraNama: 'Bapak Kepala Desa',
    ));
}

function kknKelas(?Prodi $prodi = null, int $sks = 4): KelasKuliah
{
    $prodi ??= test()->prodiA;
    $mk = MataKuliah::factory()->create(['prodi_id' => $prodi->id, 'sks' => $sks, 'nama' => 'Kuliah Kerja Nyata']);

    return KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $prodi->id,
        'sks' => $sks,
        'kode' => 'K',
    ]);
}

function kknPeserta(int $jumlah, Prodi $prodi, Gender $jk, string $awalan): array
{
    $hasil = [];

    for ($i = 1; $i <= $jumlah; $i++) {
        $hasil[] = kknMahasiswa($prodi, $jk, nim: $awalan.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
    }

    return $hasil;
}

describe('periode kegiatan', function () {
    it('mengambil aturan kelompok dan bobot dari config bila tidak diberikan', function () {
        config(['kegiatan_lapangan.kelompok.ukuran' => 7, 'kegiatan_lapangan.bobot_mitra' => 30, 'kegiatan_lapangan.kelompok.campur_jenis_kelamin' => false]);

        $k = $this->layanan->simpan(KegiatanLapanganData::fromArray([
            'tahun_akademik_id' => $this->term->id,
            'jenis' => 'pkl',
            'nama' => 'PKL Gelombang 1',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-11-30',
        ]));

        expect($k->ukuran_kelompok)->toBe(7)
            ->and($k->bobot_mitra)->toBe(30)
            ->and($k->campur_prodi)->toBeTrue()
            ->and($k->campur_jenis_kelamin)->toBeFalse()
            ->and($k->jenis)->toBe(StudentActivityType::Pkl);
    });

    it('menolak jenis aktivitas yang bukan kegiatan lapangan', function () {
        expect(fn () => kknKegiatan(['jenis' => StudentActivityType::Wirausaha->value]))
            ->toThrow(AturanAkademikException::class, 'tidak dapat dikelola');
    });

    it('menolak memindahkan kegiatan berpeserta ke semester lain, dan meneruskan perubahan nama ke aktivitas', function () {
        $k = kknKegiatan();
        $aktivitas = $this->layanan->daftarkan($k, kknMahasiswa());
        $lain = TahunAkademik::factory()->term(2026, SemesterType::Genap)->create();

        $data = fn (array $ubah) => KegiatanLapanganData::fromArray(array_merge([
            'tahun_akademik_id' => $this->term->id,
            'jenis' => 'kkn',
            'nama' => 'KKN Reguler 2026',
            'tanggal_mulai' => $k->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $k->tanggal_selesai->toDateString(),
        ], $ubah));

        expect(fn () => $this->layanan->simpan($data(['tahun_akademik_id' => $lain->id]), $k))
            ->toThrow(AturanAkademikException::class, 'sudah berpeserta');

        $this->layanan->simpan($data(['nama' => 'KKN Tematik Desa Digital']), $k);

        expect($aktivitas->fresh()->judul)->toBe('KKN Tematik Desa Digital');
    });
});

describe('peserta', function () {
    it('mendaftarkan peserta sebagai aktivitas yang belum terverifikasi, pada semester dan tanggal kegiatan', function () {
        $k = kknKegiatan();
        $m = kknMahasiswa();

        $a = $this->layanan->daftarkan($k, $m);

        expect($a->kegiatan_lapangan_id)->toBe($k->id)
            ->and($a->jenis)->toBe(StudentActivityType::Kkn)
            ->and($a->tahun_akademik_id)->toBe($this->term->id)
            ->and($a->tanggal_mulai->toDateString())->toBe($k->tanggal_mulai->toDateString())
            ->and($a->is_verified)->toBeFalse()
            ->and($a->judul)->toBe('KKN Reguler 2026');
    });

    it('menolak mahasiswa tidak aktif, SKS kurang, dan pendaftaran ganda', function () {
        $k = kknKegiatan(['min_sks' => 100]);

        expect(fn () => $this->layanan->daftarkan($k, kknMahasiswa(sks: 90)))
            ->toThrow(AturanAkademikException::class, 'minimal 100 SKS');

        $cuti = kknMahasiswa();
        $cuti->update(['status' => StudentStatus::Cuti]);

        expect(fn () => $this->layanan->daftarkan($k, $cuti))
            ->toThrow(AturanAkademikException::class, 'berstatus aktif');

        $m = kknMahasiswa();
        $this->layanan->daftarkan($k, $m);

        expect(fn () => $this->layanan->daftarkan($k, $m))
            ->toThrow(AturanAkademikException::class, 'sudah terdaftar');
    });

    it('menolak pendaftaran mandiri saat pendaftaran ditutup', function () {
        $k = kknKegiatan(['pendaftaran_dibuka' => false]);

        expect(fn () => $this->layanan->daftarkan($k, kknMahasiswa(), mandiri: true))
            ->toThrow(AturanAkademikException::class, 'ditutup');
    });

    it('mendaftarkan banyak NIM sekaligus dan melaporkan setiap penolakan', function () {
        $k = kknKegiatan(['min_sks' => 100]);
        $a = kknMahasiswa(nim: '2201001');
        kknMahasiswa(nim: '2201002', sks: 50);

        $hasil = $this->layanan->daftarkanBanyak($k, ['2201001', '2201002', '9999999', '2201001']);

        expect($hasil['terdaftar'])->toBe(1)
            ->and(array_map('strval', array_keys($hasil['ditolak'])))->toBe(['2201002', '9999999'])
            ->and($k->peserta()->where('mahasiswa_id', $a->id)->exists())->toBeTrue();
    });

    it('mengeluarkan peserta yang belum dinilai, tetapi tidak yang sudah', function () {
        $k = kknKegiatan();
        $a = $this->layanan->daftarkan($k, kknMahasiswa());
        $b = $this->layanan->daftarkan($k, kknMahasiswa());

        $this->layanan->keluarkan($a);
        $this->layanan->nilai($b, 80, 90);

        expect(AktivitasMahasiswa::find($a->id))->toBeNull()
            ->and(fn () => $this->layanan->keluarkan($b->fresh()))->toThrow(AturanAkademikException::class, 'sudah dinilai');
    });
});

describe('pembentukan kelompok', function () {
    it('meratakan ukuran kelompok: 23 peserta dengan target 10 menjadi 8, 8, 7', function () {
        $k = kknKegiatan();
        foreach (kknPeserta(23, $this->prodiA, Gender::LakiLaki, 'A') as $m) {
            $this->layanan->daftarkan($k, $m);
        }

        $kelompok = $this->layanan->bentukKelompokOtomatis($k);

        expect($kelompok->map(fn ($g) => $g->anggota()->count())->all())->toBe([8, 8, 7]);
    });

    it('menyebar prodi dan jenis kelamin ke setiap kelompok bila dicampur', function () {
        $k = kknKegiatan(['ukuran_kelompok' => 5]);
        foreach ([
            ...kknPeserta(5, $this->prodiA, Gender::LakiLaki, 'AL'),
            ...kknPeserta(5, $this->prodiA, Gender::Perempuan, 'AP'),
            ...kknPeserta(5, $this->prodiB, Gender::LakiLaki, 'BL'),
            ...kknPeserta(5, $this->prodiB, Gender::Perempuan, 'BP'),
        ] as $m) {
            $this->layanan->daftarkan($k, $m);
        }

        $kelompok = $this->layanan->bentukKelompokOtomatis($k);

        expect($kelompok)->toHaveCount(4);

        foreach ($kelompok as $g) {
            $anggota = $g->anggota()->with('mahasiswa')->get()->pluck('mahasiswa');

            expect($anggota)->toHaveCount(5)
                ->and($anggota->pluck('prodi_id')->unique()->sort()->values()->all())->toBe([$this->prodiA->id, $this->prodiB->id])
                ->and($anggota->map(fn ($m) => $m->jenis_kelamin->value)->unique()->sort()->values()->all())->toBe(['L', 'P']);
        }
    });

    it('membentuk kelompok di dalam satu prodi atau satu jenis kelamin bila aturannya dimatikan', function () {
        $peserta = collect([
            ...kknPeserta(4, $this->prodiA, Gender::LakiLaki, 'AL'),
            ...kknPeserta(4, $this->prodiA, Gender::Perempuan, 'AP'),
            ...kknPeserta(4, $this->prodiB, Gender::LakiLaki, 'BL'),
        ])->map(fn (Mahasiswa $m) => (new AktivitasMahasiswa(['mahasiswa_id' => $m->id]))->setRelation('mahasiswa', $m));

        $pembentuk = new PembentukKelompok;

        $perProdi = $pembentuk->bentuk($peserta, 4, campurProdi: false, campurJenisKelamin: true);
        $perJk = $pembentuk->bentuk($peserta, 4, campurProdi: true, campurJenisKelamin: false);

        foreach ($perProdi as $g) {
            expect(collect($g)->map(fn ($a) => $a->mahasiswa->prodi_id)->unique())->toHaveCount(1);
        }

        foreach ($perJk as $g) {
            expect(collect($g)->map(fn ($a) => $a->mahasiswa->jenis_kelamin->value)->unique())->toHaveCount(1);
        }

        expect(collect($perProdi)->flatten(1))->toHaveCount(12)
            ->and(collect($perJk)->flatten(1))->toHaveCount(12)
            ->and($pembentuk->bentuk($peserta, 4, false, true))->toEqual($perProdi);
    });

    it('menempatkan kelompok di lokasi sesuai kuota, menuliskan lokasi ke aktivitas, dan menyisakan kelompok tanpa lokasi', function () {
        $k = kknKegiatan(['ukuran_kelompok' => 2]);
        $sukamaju = kknLokasi($k, 'Sukamaju', 2);
        foreach (kknPeserta(6, $this->prodiA, Gender::LakiLaki, 'X') as $m) {
            $this->layanan->daftarkan($k, $m);
        }

        $kelompok = $this->layanan->bentukKelompokOtomatis($k);
        $aktivitas = $kelompok->first()->anggota()->first();

        expect($kelompok->pluck('lokasi_id')->all())->toBe([$sukamaju->id, $sukamaju->id, null])
            ->and($kelompok->pluck('nama')->all())->toBe(['Kelompok 01', 'Kelompok 02', 'Kelompok 03'])
            ->and($aktivitas->judul)->toBe('KKN Reguler 2026 · Kelompok 01 · Desa Sukamaju')
            ->and($aktivitas->mitra_nama)->toBe('Desa Sukamaju')
            ->and($aktivitas->mitra_jenis)->toBe('desa')
            ->and($aktivitas->lokasi)->toBe('Desa Sukamaju, Kec. Cibodas, Kabupaten Bandung Barat, Jawa Barat');
    });

    it('membiarkan peserta yang sudah berkelompok dan melanjutkan nomor kelompok', function () {
        $k = kknKegiatan(['ukuran_kelompok' => 3]);
        foreach (kknPeserta(3, $this->prodiA, Gender::LakiLaki, 'P') as $m) {
            $this->layanan->daftarkan($k, $m);
        }
        $pertama = $this->layanan->bentukKelompokOtomatis($k)->first();

        expect(fn () => $this->layanan->bentukKelompokOtomatis($k))
            ->toThrow(AturanAkademikException::class, 'belum berkelompok');

        $this->layanan->daftarkan($k, kknMahasiswa());
        $kedua = $this->layanan->bentukKelompokOtomatis($k)->first();

        expect($kedua->nomor)->toBe(2)
            ->and($pertama->anggota()->count())->toBe(3)
            ->and($kedua->anggota()->count())->toBe(1);
    });
});

describe('kelompok manual dan pembimbing', function () {
    it('meneruskan dosen pembimbing lapangan ke setiap aktivitas anggota', function () {
        $k = kknKegiatan();
        $dpl = Dosen::factory()->create();
        $kelompok = kknKelompokBerisi($k, [kknMahasiswa(), kknMahasiswa()], $dpl);

        expect($kelompok->anggota()->pluck('dosen_pembimbing_id')->unique()->all())->toBe([$dpl->id])
            ->and($kelompok->pembimbing_mitra_nama)->toBe('Bapak Kepala Desa');
    });

    it('menolak lokasi milik kegiatan lain, lokasi penuh, dan ketua yang bukan anggota', function () {
        $k = kknKegiatan();
        $lain = kknKegiatan(['nama' => 'PKL', 'jenis' => 'pkl']);
        $lokasiLain = kknLokasi($lain, 'Lain');
        $penuh = kknLokasi($k, 'Penuh', 1);

        $g1 = $this->layanan->buatKelompok($k, new KelompokKegiatanData(lokasiId: $penuh->id));
        $g2 = $this->layanan->buatKelompok($k, new KelompokKegiatanData);
        $orangLain = $this->layanan->daftarkan($lain, kknMahasiswa());

        expect(fn () => $this->layanan->aturKelompok($g2, new KelompokKegiatanData(lokasiId: $lokasiLain->id)))
            ->toThrow(AturanAkademikException::class, 'bukan milik kegiatan ini')
            ->and(fn () => $this->layanan->aturKelompok($g2, new KelompokKegiatanData(lokasiId: $penuh->id)))
            ->toThrow(AturanAkademikException::class, 'sudah penuh')
            ->and(fn () => $this->layanan->aturKelompok($g2, new KelompokKegiatanData(ketuaAktivitasId: $orangLain->id)))
            ->toThrow(AturanAkademikException::class, 'harus anggota')
            ->and($g1->nomor)->toBe(1)
            ->and($g2->nomor)->toBe(2);
    });

    it('memindahkan anggota antarkelompok kegiatan yang sama dan menyegarkan aktivitasnya', function () {
        $k = kknKegiatan();
        $dplA = Dosen::factory()->create();
        $dplB = Dosen::factory()->create();
        $a = $this->layanan->daftarkan($k, kknMahasiswa());
        $gA = $this->layanan->buatKelompok($k, new KelompokKegiatanData(dosenPembimbingId: $dplA->id));
        $gB = $this->layanan->buatKelompok($k, new KelompokKegiatanData(lokasiId: kknLokasi($k, 'Mekarsari')->id, dosenPembimbingId: $dplB->id));
        $lainKelompok = $this->layanan->buatKelompok(kknKegiatan(['nama' => 'Lain']), new KelompokKegiatanData);

        $this->layanan->pindahkan($a, $gA);
        $setelah = $this->layanan->pindahkan($a->fresh(), $gB);

        expect($setelah->kegiatan_lapangan_kelompok_id)->toBe($gB->id)
            ->and($setelah->dosen_pembimbing_id)->toBe($dplB->id)
            ->and($setelah->mitra_nama)->toBe('Desa Mekarsari')
            ->and(fn () => $this->layanan->pindahkan($setelah, $lainKelompok))
            ->toThrow(AturanAkademikException::class, 'kegiatan yang sama');
    });

    it('hanya menghapus kelompok yang kosong', function () {
        $k = kknKegiatan();
        $kelompok = kknKelompokBerisi($k, [kknMahasiswa()]);
        $kosong = $this->layanan->buatKelompok($k, new KelompokKegiatanData);

        $this->layanan->hapusKelompok($kosong);

        expect(KegiatanLapanganKelompok::find($kosong->id))->toBeNull()
            ->and(fn () => $this->layanan->hapusKelompok($kelompok))->toThrow(AturanAkademikException::class, 'beranggota');
    });
});

describe('nilai', function () {
    it('membobot nilai pembimbing lapangan dan mitra, dan menunggu sampai keduanya ada', function () {
        $k = kknKegiatan(['bobot_mitra' => 40]);
        $a = $this->layanan->daftarkan($k, kknMahasiswa());

        $sebagian = $this->layanan->nilai($a, 80, null)->nilai_akhir;
        $lengkap = $this->layanan->nilai($a, 80, 90);

        expect($sebagian)->toBeNull()
            ->and((float) $lengkap->nilai_akhir)->toBe(84.0)
            ->and($lengkap->dinilai_at)->not->toBeNull()
            ->and(fn () => $this->layanan->nilai($a, 101, 90))->toThrow(AturanAkademikException::class, 'antara 0 dan 100');
    });

    it('tidak menunggu nilai mitra bila bobot mitra nol', function () {
        $a = $this->layanan->daftarkan(kknKegiatan(['bobot_mitra' => 0]), kknMahasiswa());

        expect((float) $this->layanan->nilai($a, 77.5, null)->nilai_akhir)->toBe(77.5);
    });

    it('mengabaikan baris nilai yang menyebut aktivitas di luar kelompok', function () {
        $k = kknKegiatan();
        $kelompok = kknKelompokBerisi($k, [kknMahasiswa()]);
        $anggota = $kelompok->anggota()->first();
        $orangLain = $this->layanan->daftarkan(kknKegiatan(['nama' => 'Lain']), kknMahasiswa());

        $jumlah = $this->layanan->nilaiKelompok($kelompok, [
            $anggota->uuid => ['pembimbing' => 70.0, 'mitra' => 80.0],
            $orangLain->uuid => ['pembimbing' => 100.0, 'mitra' => 100.0],
        ]);

        expect($jumlah)->toBe(1)
            ->and((float) $anggota->fresh()->nilai_akhir)->toBe(74.0)
            ->and($orangLain->fresh()->nilai_akhir)->toBeNull();
    });
});

describe('konversi ke mata kuliah', function () {
    it('menulis baris KRS bertanda aktivitas dengan nilai final, memverifikasi aktivitas, dan menghitung ulang IPS', function () {
        $k = kknKegiatan();
        $dpl = Dosen::factory()->create();
        $m = kknMahasiswa();
        $kelompok = kknKelompokBerisi($k, [$m], $dpl);
        $a = $kelompok->anggota()->first();
        $this->layanan->nilai($a, 85, 80);
        $kelas = kknKelas(sks: 4);

        $hasil = $this->layanan->konversi($k, $kelas, $this->staf);

        $detail = KrsDetail::where('aktivitas_mahasiswa_id', $a->id)->firstOrFail();
        $nilai = Nilai::where('krs_detail_id', $detail->id)->firstOrFail();
        $a->refresh();

        expect($hasil->dikonversi)->toBe(1)
            ->and($detail->kelas_kuliah_id)->toBe($kelas->id)
            ->and($detail->sks)->toBe(4)
            ->and($detail->krs->mahasiswa_id)->toBe($m->id)
            ->and((float) $nilai->nilai_angka)->toBe(83.0)
            ->and($nilai->nilai_huruf)->toBe(GradeLetter::fromScore(83.0))
            ->and($nilai->is_final)->toBeTrue()
            ->and($nilai->finalized_by_dosen_id)->toBe($dpl->id)
            ->and($a->is_verified)->toBeTrue()
            ->and($a->verified_by_staff_id)->toBe($this->staf->id)
            ->and($a->sks_konversi)->toBe(4)
            ->and($kelas->fresh()->terisi)->toBe(1)
            ->and((float) StatusMahasiswa::where('mahasiswa_id', $m->id)->value('ips'))->toBe((float) $nilai->bobot)
            ->and(LogAktivitas::query()->where('subject_id', $nilai->id)->where('event', 'converted')->exists())->toBeTrue();
    });

    it('melewati peserta yang belum dinilai, peserta prodi lain, dan yang sudah dikonversi', function () {
        $k = kknKegiatan();
        $dinilai = kknMahasiswa(nim: '3001');
        $belum = kknMahasiswa(nim: '3002');
        $prodiLain = kknMahasiswa($this->prodiB, nim: '3003');
        $kelompok = kknKelompokBerisi($k, [$dinilai, $belum, $prodiLain]);
        $this->layanan->nilaiKelompok($kelompok, $kelompok->anggota()->get()->mapWithKeys(fn ($a) => [
            $a->uuid => ['pembimbing' => $a->mahasiswa_id === $belum->id ? null : 80.0, 'mitra' => 80.0],
        ])->all());
        $kelas = kknKelas();

        $pertama = $this->layanan->konversi($k, $kelas, $this->staf);
        $kedua = $this->layanan->konversi($k, $kelas, $this->staf);

        expect($pertama->dikonversi)->toBe(1)
            ->and($pertama->dilewati)->toHaveKey('3002')
            ->and($pertama->dilewati)->not->toHaveKey('3003')
            ->and($kedua->dikonversi)->toBe(0)
            ->and($kedua->dilewati['3001'])->toBe(__('kegiatan_lapangan.galat.sudah_dikonversi'))
            ->and(KrsDetail::whereNotNull('aktivitas_mahasiswa_id')->count())->toBe(1);
    });

    it('menempelkan aktivitas pada baris KRS mata kuliah yang sama bila mahasiswa sudah mengambilnya', function () {
        $k = kknKegiatan();
        $m = kknMahasiswa();
        $kelompok = kknKelompokBerisi($k, [$m]);
        $this->layanan->nilai($kelompok->anggota()->first(), 90, 90);
        $kelas = kknKelas();
        $krs = Krs::factory()->create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'status' => KrsStatus::Disetujui]);
        $baris = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => $kelas->sks]);

        $this->layanan->konversi($k, $kelas, $this->staf);

        expect(KrsDetail::where('krs_id', $krs->id)->count())->toBe(1)
            ->and($baris->fresh()->aktivitas_mahasiswa_id)->toBe($kelompok->anggota()->first()->id)
            ->and($baris->fresh()->nilai->is_final)->toBeTrue();
    });

    it('menolak mengonversi ke mata kuliah yang sudah bernilai final, dan kelas semester lain', function () {
        $k = kknKegiatan();
        $m = kknMahasiswa(nim: '4001');
        $kelompok = kknKelompokBerisi($k, [$m]);
        $this->layanan->nilai($kelompok->anggota()->first(), 90, 90);
        $kelas = kknKelas();
        $krs = Krs::factory()->create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id]);
        $baris = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => $kelas->sks]);
        Nilai::create(['krs_detail_id' => $baris->id, 'kelas_kuliah_id' => $kelas->id, 'mahasiswa_id' => $m->id, 'nilai_angka' => 60, 'nilai_huruf' => 'C', 'bobot' => 2, 'is_final' => true]);

        $hasil = $this->layanan->konversi($k, $kelas, $this->staf);
        $kelasLain = KelasKuliah::factory()->create([
            'prodi_id' => $this->prodiA->id,
            'tahun_akademik_id' => TahunAkademik::factory()->term(2025, SemesterType::Genap)->create()->id,
        ]);

        expect($hasil->dikonversi)->toBe(0)
            ->and($hasil->dilewati['4001'])->toContain('sudah bernilai final')
            ->and((float) $baris->fresh()->nilai->nilai_angka)->toBe(60.0)
            ->and(fn () => $this->layanan->konversi($k, $kelasLain, $this->staf))
            ->toThrow(AturanAkademikException::class, 'tahun akademik yang sama');
    });

    it('mengunci nilai kegiatan dan perpindahan kelompok sesudah dikonversi', function () {
        $k = kknKegiatan();
        $kelompok = kknKelompokBerisi($k, [kknMahasiswa()]);
        $a = $kelompok->anggota()->first();
        $this->layanan->nilai($a, 80, 80);
        $this->layanan->konversi($k, kknKelas(), $this->staf);

        expect(fn () => $this->layanan->nilai($a->fresh(), 100, 100))->toThrow(AturanAkademikException::class, 'terkunci')
            ->and(fn () => $this->layanan->pindahkan($a->fresh(), null))->toThrow(AturanAkademikException::class, 'terkunci');
    });

    it('mengeluarkan baris konversi dari lembar nilai dosen, sehingga finalisasi kelas tidak menimpanya', function () {
        $this->term->update(['nilai_mulai' => now()->subDay(), 'nilai_selesai' => now()->addWeek()]);
        $k = kknKegiatan();
        $kelompok = kknKelompokBerisi($k, [kknMahasiswa()]);
        $this->layanan->nilai($kelompok->anggota()->first(), 95, 95);
        $kelas = kknKelas();
        $this->layanan->konversi($k, $kelas, $this->staf);

        // Satu mahasiswa yang mengambil kelas yang sama lewat KRS biasa.
        $reguler = kknMahasiswa();
        $krs = Krs::factory()->create(['mahasiswa_id' => $reguler->id, 'tahun_akademik_id' => $this->term->id]);
        $baris = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => $kelas->sks]);

        $penilaian = app(PenilaianService::class);
        $komponen = $penilaian->komponen($kelas);
        $penilaian->simpanNilai($kelas, [$baris->id => $komponen->mapWithKeys(fn (KomponenNilai $kn) => [$kn->id => 50])->all()]);
        $penilaian->finalisasi($kelas, Dosen::factory()->create());

        $konversi = Nilai::whereHas('krsDetail', fn ($q) => $q->whereNotNull('aktivitas_mahasiswa_id'))->firstOrFail();

        expect($penilaian->lembarNilai($kelas))->toHaveCount(1)
            ->and((float) $konversi->nilai_angka)->toBe(95.0)
            ->and(NilaiKomponen::where('krs_detail_id', $konversi->krs_detail_id)->exists())->toBeFalse()
            ->and((float) $baris->fresh()->nilai->nilai_angka)->toBe(50.0);
    });
});

describe('Feeder', function () {
    beforeEach(function () {
        config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);
        $this->fake = new FakeFeederClient;
        $this->app->instance(FeederClientInterface::class, $this->fake);

        FeederMapping::create(['group' => 'jenis_aktivitas', 'local_value' => 'kkn', 'feeder_code' => '5']);
        FeederMapping::create(['group' => 'jenis_aktivitas', 'local_value' => 'membangun_desa', 'feeder_code' => '19']);
        FeederMapping::create(['group' => 'kategori_kegiatan', 'local_value' => 'pembimbing_mbkm', 'feeder_code' => '110100']);

        FeederSyncLog::create([
            'entity' => 'riwayat_pendidikan',
            'action' => config('feeder.entities.riwayat_pendidikan.action'),
            'tahun_akademik_id' => $this->term->id,
            'status' => FeederSyncStatus::Success,
            'payload_hash' => str_repeat('0', 64),
        ]);
    });

    it('mengirim peserta KKN reguler sebagai aktivitas dengan SK kegiatan, lokasi desa, dan bukan kampus merdeka', function () {
        $k = kknKegiatan();
        $m = kknMahasiswa();
        $m->forceFill(['feeder_id' => 'mhs-1', 'feeder_registrasi_id' => 'reg-1'])->save();
        $kelompok = kknKelompokBerisi($k, [$m], null, kknLokasi($k));
        $a = $kelompok->anggota()->first();
        $this->layanan->nilai($a, 80, 80);
        $this->layanan->konversi($k, kknKelas(), $this->staf);

        $hasil = app(FeederSyncService::class)->sinkronkan('aktivitas_mbkm', $this->term);

        expect($hasil['terkirim'])->toBe(1)
            ->and(FeederSyncLog::entity('aktivitas_mbkm')->firstOrFail()->payload)->toBe([
                'id_jenis_aktivitas' => '5',
                'id_prodi' => 'prodi-a',
                'id_semester' => $this->term->kode,
                'judul' => 'KKN Reguler 2026 · Kelompok 01 · Desa Sukamaju',
                'lokasi' => 'Desa Sukamaju, Kec. Cibodas, Kabupaten Bandung Barat, Jawa Barat',
                'sk_tugas' => '123/UN/KKN/2026',
                'tanggal_sk_tugas' => $this->term->tanggal_mulai->toDateString(),
                'untuk_kampus_merdeka' => 0,
            ]);
    });

    it('menandai KKN tematik (membangun desa) sebagai kampus merdeka, dan mengirim DPL sebagai pembimbing', function () {
        $k = kknKegiatan(['jenis' => StudentActivityType::MembangunDesa->value, 'nama' => 'KKN Tematik']);
        $dpl = Dosen::factory()->create(['feeder_id' => 'dosen-7']);
        $m = kknMahasiswa();
        $m->forceFill(['feeder_id' => 'mhs-2', 'feeder_registrasi_id' => 'reg-2'])->save();
        $a = kknKelompokBerisi($k, [$m], $dpl)->anggota()->first();
        $a->forceFill(['is_verified' => true])->save();

        app(FeederSyncService::class)->sinkronkan('aktivitas_mbkm', $this->term);
        $a->refresh();
        FeederSyncLog::create([
            'entity' => 'aktivitas_mbkm',
            'action' => config('feeder.entities.aktivitas_mbkm.action'),
            'tahun_akademik_id' => $this->term->id,
            'status' => FeederSyncStatus::Success,
            'payload_hash' => str_repeat('1', 64),
        ]);
        app(FeederSyncService::class)->sinkronkan('bimbing_mbkm', $this->term);

        expect(FeederSyncLog::entity('aktivitas_mbkm')->whereNotNull('payload')->firstOrFail()->payload['untuk_kampus_merdeka'])->toBe(1)
            ->and(FeederSyncLog::entity('bimbing_mbkm')->firstOrFail()->payload)->toBe([
                'id_aktivitas' => $a->feeder_id,
                'id_kategori_kegiatan' => '110100',
                'id_dosen' => 'dosen-7',
                'pembimbing_ke' => 1,
            ]);
    });

    it('mengirim konversi KKN sebagai konversi kampus merdeka pada anggota aktivitasnya', function () {
        $k = kknKegiatan();
        $m = kknMahasiswa();
        $a = kknKelompokBerisi($k, [$m])->anggota()->first();
        $this->layanan->nilai($a, 90, 80);
        $kelas = kknKelas(sks: 4);
        $kelas->mataKuliah->forceFill(['feeder_id' => 'mk-kkn'])->save();
        $this->layanan->konversi($k, $kelas, $this->staf);
        $a->refresh()->forceFill(['feeder_id' => 'akt-1', 'feeder_anggota_id' => 'anggota-1'])->save();
        KrsDetail::where('aktivitas_mahasiswa_id', $a->id)->update(['feeder_id' => 'krs-1']);

        FeederSyncLog::create([
            'entity' => 'anggota_mbkm',
            'action' => config('feeder.entities.anggota_mbkm.action'),
            'tahun_akademik_id' => $this->term->id,
            'status' => FeederSyncStatus::Success,
            'payload_hash' => str_repeat('2', 64),
        ]);
        foreach ((array) config('feeder.entities.konversi_mbkm.depends_on', []) as $syarat) {
            FeederSyncLog::create([
                'entity' => $syarat,
                'action' => config("feeder.entities.{$syarat}.action"),
                'tahun_akademik_id' => $this->term->id,
                'status' => FeederSyncStatus::Success,
                'payload_hash' => str_repeat('3', 64),
            ]);
        }

        app(FeederSyncService::class)->sinkronkan('konversi_mbkm', $this->term);

        expect(FeederSyncLog::entity('konversi_mbkm')->whereNotNull('payload')->firstOrFail()->payload)->toBe([
            'id_matkul' => 'mk-kkn',
            'id_anggota' => 'anggota-1',
            'id_semester' => $this->term->kode,
            'sks_mata_kuliah' => 4,
            'nilai_angka' => '86.00',
            'nilai_huruf' => GradeLetter::fromScore(86.0)->value,
            'nilai_indeks' => number_format(GradeLetter::fromScore(86.0)->weight(), 2, '.', ''),
        ]);
    });
});

describe('logbook', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    it('mencatat logbook beserta lampiran hanya sesudah peserta masuk kelompok', function () {
        $k = kknKegiatan();
        $lepas = $this->layanan->daftarkan($k, kknMahasiswa());
        $a = kknKelompokBerisi($k, [kknMahasiswa()])->anggota()->first();
        $tanggal = $k->tanggal_mulai->copy()->addDay()->toDateString();

        expect(fn () => $this->logbook->catat($lepas, new LogbookData(JenisLogbook::Harian, $tanggal, 'Hari pertama')))
            ->toThrow(AturanAkademikException::class, 'masuk kelompok');

        $entri = $this->logbook->catat($a, new LogbookData(
            JenisLogbook::Mingguan,
            $tanggal,
            'Pekan pertama',
            'Survei potensi desa.',
            UploadedFile::fake()->create('laporan.pdf', 50, 'application/pdf'),
        ));

        expect($entri->berkas_nama)->toBe('laporan.pdf')
            ->and(Storage::disk('local')->exists($entri->berkas_path))->toBeTrue()
            ->and($entri->jenis)->toBe(JenisLogbook::Mingguan);
    });

    it('menolak tanggal sebelum kegiatan dimulai atau di masa depan', function () {
        $k = kknKegiatan();
        $a = kknKelompokBerisi($k, [kknMahasiswa()])->anggota()->first();

        expect(fn () => $this->logbook->catat($a, new LogbookData(JenisLogbook::Harian, $k->tanggal_mulai->copy()->subDay()->toDateString(), 'Terlalu awal')))
            ->toThrow(AturanAkademikException::class, 'Tanggal logbook')
            ->and(fn () => $this->logbook->catat($a, new LogbookData(JenisLogbook::Harian, now()->addDay()->toDateString(), 'Besok')))
            ->toThrow(AturanAkademikException::class, 'Tanggal logbook');
    });

    it('hanya DPL kelompok yang memeriksa, dan logbook terperiksa tidak dapat dihapus', function () {
        $k = kknKegiatan();
        $dpl = Dosen::factory()->create();
        $a = kknKelompokBerisi($k, [kknMahasiswa()], $dpl)->anggota()->first();
        $entri = $this->logbook->catat($a, new LogbookData(JenisLogbook::Harian, $k->tanggal_mulai->toDateString(), 'Pembukaan'));

        expect(fn () => $this->logbook->periksa($entri, Dosen::factory()->create()))
            ->toThrow(AturanAkademikException::class, 'pembimbing lapangan');

        $this->logbook->periksa($entri, $dpl, 'Lanjutkan.');

        expect($entri->fresh()->catatan_pembimbing)->toBe('Lanjutkan.')
            ->and(fn () => $this->logbook->hapus($entri->fresh()))->toThrow(AturanAkademikException::class, 'sudah diperiksa')
            ->and(fn () => $this->logbook->periksa($entri->fresh(), $dpl))->toThrow(AturanAkademikException::class, 'sudah diperiksa');
    });
});

describe('layar dan otorisasi per portal', function () {
    it('membuka layar staf dan menolak staf keuangan membuat kegiatan', function () {
        $k = kknKegiatan();
        kknKelompokBerisi($k, [kknMahasiswa(), kknMahasiswa()], Dosen::factory()->create(), kknLokasi($k));

        kknSebagai($this->staf, 'staff')->get(route('admin.kegiatan-lapangan.index'))->assertOk()->assertSee('KKN Reguler 2026');
        kknSebagai($this->staf, 'staff')->get(route('admin.kegiatan-lapangan.show', $k))->assertOk()->assertSee('Kelompok 01')->assertSee('Desa Sukamaju');
        kknSebagai($this->staf, 'staff')->get(route('admin.kegiatan-lapangan.create'))->assertOk();
        kknSebagai($this->staf, 'staff')->get(route('admin.kegiatan-lapangan.edit', $k))->assertOk();

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        kknSebagai($keuangan, 'staff')->get(route('admin.kegiatan-lapangan.index'))->assertOk();
        kknSebagai($keuangan, 'staff')->post(route('admin.kegiatan-lapangan.store'), [
            'tahun_akademik_id' => $this->term->id, 'jenis' => 'kkn', 'nama' => 'X',
            'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-11-01',
            'min_sks' => 0, 'ukuran_kelompok' => 10, 'bobot_mitra' => 40,
        ])->assertForbidden();
    });

    it('membuat kegiatan, menambah peserta, dan mengonversi lewat layar staf', function () {
        kknSebagai($this->staf, 'staff')->post(route('admin.kegiatan-lapangan.store'), [
            'tahun_akademik_id' => $this->term->id, 'jenis' => 'kkn', 'nama' => 'KKN Layar',
            'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-11-01',
            'min_sks' => 0, 'ukuran_kelompok' => 10, 'bobot_mitra' => 40, 'campur_prodi' => '1', 'campur_jenis_kelamin' => '1',
        ])->assertRedirect();

        $k = KegiatanLapangan::where('nama', 'KKN Layar')->firstOrFail();
        $m = kknMahasiswa(nim: '5001');

        kknSebagai($this->staf, 'staff')->post(route('admin.kegiatan-lapangan.peserta', $k), ['nim' => "5001\n5999"])
            ->assertSessionHas('ditolak', fn (array $d) => array_map('strval', array_keys($d)) === ['5999']);
        kknSebagai($this->staf, 'staff')->post(route('admin.kegiatan-lapangan.bentuk', $k))->assertSessionHas('sukses');

        $kelompok = $k->kelompok()->firstOrFail();
        $a = $kelompok->anggota()->firstOrFail();

        kknSebagai($this->staf, 'staff')->post(route('admin.kegiatan-lapangan.kelompok.nilai', $kelompok), [
            'nilai' => [$a->uuid => ['pembimbing' => '80', 'mitra' => '70']],
        ])->assertSessionHas('sukses');

        $kelas = kknKelas();
        kknSebagai($this->staf, 'staff')->post(route('admin.kegiatan-lapangan.konversi', $k), ['kelas_kuliah_id' => $kelas->uuid])
            ->assertSessionHas('sukses');

        expect($a->fresh()->krsKonversi)->not->toBeNull()
            ->and((float) $a->fresh()->nilai_akhir)->toBe(76.0)
            ->and($m->krs()->exists())->toBeTrue();
    });

    it('membatasi dosen pada kelompok yang dibimbingnya', function () {
        $k = kknKegiatan();
        $dpl = Dosen::factory()->create();
        $lain = Dosen::factory()->create();
        foreach ([$dpl, $lain] as $d) {
            $d->assignRole('dosen');
        }
        $kelompok = kknKelompokBerisi($k, [kknMahasiswa()], $dpl);
        $a = $kelompok->anggota()->first();
        $entri = $this->logbook->catat($a, new LogbookData(JenisLogbook::Harian, $k->tanggal_mulai->toDateString(), 'Hari pertama'));

        kknSebagai($dpl, 'dosen')->get(route('dosen.kegiatan-lapangan'))->assertOk()->assertSee('Kelompok 01');
        kknSebagai($dpl, 'dosen')->get(route('dosen.kegiatan-lapangan.show', $kelompok))->assertOk()->assertSee('Hari pertama');
        kknSebagai($lain, 'dosen')->get(route('dosen.kegiatan-lapangan.show', $kelompok))->assertForbidden();

        kknSebagai($lain, 'dosen')->post(route('dosen.kegiatan-lapangan.nilai', $kelompok), [
            'nilai' => [$a->uuid => ['pembimbing' => '100', 'mitra' => '100']],
        ])->assertForbidden();
        kknSebagai($lain, 'dosen')->post(route('dosen.kegiatan-lapangan.periksa', $entri))->assertForbidden();

        kknSebagai($dpl, 'dosen')->post(route('dosen.kegiatan-lapangan.nilai', $kelompok), [
            'nilai' => [$a->uuid => ['pembimbing' => '90', 'mitra' => '80']],
        ])->assertSessionHas('sukses');
        kknSebagai($dpl, 'dosen')->post(route('dosen.kegiatan-lapangan.periksa', $entri), ['catatan' => 'Baik'])->assertSessionHas('sukses');

        expect((float) $a->fresh()->nilai_akhir)->toBe(86.0)
            ->and($entri->fresh()->sudahDiperiksa())->toBeTrue();
    });

    it('membiarkan mahasiswa mendaftar sendiri dan menulis logbooknya sendiri saja', function () {
        Storage::fake('local');
        $terbuka = kknKegiatan(['pendaftaran_dibuka' => true, 'nama' => 'KKN Terbuka']);
        $tertutup = kknKegiatan(['nama' => 'KKN Tertutup']);
        $m = kknMahasiswa();
        $lain = kknMahasiswa();

        kknSebagai($m, 'mahasiswa')->get(route('mahasiswa.kegiatan-lapangan'))->assertOk()->assertSee('KKN Terbuka')->assertDontSee('KKN Tertutup');
        kknSebagai($m, 'mahasiswa')->post(route('mahasiswa.kegiatan-lapangan.daftar', $tertutup))->assertForbidden();
        kknSebagai($m, 'mahasiswa')->post(route('mahasiswa.kegiatan-lapangan.daftar', $terbuka))->assertSessionHas('sukses');

        $milikLain = kknKelompokBerisi($terbuka, [$lain])->anggota()->where('mahasiswa_id', $lain->id)->firstOrFail();
        $milikSendiri = $terbuka->peserta()->where('mahasiswa_id', $m->id)->firstOrFail();
        $this->layanan->pindahkan($milikSendiri, $milikLain->kelompok);

        $isian = ['jenis' => 'harian', 'tanggal' => $terbuka->tanggal_mulai->toDateString(), 'judul' => 'Rapat desa'];

        kknSebagai($m, 'mahasiswa')->post(route('mahasiswa.kegiatan-lapangan.logbook', $milikLain), $isian)->assertForbidden();
        kknSebagai($m, 'mahasiswa')->post(route('mahasiswa.kegiatan-lapangan.logbook', $milikSendiri), $isian)->assertSessionHas('sukses');

        $entriLain = $this->logbook->catat($milikLain, new LogbookData(JenisLogbook::Harian, $terbuka->tanggal_mulai->toDateString(), 'Punya teman'));

        kknSebagai($m, 'mahasiswa')->delete(route('mahasiswa.kegiatan-lapangan.logbook.hapus', $entriLain))->assertForbidden();
        kknSebagai($m, 'mahasiswa')->get(route('mahasiswa.kegiatan-lapangan'))->assertOk()->assertSee('Rapat desa')->assertSee('Kelompok 01');

        expect(KegiatanLapanganLogbook::where('aktivitas_mahasiswa_id', $milikSendiri->id)->count())->toBe(1);
    });

    it('melindungi lampiran logbook: penulis, DPL, dan staf saja', function () {
        Storage::fake('local');
        $k = kknKegiatan();
        $dpl = Dosen::factory()->create();
        $m = kknMahasiswa();
        $a = kknKelompokBerisi($k, [$m], $dpl)->anggota()->first();
        $entri = $this->logbook->catat($a, new LogbookData(
            JenisLogbook::LaporanAkhir,
            $k->tanggal_mulai->toDateString(),
            'Laporan akhir',
            null,
            UploadedFile::fake()->create('akhir.pdf', 20, 'application/pdf'),
        ));

        kknSebagai($m, 'mahasiswa')->get(route('berkas.logbook-kegiatan', $entri))->assertOk();
        kknSebagai($dpl, 'dosen')->get(route('berkas.logbook-kegiatan', $entri))->assertOk();
        kknSebagai($this->staf, 'staff')->get(route('berkas.logbook-kegiatan', $entri))->assertOk();
        kknSebagai(kknMahasiswa(), 'mahasiswa')->get(route('berkas.logbook-kegiatan', $entri))->assertForbidden();
        kknSebagai(Dosen::factory()->create(), 'dosen')->get(route('berkas.logbook-kegiatan', $entri))->assertForbidden();
    });
});
