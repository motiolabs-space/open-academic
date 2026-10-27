<?php

declare(strict_types=1);

use App\DTOs\Feeder\KlaimLampauData;
use App\Enums\AlasanAmbiguAdopsi;
use App\Enums\FeederSyncStatus;
use App\Enums\MasalahKlaimLampau;
use App\Enums\SemesterType;
use App\Enums\StatusAdopsiFeeder;
use App\Enums\StatusLampauFeeder;
use App\Exceptions\FeederException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederAdoption;
use App\Models\Feeder\FeederPastStudent;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederAdopsi;
use App\Services\Feeder\FeederKlaimLampau;
use App\Services\Feeder\FeederSyncService;
use Database\Seeders\RolePermissionSeeder;

/**
 * Klaim mahasiswa lampau (#44b).
 *
 * Mahasiswa lama dapat tercatat di PDDIKTI tanpa muncul di Feeder kampus,
 * sehingga adopsi tidak menemukannya. Yang diuji: daftar lampau dinilai
 * dengan pencocok adopsi yang sama, hanya baris pilihan yang diklaim, semua
 * masalah dilaporkan sebelum apa pun dikirim, klaim tidak pernah terkirim
 * dua kali, penolakan dapat dicoba lagi, dan yang diklaim langsung diadopsi
 * lewat jalur #44 — bukan jalur kedua.
 *
 * Kasusnya di tests/Fixtures/feeder/mahasiswa-lampau.json.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);
    $this->fake->mahasiswaLampau(fixtureLampau());
    // Tidak ada yang terdaftar di Feeder kampus sebelum diklaim.
    $this->fake->mahasiswaTerdaftar([]);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'prodi-ti-0001']);
    $this->staf = Staff::factory()->create();

    $this->lampau = app(FeederKlaimLampau::class);
});

/** @return array<int, array<string, mixed>> */
function fixtureLampau(): array
{
    $isi = json_decode((string) file_get_contents(base_path('tests/Fixtures/feeder/mahasiswa-lampau.json')), true, flags: JSON_THROW_ON_ERROR);

    return $isi['orang'];
}

function lokalLampau(string $nim, ?string $nik, string $nama, array $atribut = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'term_masuk' => '20191',
        'nim' => $nim,
        'nik' => $nik,
        'nama' => $nama,
    ], $atribut));
}

/** Satu mahasiswa lokal untuk setiap kasus di fixture yang punya pasangan di sini. */
function angkatanLampau(): array
{
    return [
        'hana' => lokalLampau('2019001', '3201010101020001', 'Hana Maharani'),
        'indra' => lokalLampau('2019002', '3201010101020002', 'Indra Gunawan'),
        'joko' => lokalLampau('2019003', '3201010101020003', 'Joko Susilo'),
        'kartika' => lokalLampau('2019004', '3201010101020004', 'Kartika Sari'),
        'lukman' => lokalLampau('2019005', '3201010101020005', 'Lukman Hakim'),
        'nanda' => lokalLampau('2019007', '3201010101020007', 'Nanda Pertiwi'),
        'oki' => lokalLampau('2019008', '3201010101020008', 'Oki Setiawan'),
    ];
}

function barisLampau(string $reg): FeederPastStudent
{
    return FeederPastStudent::query()->where('feeder_registrasi_id', $reg)->sole();
}

/** @param list<string> $reg */
function pilihLampau(array $reg, ?string $catatan = null): KlaimLampauData
{
    return KlaimLampauData::fromArray([
        'periode' => '20191',
        'baris' => array_map(fn (string $r): string => barisLampau($r)->uuid, $reg),
        'catatan' => $catatan,
    ], test()->prodi);
}

describe('membaca dan mencocokkan', function () {
    it('memilah daftar lampau menjadi cocok, ambigu, dan tanpa mahasiswa lokal', function () {
        $m = angkatanLampau();

        $hasil = $this->lampau->baca($this->prodi, '20191');

        expect($hasil)->toMatchArray([
            'feeder' => 9,
            'cocok' => 4,
            'ambigu' => 4,
            'tanpa_lokal' => 1,
            'diklaim' => 0,
            'tidak_lengkap' => 1,
        ])
            ->and(barisLampau('reg-hana'))
            ->status->toBe(StatusLampauFeeder::Cocok)
            ->mahasiswa_id->toBe($m['hana']->id)
            ->nik_cocok->toBeTrue()
            ->kunci_klaim->toBe(['id_registrasi_mahasiswa' => 'reg-hana'])
            ->and(barisLampau('reg-maya')->status)->toBe(StatusLampauFeeder::TanpaLokal)
            ->and(barisLampau('reg-maya')->mahasiswa_id)->toBeNull();
    });

    it('membiarkan NIK mengalahkan nama, dan nama menggantikan NIK yang kosong', function () {
        $m = angkatanLampau();

        $this->lampau->baca($this->prodi, '20191');

        expect(barisLampau('reg-indra')->status)->toBe(StatusLampauFeeder::Cocok)
            ->and(barisLampau('reg-indra')->nama_feeder)->toBe('Indra Gunawam')
            ->and(barisLampau('reg-oki'))
            ->status->toBe(StatusLampauFeeder::Cocok)
            ->mahasiswa_id->toBe($m['oki']->id)
            ->nik_cocok->toBeNull();
    });

    it('memakai alasan ambigu yang sama dengan adopsi', function () {
        angkatanLampau();

        $this->lampau->baca($this->prodi, '20191');

        expect(barisLampau('reg-joko')->alasan)->toBe(AlasanAmbiguAdopsi::NikBerbeda)
            ->and(barisLampau('reg-joko')->nik_cocok)->toBeFalse()
            ->and(barisLampau('reg-kartika-a')->alasan)->toBe(AlasanAmbiguAdopsi::KandidatGanda)
            ->and(barisLampau('reg-kartika-b')->alasan)->toBe(AlasanAmbiguAdopsi::KandidatGanda)
            ->and(barisLampau('reg-lukman')->alasan)->toBe(AlasanAmbiguAdopsi::NimBerbeda);
    });

    it('menandai ambigu catatan lampau bagi mahasiswa yang sudah tertaut ke data Feeder lain', function () {
        $hana = lokalLampau('2019001', '3201010101020001', 'Hana Maharani', ['feeder_id' => 'mhs-entah', 'feeder_registrasi_id' => 'reg-entah']);

        $this->lampau->baca($this->prodi, '20191');

        expect(barisLampau('reg-hana'))
            ->status->toBe(StatusLampauFeeder::Ambigu)
            ->alasan->toBe(AlasanAmbiguAdopsi::TertautLain)
            ->mahasiswa_id->toBe($hana->id);
    });

    it('membaca hanya prodi dan periode yang diminta, tanpa menyimpan NIK', function () {
        angkatanLampau();

        $this->lampau->baca($this->prodi, '20191');

        $semua = FeederPastStudent::all()->toJson();

        expect(FeederPastStudent::pluck('nim_feeder'))->not->toContain('2019009')->not->toContain('2019010')
            ->and($semua)->not->toContain('3201010101020001')
            ->and($semua)->not->toContain('3201010101029993')
            ->and(collect($this->fake->riwayat)->pluck('act')->unique()->values()->all())->toBe(['GetListDaftarLampau']);
    });

    it('mengganti temuan lama tetapi mempertahankan yang sudah diklaim', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        $this->lampau->klaim(pilihLampau(['reg-hana']), $this->staf, $this->term);

        $kedua = $this->lampau->baca($this->prodi, '20191');

        expect($kedua['diklaim'])->toBe(1)
            ->and($kedua['feeder'])->toBe(8)
            ->and(FeederPastStudent::count())->toBe(9)
            ->and(FeederPastStudent::where('feeder_registrasi_id', 'reg-hana')->sole()->status)->toBe(StatusLampauFeeder::Diklaim);
    });

    it('membaca seluruh halaman, bukan hanya yang pertama', function () {
        config(['feeder.reconcile_page_size' => 2]);
        angkatanLampau();

        expect($this->lampau->baca($this->prodi, '20191')['feeder'])->toBe(9);
    });

    it('berhenti bila Feeder menolak pembacaan, bukan menyimpulkan daftarnya kosong', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        $this->fake->tolak('GetListDaftarLampau', 100, 'Aksi tidak dikenal');

        expect(fn () => $this->lampau->baca($this->prodi, '20191'))->toThrow(FeederException::class)
            ->and(FeederPastStudent::count())->toBe(9);
    });

    it('menolak prodi tanpa kode PDDIKTI', function () {
        $this->lampau->baca(Prodi::factory()->create(['kode_pddikti' => null]), '20191');
    })->throws(FeederException::class, 'id_sms');

    it('menolak berjalan saat integrasi dimatikan', function () {
        config(['feeder.enabled' => false]);

        $this->lampau->baca($this->prodi, '20191');
    })->throws(FeederException::class);

    it('mengurutkan laporan: cocok, ambigu, lalu sisanya', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $status = $this->lampau->laporan($this->prodi, '20191')->map(fn ($b) => $b->status)->unique()->values()->all();

        expect($status)->toBe([StatusLampauFeeder::Cocok, StatusLampauFeeder::Ambigu, StatusLampauFeeder::TanpaLokal]);
    });
});

describe('validasi pra-kirim', function () {
    it('melaporkan seluruh baris yang akan gagal sekaligus, tanpa mengirim apa pun', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $galat = $this->lampau->periksa(pilihLampau(['reg-hana', 'reg-maya', 'reg-joko']));

        expect($galat)->toHaveCount(2)
            ->and(collect($galat)->pluck('nim')->all())->toBe(['2019003', '2019006'])
            ->and(collect($galat)->pluck('pesan')->all())->toBe([
                MasalahKlaimLampau::AmbiguTanpaAlasan->label(),
                MasalahKlaimLampau::TanpaMahasiswaLokal->label(),
            ])
            ->and($this->fake->dipanggil('KlaimMahasiswaLampau'))->toBeFalse();
    });

    it('tidak mengklaim satu pun bila ada baris yang gagal diperiksa', function () {
        $m = angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $hasil = $this->lampau->klaim(pilihLampau(['reg-hana', 'reg-maya']), $this->staf, $this->term);

        expect($hasil->berhasil())->toBeFalse()
            ->and($hasil->galat)->toHaveCount(1)
            ->and($hasil->diklaim)->toBe(0)
            ->and($this->fake->dipanggil('KlaimMahasiswaLampau'))->toBeFalse()
            ->and(FeederSyncLog::count())->toBe(0)
            ->and(barisLampau('reg-hana')->status)->toBe(StatusLampauFeeder::Cocok)
            ->and($m['hana']->refresh()->feeder_id)->toBeNull();
    });

    it('memeriksa ulang terhadap PDDIKTI, bukan terhadap laporan yang mungkin basi', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        // Sejak dibaca, catatan ini diklaim lewat Feeder sendiri dan hilang dari daftar.
        $this->fake->mahasiswaLampau(collect(fixtureLampau())->reject(fn ($o) => ($o['id_registrasi_mahasiswa'] ?? null) === 'reg-hana')->values()->all());

        $galat = $this->lampau->periksa(pilihLampau(['reg-hana', 'reg-indra']));

        expect($galat)->toHaveCount(1)
            ->and($galat[0]['nim'])->toBe('2019001')
            ->and($galat[0]['pesan'])->toBe(MasalahKlaimLampau::TidakTerdaftarLagi->label());
    });

    it('menolak registrasi yang sudah dipegang mahasiswa lain di SIAKAD', function () {
        angkatanLampau();
        lokalLampau('2019999', null, 'Orang Lain', ['feeder_registrasi_id' => 'reg-indra']);
        $this->lampau->baca($this->prodi, '20191');

        $galat = $this->lampau->periksa(pilihLampau(['reg-indra']));

        expect($galat[0]['pesan'] ?? null)->toBe(MasalahKlaimLampau::RegistrasiDipakai->label());
    });

    it('menolak baris tanpa kunci klaim yang diperlukan', function () {
        config(['feeder.adopt.lampau.claim_key' => ['id_registrasi_mahasiswa', 'id_pt_lampau']]);
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $galat = $this->lampau->periksa(pilihLampau(['reg-hana']));

        expect(barisLampau('reg-hana')->kunci_klaim)->toBeNull()
            ->and($galat[0]['pesan'])->toBe(MasalahKlaimLampau::KunciTidakLengkap->label());
    });

    it('menolak pilihan yang bukan milik prodi dan periode ini', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        $lain = Prodi::factory()->create(['kode_pddikti' => 'prodi-si-0002']);

        $galat = $this->lampau->periksa(KlaimLampauData::fromArray([
            'periode' => '20191',
            'baris' => [barisLampau('reg-hana')->uuid],
        ], $lain));

        expect($galat)->toHaveCount(1)
            ->and($this->fake->dipanggil('KlaimMahasiswaLampau'))->toBeFalse();
    });
});

describe('klaim', function () {
    it('hanya mengirim baris yang dipilih, dengan kunci dari daftar Feeder', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $hasil = $this->lampau->klaim(pilihLampau(['reg-hana', 'reg-indra']), $this->staf, $this->term);

        $kunci = collect($this->fake->riwayat)->where('act', 'KlaimMahasiswaLampau')->pluck('payload.key')->all();

        expect($hasil->berhasil())->toBeTrue()
            ->and($hasil->diklaim)->toBe(2)
            ->and($kunci)->toBe([['id_registrasi_mahasiswa' => 'reg-hana'], ['id_registrasi_mahasiswa' => 'reg-indra']])
            ->and(barisLampau('reg-oki')->status)->toBe(StatusLampauFeeder::Cocok);
    });

    it('mencatat setiap klaim di buku besar', function () {
        $m = angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $this->lampau->klaim(pilihLampau(['reg-hana']), $this->staf, $this->term);

        $log = FeederSyncLog::query()->entity(FeederKlaimLampau::ENTITY)->sole();

        expect($log)
            ->action->toBe('KlaimMahasiswaLampau')
            ->direction->toBe('claim')
            ->status->toBe(FeederSyncStatus::Success)
            ->feeder_id->toBe('reg-hana')
            ->local_id->toBe($m['hana']->id)
            ->attempts->toBe(1)
            ->and($log->payload)->toBe(['key' => ['id_registrasi_mahasiswa' => 'reg-hana']]);
    });

    it('mengalirkan yang diklaim ke adopsi #44: id Feeder terisi dan laporan adopsi mencatatnya', function () {
        $m = angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $hasil = $this->lampau->klaim(pilihLampau(['reg-hana', 'reg-oki']), $this->staf, $this->term);

        $adopsi = FeederAdoption::query()->where('mahasiswa_id', $m['hana']->id)->sole();

        expect($hasil->adopsi['diadopsi'])->toBe(2)
            ->and($hasil->belumTeradopsi)->toBe([])
            ->and($m['hana']->refresh())
            ->feeder_id->toBe('mhs-hana')
            ->feeder_registrasi_id->toBe('reg-hana')
            ->and($adopsi->status)->toBe(StatusAdopsiFeeder::Diadopsi)
            ->and($m['oki']->refresh()->feeder_registrasi_id)->toBe('reg-oki')
            ->and(FeederSyncLog::where('direction', 'adopt')->where('local_id', $m['hana']->id)->pluck('entity')->sort()->values()->all())
            ->toBe(['mahasiswa', 'riwayat_pendidikan']);
    });

    it('membuat dorongan berikutnya mencatat Skipped bagi yang diklaim', function () {
        $m = angkatanLampau();
        $angkatan = TahunAkademik::factory()->term(2019, SemesterType::Ganjil)->create();
        $this->lampau->baca($this->prodi, '20191');
        $this->lampau->klaim(pilihLampau(['reg-hana']), $this->staf, $this->term);
        $this->fake->riwayat = [];

        $sync = app(FeederSyncService::class);
        $sync->sinkronkan('mahasiswa', $angkatan, lewatiValidasi: true);
        $sync->sinkronkan('riwayat_pendidikan', $angkatan, lewatiValidasi: true);

        $nikTerkirim = collect($this->fake->riwayat)->where('act', 'InsertBiodataMahasiswa')->pluck('payload.record.nik');
        $dilewati = FeederSyncLog::query()
            ->where('status', FeederSyncStatus::Skipped->value)
            ->where('local_id', $m['hana']->id)
            ->pluck('entity');

        expect($nikTerkirim)->not->toContain('3201010101020001')
            ->and($dilewati->sort()->values()->all())->toBe(['mahasiswa', 'riwayat_pendidikan']);
    });

    it('mengklaim baris ambigu hanya dengan alasan, lalu menyerahkan keputusannya ke layar adopsi', function () {
        $m = angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $hasil = $this->lampau->klaim(pilihLampau(['reg-joko'], 'Dicocokkan dengan ijazah dan KTP pada arsip BAAK.'), $this->staf, $this->term);

        expect($hasil->berhasil())->toBeTrue()
            ->and($hasil->diklaim)->toBe(1)
            ->and($hasil->menungguKeputusan)->toBe(['2019003'])
            ->and(barisLampau('reg-joko'))
            ->status->toBe(StatusLampauFeeder::Diklaim)
            ->alasan->toBe(AlasanAmbiguAdopsi::NikBerbeda)
            ->catatan->toBe('Dicocokkan dengan ijazah dan KTP pada arsip BAAK.')
            ->diklaim_by_staff_id->toBe($this->staf->id)
            // Klaim bukan penautan: yang ragu tetap menunggu keputusan orang di #44.
            ->and($m['joko']->refresh()->feeder_id)->toBeNull()
            ->and(FeederAdoption::where('mahasiswa_id', $m['joko']->id)->sole()->status)->toBe(StatusAdopsiFeeder::Ambigu);
    });

    it('tetap mencatat klaim bila adopsi sesudahnya gagal', function () {
        $m = angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        $this->fake->tolak('GetListRiwayatPendidikanMahasiswa', 100, 'Aksi tidak dikenal');

        $hasil = $this->lampau->klaim(pilihLampau(['reg-hana']), $this->staf, $this->term);

        expect($hasil->diklaim)->toBe(1)
            ->and($hasil->adopsiGagal)->toContain('Aksi tidak dikenal')
            ->and($hasil->belumTeradopsi)->toBe(['2019001'])
            ->and(barisLampau('reg-hana')->status)->toBe(StatusLampauFeeder::Diklaim)
            ->and($m['hana']->refresh()->feeder_id)->toBeNull();
    });
});

describe('idempoten, penolakan, dan percobaan ulang', function () {
    it('tidak mengirim ulang klaim yang sudah berhasil', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        $data = pilihLampau(['reg-hana']);

        $this->lampau->klaim($data, $this->staf, $this->term);
        $kedua = $this->lampau->klaim($data, $this->staf, $this->term);

        expect($kedua->berhasil())->toBeTrue()
            ->and($kedua->diklaim)->toBe(0)
            ->and($kedua->dilewati)->toBe(1)
            ->and($this->fake->jumlahPanggilan('KlaimMahasiswaLampau'))->toBe(1)
            ->and(FeederSyncLog::query()->entity(FeederKlaimLampau::ENTITY)->count())->toBe(1);
    });

    it('tidak mengirim ulang meski laporannya dibaca ulang dan Feeder masih mencantumkannya', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        $this->lampau->klaim(pilihLampau(['reg-hana']), $this->staf, $this->term);

        // Seolah klaim belum terlihat di daftar lampau Feeder.
        $this->fake->mahasiswaLampau(fixtureLampau());
        $this->lampau->baca($this->prodi, '20191');

        $hasil = $this->lampau->klaim(pilihLampau(['reg-hana']), $this->staf, $this->term);

        expect($hasil->dilewati)->toBe(1)
            ->and($this->fake->jumlahPanggilan('KlaimMahasiswaLampau'))->toBe(1);
    });

    it('mencatat penolakan Feeder tanpa menghentikan baris lain', function () {
        $m = angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $hasil = $this->lampau->klaim(pilihLampau(['reg-nanda', 'reg-hana']), $this->staf, $this->term);

        $log = FeederSyncLog::query()->entity(FeederKlaimLampau::ENTITY)->where('feeder_id', 'reg-nanda')->sole();

        expect($hasil->diklaim)->toBe(1)
            ->and($hasil->ditolak)->toBe([['nim' => '2019007', 'pesan' => 'Mahasiswa sudah diklaim oleh perguruan tinggi lain']])
            ->and($log->status)->toBe(FeederSyncStatus::Failed)
            ->and($log->error_message)->toBe('Mahasiswa sudah diklaim oleh perguruan tinggi lain')
            ->and(barisLampau('reg-nanda')->status)->toBe(StatusLampauFeeder::Cocok)
            ->and($m['nanda']->refresh()->feeder_id)->toBeNull()
            ->and($m['hana']->refresh()->feeder_id)->toBe('mhs-hana');
    });

    it('mencoba lagi klaim yang ditolak, dan menghitung percobaannya', function () {
        $m = angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        $this->fake->tolakKlaim('reg-hana', 500, 'Layanan PDDIKTI sedang sibuk');
        $data = pilihLampau(['reg-hana']);

        $pertama = $this->lampau->klaim($data, $this->staf, $this->term);
        $kedua = $this->lampau->klaim($data, $this->staf, $this->term);

        $log = FeederSyncLog::query()->entity(FeederKlaimLampau::ENTITY)->orderBy('id')->get();

        expect($pertama->diklaim)->toBe(0)
            ->and($pertama->ditolak)->toHaveCount(1)
            ->and($pertama->adopsi)->toBeNull()
            ->and($kedua->diklaim)->toBe(1)
            ->and($log->pluck('status')->all())->toBe([FeederSyncStatus::Failed, FeederSyncStatus::Success])
            ->and($log->pluck('attempts')->all())->toBe([1, 2])
            ->and($m['hana']->refresh()->feeder_registrasi_id)->toBe('reg-hana');
    });

    it('menampilkan percobaan terakhir dan jumlahnya per baris', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        $data = pilihLampau(['reg-nanda']);

        $this->lampau->klaim($data, $this->staf, $this->term);
        $this->lampau->klaim($data, $this->staf, $this->term);

        $percobaan = $this->lampau->percobaanTerakhir($this->lampau->laporan($this->prodi, '20191'));

        expect($percobaan->keys()->all())->toBe(['reg-nanda'])
            ->and($percobaan['reg-nanda']['percobaan'])->toBe(2)
            ->and($percobaan['reg-nanda']['log']->status)->toBe(FeederSyncStatus::Failed)
            ->and($this->lampau->percobaanTerakhir(collect()))->toBeEmpty();
    });
});

describe('validator pra-kirim biodata', function () {
    it('menolak biodata mahasiswa yang sudah diklaim tetapi belum diadopsi', function () {
        $m = angkatanLampau();
        $angkatan = TahunAkademik::factory()->term(2019, SemesterType::Ganjil)->create();
        $this->lampau->baca($this->prodi, '20191');
        $this->fake->tolak('GetListRiwayatPendidikanMahasiswa', 100, 'Aksi tidak dikenal');
        $this->lampau->klaim(pilihLampau(['reg-hana']), $this->staf, $this->term);

        expect(fn () => app(FeederSyncService::class)->sinkronkan('mahasiswa', $angkatan))->toThrow(FeederException::class);

        expect(FeederValidationIssue::where('rule', 'klaim_lampau_belum_diadopsi')->pluck('local_id')->all())->toBe([$m['hana']->id])
            ->and($this->fake->dipanggil('InsertBiodataMahasiswa'))->toBeFalse();
    });

    it('melepaskan biodata yang sesudah diklaim dinyatakan bukan orang yang sama', function () {
        $m = angkatanLampau();
        $angkatan = TahunAkademik::factory()->term(2019, SemesterType::Ganjil)->create();
        $this->lampau->baca($this->prodi, '20191');
        $this->lampau->klaim(pilihLampau(['reg-joko'], 'Dicocokkan dengan ijazah dan KTP pada arsip BAAK.'), $this->staf, $this->term);

        app(FeederAdopsi::class)->pisahkan(
            FeederAdoption::where('mahasiswa_id', $m['joko']->id)->sole(),
            'NIM dipakai ulang oleh SIAKAD lama untuk orang lain.',
            $this->staf,
        );

        app(FeederSyncService::class)->sinkronkan('mahasiswa', $angkatan);

        expect(FeederValidationIssue::whereIn('rule', ['klaim_lampau_belum_diadopsi', 'kandidat_feeder_belum_diadopsi'])->exists())->toBeFalse()
            ->and(collect($this->fake->riwayat)->where('act', 'InsertBiodataMahasiswa')->pluck('payload.record.nik'))->toContain('3201010101020003');
    });
});

describe('layar', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);

        $this->operator = Staff::factory()->create();
        $this->operator->assignRole('operator-pddikti');
    });

    it('membaca daftar dari layar dan menampilkannya tanpa NIK', function () {
        angkatanLampau();

        $this->actingAs($this->operator, 'staff')
            ->post(route('admin.feeder.lampau.baca'), ['prodi' => $this->prodi->uuid, 'periode' => '20191'])
            ->assertRedirect(route('admin.feeder.lampau', ['prodi' => $this->prodi->uuid, 'periode' => '20191']))
            ->assertSessionHas('sukses');

        $this->actingAs($this->operator, 'staff')
            ->get(route('admin.feeder.lampau', ['prodi' => $this->prodi->uuid, 'periode' => '20191']))
            ->assertOk()
            ->assertSee(__('feeder.lampau.status.cocok'))
            ->assertSee(__('feeder.lampau.status.tanpa_lokal'))
            ->assertSee(__('feeder.adopsi.alasan.nik_berbeda'))
            ->assertSee(barisLampau('reg-hana')->uuid)
            ->assertDontSee(barisLampau('reg-maya')->uuid)
            ->assertDontSee('3201010101020001');
    });

    it('mengklaim dari layar dan mencatat pelaku di log aktivitas', function () {
        $m = angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $this->actingAs($this->operator, 'staff')
            ->post(route('admin.feeder.lampau.klaim'), [
                'prodi' => $this->prodi->uuid,
                'periode' => '20191',
                'baris' => [barisLampau('reg-hana')->uuid],
            ])
            ->assertSessionHas('sukses');

        $log = LogAktivitas::query()
            ->where('subject_type', (new FeederPastStudent)->getMorphClass())
            ->where('subject_id', barisLampau('reg-hana')->id)
            ->where('event', 'updated')
            ->sole();

        expect($m['hana']->refresh()->feeder_registrasi_id)->toBe('reg-hana')
            ->and($log->causer_id)->toBe($this->operator->id);

        $this->actingAs($this->operator, 'staff')
            ->get(route('admin.feeder.lampau', ['prodi' => $this->prodi->uuid, 'periode' => '20191']))
            ->assertOk()
            ->assertSee(__('feeder.lampau.tertaut'));
    });

    it('mengembalikan seluruh galat pra-kirim ke layar tanpa mengirim apa pun', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $this->actingAs($this->operator, 'staff')
            ->post(route('admin.feeder.lampau.klaim'), [
                'prodi' => $this->prodi->uuid,
                'periode' => '20191',
                'baris' => [barisLampau('reg-hana')->uuid, barisLampau('reg-joko')->uuid, barisLampau('reg-maya')->uuid],
            ])
            ->assertSessionHas('galat')
            ->assertSessionHas('galat_klaim', fn (array $g): bool => count($g) === 2);

        expect($this->fake->dipanggil('KlaimMahasiswaLampau'))->toBeFalse();
    });

    it('menolak alasan yang terlalu pendek dan pilihan kosong', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');

        $this->actingAs($this->operator, 'staff')
            ->post(route('admin.feeder.lampau.klaim'), [
                'prodi' => $this->prodi->uuid,
                'periode' => '20191',
                'baris' => [],
                'catatan' => 'ok',
            ])
            ->assertSessionHasErrors(['baris', 'catatan']);
    });

    it('menolak staf tanpa izin sinkron', function () {
        angkatanLampau();
        $this->lampau->baca($this->prodi, '20191');
        $tamu = Staff::factory()->create();

        $this->actingAs($tamu, 'staff')
            ->post(route('admin.feeder.lampau.baca'), ['prodi' => $this->prodi->uuid, 'periode' => '20191'])
            ->assertForbidden();

        $this->actingAs($tamu, 'staff')
            ->post(route('admin.feeder.lampau.klaim'), [
                'prodi' => $this->prodi->uuid,
                'periode' => '20191',
                'baris' => [barisLampau('reg-hana')->uuid],
            ])
            ->assertForbidden();

        expect($this->fake->dipanggil('KlaimMahasiswaLampau'))->toBeFalse();
    });
});
