<?php

declare(strict_types=1);

use App\Enums\AlasanAmbiguAdopsi;
use App\Enums\FeederSyncStatus;
use App\Enums\SemesterType;
use App\Enums\StatusAdopsiFeeder;
use App\Exceptions\FeederException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederAdoption;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederAdopsi;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\Mappers\MahasiswaMapper;
use Database\Seeders\RolePermissionSeeder;

/**
 * Adopsi mahasiswa yang sudah ada di PDDIKTI.
 *
 * Kampus yang pindah dari SIAKAD lain sudah punya seluruh mahasiswanya di
 * PDDIKTI. Tanpa adopsi, sinkronisasi pertama mencoba mendaftarkan mereka
 * ulang. Yang diuji di sini terutama dua hal: adopsi tidak pernah mengirim
 * apa pun, dan tidak pernah memutuskan sendiri kasus yang ragu.
 *
 * Kasusnya ada di tests/Fixtures/feeder/mahasiswa-terdaftar.json — satu
 * orang per kasus, ditambah dua baris prodi/periode lain yang tidak boleh
 * terbaca sama sekali.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);
    $this->fake->mahasiswaTerdaftar(fixtureAdopsi());

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'prodi-ti-0001']);

    $this->adopsi = app(FeederAdopsi::class);
});

/** @return array<int, array<string, mixed>> */
function fixtureAdopsi(): array
{
    $isi = json_decode((string) file_get_contents(base_path('tests/Fixtures/feeder/mahasiswa-terdaftar.json')), true, flags: JSON_THROW_ON_ERROR);

    return $isi['orang'];
}

function lokalAdopsi(string $nim, ?string $nik, string $nama, array $atribut = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'term_masuk' => '20241',
        'nim' => $nim,
        'nik' => $nik,
        'nama' => $nama,
    ], $atribut));
}

/** Satu mahasiswa lokal untuk setiap kasus di fixture. */
function angkatanAdopsi(): array
{
    return [
        'ayu' => lokalAdopsi('2024001', '3201010101010001', 'Ayu Lestari'),
        'bagus' => lokalAdopsi('2024002', '3201010101010002', 'Bagus Pratama'),
        'citra' => lokalAdopsi('2024003', '3201010101019993', 'Citra Dewi'),
        'dimas' => lokalAdopsi('2024004', '3201010101010004', 'Dimas Saputra'),
        'eka' => lokalAdopsi('2024005', '3201010101010005', 'Eka Putri'),
        // NIM-nya ada di Feeder, tetapi di prodi lain: penyaring harus menahannya.
        'gilang' => lokalAdopsi('2024007', '3201010101019997', 'Gilang Lokal'),
    ];
}

function temuanUntuk(Mahasiswa $m): FeederAdoption
{
    return FeederAdoption::query()->where('mahasiswa_id', $m->id)->latest('id')->firstOrFail();
}

describe('empat keadaan', function () {
    it('memilah angkatan menjadi cocok, hanya di Feeder, hanya lokal, dan ambigu', function () {
        $m = angkatanAdopsi();

        $hasil = $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect($hasil)->toMatchArray([
            'lokal' => 6,
            'feeder' => 7,
            'diadopsi' => 2,
            'sudah_tertaut' => 0,
            'hanya_lokal' => 1,
            'hanya_feeder' => 1,
            'ambigu' => 3,
        ])
            ->and(temuanUntuk($m['ayu'])->status)->toBe(StatusAdopsiFeeder::Diadopsi)
            ->and(temuanUntuk($m['gilang'])->status)->toBe(StatusAdopsiFeeder::HanyaLokal)
            ->and(FeederAdoption::status(StatusAdopsiFeeder::HanyaFeeder)->sole()->nim_feeder)->toBe('2024006');
    });

    it('mengisi id Feeder mahasiswa dan id registrasinya bagi yang cocok', function () {
        $m = angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect($m['ayu']->refresh())
            ->feeder_id->toBe('mhs-ayu')
            ->feeder_registrasi_id->toBe('reg-ayu');
    });

    it('membiarkan NIK mengalahkan nama yang salah ketik', function () {
        $m = angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect(temuanUntuk($m['bagus'])->status)->toBe(StatusAdopsiFeeder::Diadopsi)
            ->and(temuanUntuk($m['bagus'])->nama_feeder)->toBe('Bagus Pratomo')
            ->and($m['bagus']->refresh()->feeder_registrasi_id)->toBe('reg-bagus');
    });

    it('menandai ambigu, bukan mengadopsi, bila NIM cocok tetapi NIK berbeda', function () {
        $m = angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect(temuanUntuk($m['citra']))
            ->status->toBe(StatusAdopsiFeeder::Ambigu)
            ->alasan->toBe(AlasanAmbiguAdopsi::NikBerbeda)
            ->and($m['citra']->refresh()->feeder_id)->toBeNull();
    });

    it('menandai ambigu bila ada dua kandidat', function () {
        $m = angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        $temuan = temuanUntuk($m['dimas']);

        expect($temuan->alasan)->toBe(AlasanAmbiguAdopsi::KandidatGanda)
            ->and(collect($temuan->kandidat)->pluck('id_registrasi_mahasiswa')->sort()->values()->all())
            ->toBe(['reg-dimas-a', 'reg-dimas-b'])
            ->and($m['dimas']->refresh()->feeder_id)->toBeNull();
    });

    it('menandai ambigu bila hanya NIK yang cocok dan Feeder memakai NIM lain', function () {
        $m = angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect(temuanUntuk($m['eka']))
            ->status->toBe(StatusAdopsiFeeder::Ambigu)
            ->alasan->toBe(AlasanAmbiguAdopsi::NimBerbeda);
    });

    it('memakai nama sebagai pengganti NIK yang kosong, dan ragu bila namanya berbeda', function () {
        $sama = lokalAdopsi('2024001', null, 'AYU LESTARI');
        $beda = lokalAdopsi('2024006', null, 'Fajri Nugraha');

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect(temuanUntuk($sama)->status)->toBe(StatusAdopsiFeeder::Diadopsi)
            ->and(temuanUntuk($beda)->alasan)->toBe(AlasanAmbiguAdopsi::NamaBerbeda);
    });

    it('menolak mengadopsi mahasiswa yang sudah tertaut ke data Feeder lain', function () {
        $m = lokalAdopsi('2024001', '3201010101010001', 'Ayu Lestari', ['feeder_id' => 'mhs-entah']);

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect(temuanUntuk($m)->alasan)->toBe(AlasanAmbiguAdopsi::TertautLain)
            ->and($m->refresh()->feeder_id)->toBe('mhs-entah');
    });

    it('tidak menyimpan NIK pada laporan', function () {
        angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        $semua = FeederAdoption::all()->toJson();

        expect($semua)->not->toContain('3201010101010001')
            ->and($semua)->not->toContain('3201010101010003');
    });

    it('membaca hanya prodi dan periode yang diminta', function () {
        angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        $nim = FeederAdoption::all()->flatMap(fn (FeederAdoption $a) => collect($a->kandidat)->pluck('nim'));

        expect($nim)->not->toContain('2023008')
            ->and(temuanUntuk(Mahasiswa::where('nim', '2024007')->first())->status)->toBe(StatusAdopsiFeeder::HanyaLokal);
    });
});

describe('tanpa mengirim apa pun', function () {
    it('hanya membaca dari Feeder', function () {
        angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        $aksi = collect($this->fake->riwayat)->pluck('act')->unique()->sort()->values()->all();

        expect($aksi)->toBe(['GetListMahasiswa', 'GetListRiwayatPendidikanMahasiswa']);
    });

    it('mencatat buku besar dengan hash muatan lokal untuk biodata dan riwayat', function () {
        $m = angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        $log = FeederSyncLog::query()
            ->where('local_id', $m['ayu']->id)
            ->where('local_type', $m['ayu']->getMorphClass())
            ->get()
            ->keyBy('entity');

        $ayu = $m['ayu']->refresh()->load('prodi');

        expect($log->keys()->sort()->values()->all())->toBe(['mahasiswa', 'riwayat_pendidikan'])
            ->and($log['mahasiswa']->direction)->toBe('adopt')
            ->and($log['mahasiswa']->status)->toBe(FeederSyncStatus::Success)
            ->and($log['mahasiswa']->feeder_id)->toBe('mhs-ayu')
            ->and($log['mahasiswa']->payload_hash)->toBe(FeederSyncLog::hashPayload(app(MahasiswaMapper::class)->payload($ayu)))
            ->and($log['riwayat_pendidikan']->feeder_id)->toBe('reg-ayu')
            ->and(FeederSyncLog::where('entity', 'mahasiswa')->count())->toBe(2);
    });

    it('membuat dorongan berikutnya mencatat Skipped, bukan Insert, bagi yang teradopsi', function () {
        $m = angkatanAdopsi();
        $angkatan = TahunAkademik::factory()->term(2024, SemesterType::Ganjil)->create();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
        $this->fake->riwayat = [];

        $sync = app(FeederSyncService::class);
        $sync->sinkronkan('mahasiswa', $angkatan, lewatiValidasi: true);
        $sync->sinkronkan('riwayat_pendidikan', $angkatan, lewatiValidasi: true);

        $nikTerkirim = collect($this->fake->riwayat)
            ->where('act', 'InsertBiodataMahasiswa')
            ->pluck('payload.record.nik');

        $dilewati = FeederSyncLog::query()
            ->where('status', FeederSyncStatus::Skipped->value)
            ->whereIn('local_id', [$m['ayu']->id, $m['bagus']->id])
            ->pluck('entity');

        expect($nikTerkirim)->not->toContain('3201010101010001')
            ->and($nikTerkirim)->not->toContain('3201010101010002')
            ->and($this->fake->jumlahPanggilan('UpdateBiodataMahasiswa'))->toBe(0)
            ->and($dilewati->sort()->values()->all())->toBe(['mahasiswa', 'mahasiswa', 'riwayat_pendidikan', 'riwayat_pendidikan'])
            ->and(collect($this->fake->riwayat)->where('act', 'InsertRiwayatPendidikanMahasiswa')->pluck('payload.record.nim'))
            ->not->toContain('2024001');
    });

    it('tidak menimpa hash dorongan yang sudah pernah berhasil', function () {
        // Mahasiswa yang dulu terkirim dari sini lalu berubah: buku besar
        // memegang hash versi lama, dan itulah yang benar-benar diterima.
        $m = lokalAdopsi('2024001', '3201010101010001', 'Ayu Lestari', ['feeder_id' => 'mhs-ayu', 'feeder_registrasi_id' => 'reg-ayu']);

        foreach (['mahasiswa', 'riwayat_pendidikan'] as $entity) {
            FeederSyncLog::create([
                'entity' => $entity, 'action' => 'Insert', 'direction' => 'push',
                'local_type' => $m->getMorphClass(), 'local_id' => $m->id, 'feeder_id' => 'x',
                'tahun_akademik_id' => $this->term->id, 'payload_hash' => str_repeat('a', 64),
                'status' => FeederSyncStatus::Success, 'attempts' => 1,
            ]);
        }

        $hasil = $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect($hasil['sudah_tertaut'])->toBe(1)
            ->and(FeederSyncLog::count())->toBe(2)
            ->and(FeederSyncLog::where('direction', 'adopt')->exists())->toBeFalse();
    });
});

describe('aman diulang', function () {
    it('tidak menggandakan buku besar maupun laporan saat dijalankan dua kali', function () {
        angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
        $log = FeederSyncLog::count();
        $laporan = FeederAdoption::count();

        $kedua = $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect(FeederSyncLog::count())->toBe($log)
            ->and(FeederAdoption::count())->toBe($laporan)
            ->and($kedua['diadopsi'])->toBe(0)
            ->and($kedua['sudah_tertaut'])->toBe(2)
            ->and($kedua['ambigu'])->toBe(3);
    });

    it('membiarkan keputusan manual berdiri saat adopsi diulang', function () {
        $m = angkatanAdopsi();
        $staf = Staff::factory()->create();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
        $this->adopsi->tautkan(temuanUntuk($m['dimas']), 'reg-dimas-b', 'Dicocokkan dengan KTP pada berkas PMB.', $staf, $this->term);
        $this->adopsi->pisahkan(temuanUntuk($m['citra']), 'NIM dipakai ulang oleh SIAKAD lama untuk orang lain.', $staf);

        $kedua = $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect($kedua['ambigu'])->toBe(1)
            ->and($kedua['diputuskan'])->toBe(2)
            ->and(temuanUntuk($m['dimas'])->status)->toBe(StatusAdopsiFeeder::Ditautkan)
            ->and(temuanUntuk($m['citra'])->status)->toBe(StatusAdopsiFeeder::Dipisahkan)
            // Registrasi yang sudah ditautkan bukan "hanya di Feeder"; kembarannya
            // yang tidak dipilih memang hanya ada di sana, dan dilaporkan begitu.
            ->and(FeederAdoption::status(StatusAdopsiFeeder::HanyaFeeder)->orderBy('feeder_registrasi_id')->pluck('feeder_registrasi_id')->all())
            ->toBe(['reg-dimas-a', 'reg-fajar']);
    });
});

describe('keputusan manual', function () {
    it('menautkan mahasiswa ambigu ke kandidat pilihan dengan alasan, tanpa mengirim apa pun', function () {
        $m = angkatanAdopsi();
        $staf = Staff::factory()->create();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
        $this->fake->riwayat = [];

        $temuan = $this->adopsi->tautkan(temuanUntuk($m['dimas']), 'reg-dimas-a', 'Dicocokkan dengan KTP pada berkas PMB.', $staf, $this->term);

        expect($temuan->refresh())
            ->status->toBe(StatusAdopsiFeeder::Ditautkan)
            ->catatan->toBe('Dicocokkan dengan KTP pada berkas PMB.')
            ->diputuskan_by_staff_id->toBe($staf->id)
            ->and($m['dimas']->refresh()->feeder_registrasi_id)->toBe('reg-dimas-a')
            ->and($this->fake->riwayat)->toBe([])
            ->and(FeederSyncLog::where('local_id', $m['dimas']->id)->where('direction', 'adopt')->count())->toBe(2);
    });

    it('mencatat penautan manual di log aktivitas', function () {
        $m = angkatanAdopsi();
        $staf = Staff::factory()->create();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
        $this->actingAs($staf, 'staff');
        $this->adopsi->tautkan(temuanUntuk($m['eka']), 'reg-eka', 'NIM lama dari SIAKAD sebelumnya, NIK sama.', $staf, $this->term);

        $log = temuanUntuk($m['eka'])->logAktivitas()->where('event', 'updated')->first();

        expect($log)->not->toBeNull()
            ->and($log->causer_id)->toBe($staf->id)
            ->and(json_encode($log->changes))->toContain('ditautkan');
    });

    it('menolak kandidat yang bukan milik temuan itu', function () {
        $m = angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        $this->adopsi->tautkan(temuanUntuk($m['dimas']), 'reg-fajar', 'Alasan yang cukup panjang.', Staff::factory()->create(), $this->term);
    })->throws(FeederException::class);

    it('menolak registrasi yang sudah dipegang mahasiswa lain', function () {
        // Eka dan Ayu: kandidat Eka ditautkan dulu ke registrasi Ayu lewat
        // data yang dipalsukan, untuk memastikan satu registrasi satu orang.
        $m = angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
        $temuan = temuanUntuk($m['eka']);
        $temuan->update(['kandidat' => [['id_mahasiswa' => 'mhs-ayu', 'id_registrasi_mahasiswa' => 'reg-ayu', 'nim' => '2024001', 'nama' => 'Ayu Lestari', 'tanggal_lahir' => null, 'nik_cocok' => null]]]);

        $this->adopsi->tautkan($temuan, 'reg-ayu', 'Alasan yang cukup panjang.', Staff::factory()->create(), $this->term);
    })->throws(FeederException::class, '2024001');

    it('hanya menerima keputusan untuk temuan yang ambigu', function () {
        $m = angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        $this->adopsi->pisahkan(temuanUntuk($m['ayu']), 'Alasan yang cukup panjang.', Staff::factory()->create());
    })->throws(FeederException::class);

    it('menyajikan laporan dengan yang ambigu di atas', function () {
        angkatanAdopsi();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        $laporan = $this->adopsi->laporan($this->prodi, '20241');

        expect($laporan)->toHaveCount(7)
            ->and($laporan->first()->status)->toBe(StatusAdopsiFeeder::Ambigu)
            ->and($laporan->last()->status)->toBe(StatusAdopsiFeeder::Diadopsi);
    });
});

describe('validator pra-kirim', function () {
    it('menolak biodata mahasiswa yang punya kandidat di Feeder tetapi belum diputuskan', function () {
        $m = angkatanAdopsi();
        $angkatan = TahunAkademik::factory()->term(2024, SemesterType::Ganjil)->create();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect(fn () => app(FeederSyncService::class)->sinkronkan('mahasiswa', $angkatan))
            ->toThrow(FeederException::class);

        $ditolak = FeederValidationIssue::query()->where('rule', 'kandidat_feeder_belum_diadopsi')->pluck('local_id');

        expect($ditolak->sort()->values()->all())->toBe(collect([$m['citra'], $m['dimas'], $m['eka']])->pluck('id')->sort()->values()->all())
            ->and(FeederValidationIssue::where('rule', 'kandidat_feeder_belum_diadopsi')->first()->message)->toContain('adopsi')
            ->and($this->fake->dipanggil('InsertBiodataMahasiswa'))->toBeFalse();
    });

    it('melepaskan biodata yang dinyatakan bukan orang yang sama', function () {
        $m = angkatanAdopsi();
        $angkatan = TahunAkademik::factory()->term(2024, SemesterType::Ganjil)->create();
        $staf = Staff::factory()->create();

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
        $this->adopsi->pisahkan(temuanUntuk($m['citra']), 'NIM dipakai ulang oleh SIAKAD lama untuk orang lain.', $staf);
        $this->adopsi->tautkan(temuanUntuk($m['dimas']), 'reg-dimas-a', 'Dicocokkan dengan KTP pada berkas PMB.', $staf, $this->term);
        $this->adopsi->tautkan(temuanUntuk($m['eka']), 'reg-eka', 'NIM lama dari SIAKAD sebelumnya, NIK sama.', $staf, $this->term);

        $hasil = app(FeederSyncService::class)->sinkronkan('mahasiswa', $angkatan);

        $nikTerkirim = collect($this->fake->riwayat)->where('act', 'InsertBiodataMahasiswa')->pluck('payload.record.nik');

        expect($nikTerkirim->sort()->values()->all())->toBe(['3201010101019993', '3201010101019997'])
            ->and($hasil['dilewati'])->toBe(4);
    });
});

describe('Feeder dan konfigurasi', function () {
    it('berhenti bila Feeder menolak pembacaan, bukan menyimpulkan semua mahasiswa baru', function () {
        angkatanAdopsi();
        $this->fake->tolak('GetListRiwayatPendidikanMahasiswa', 100, 'Aksi tidak dikenal');

        expect(fn () => $this->adopsi->adopsi($this->prodi, '20241', $this->term))->toThrow(FeederException::class)
            ->and(FeederAdoption::count())->toBe(0);
    });

    it('menolak prodi tanpa kode PDDIKTI', function () {
        $this->adopsi->adopsi(Prodi::factory()->create(['kode_pddikti' => null]), '20241', $this->term);
    })->throws(FeederException::class, 'id_sms');

    it('menolak berjalan saat integrasi dimatikan', function () {
        config(['feeder.enabled' => false]);

        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
    })->throws(FeederException::class);

    it('membaca seluruh halaman, bukan hanya yang pertama', function () {
        config(['feeder.reconcile_page_size' => 2]);
        $m = angkatanAdopsi();

        $hasil = $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        expect($hasil['feeder'])->toBe(7)
            ->and($m['ayu']->refresh()->feeder_id)->toBe('mhs-ayu');
    });
});

describe('layar dan perintah', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);

        $this->operator = Staff::factory()->create();
        $this->operator->assignRole('operator-pddikti');
    });

    it('menjalankan adopsi dari layar dan menampilkan keempat keadaan', function () {
        angkatanAdopsi();

        $this->actingAs($this->operator, 'staff')
            ->post('/admin/feeder/adopsi', ['prodi' => $this->prodi->uuid, 'periode' => '20241'])
            ->assertRedirect(route('admin.feeder.adopsi', ['prodi' => $this->prodi->uuid, 'periode' => '20241']))
            ->assertSessionHas('peringatan');

        $this->actingAs($this->operator, 'staff')
            ->get(route('admin.feeder.adopsi', ['prodi' => $this->prodi->uuid, 'periode' => '20241']))
            ->assertOk()
            ->assertSee(__('feeder.adopsi.status.diadopsi'))
            ->assertSee(__('feeder.adopsi.status.hanya_feeder'))
            ->assertSee(__('feeder.adopsi.status.hanya_lokal'))
            ->assertSee(__('feeder.adopsi.alasan.nik_berbeda'))
            ->assertDontSee('3201010101010001');
    });

    it('menautkan dari layar dengan alasan wajib', function () {
        $m = angkatanAdopsi();
        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
        $temuan = temuanUntuk($m['dimas']);

        $this->actingAs($this->operator, 'staff')
            ->post(route('admin.feeder.adopsi.tautkan', $temuan), ['id_registrasi_mahasiswa' => 'reg-dimas-a', 'catatan' => 'ok'])
            ->assertSessionHasErrors('catatan');

        $this->actingAs($this->operator, 'staff')
            ->post(route('admin.feeder.adopsi.tautkan', $temuan), ['id_registrasi_mahasiswa' => 'reg-dimas-a', 'catatan' => 'Dicocokkan dengan KTP pada berkas PMB.'])
            ->assertSessionHas('sukses');

        expect($m['dimas']->refresh()->feeder_registrasi_id)->toBe('reg-dimas-a');
    });

    it('memisahkan dari layar', function () {
        $m = angkatanAdopsi();
        $this->adopsi->adopsi($this->prodi, '20241', $this->term);

        $this->actingAs($this->operator, 'staff')
            ->post(route('admin.feeder.adopsi.pisahkan', temuanUntuk($m['citra'])), ['catatan' => 'NIM dipakai ulang untuk orang lain.'])
            ->assertSessionHas('sukses');

        expect(temuanUntuk($m['citra'])->status)->toBe(StatusAdopsiFeeder::Dipisahkan);
    });

    it('menolak staf tanpa izin sinkron', function () {
        $m = angkatanAdopsi();
        $this->adopsi->adopsi($this->prodi, '20241', $this->term);
        $tamu = Staff::factory()->create();

        $this->actingAs($tamu, 'staff')
            ->post('/admin/feeder/adopsi', ['prodi' => $this->prodi->uuid, 'periode' => '20241'])
            ->assertForbidden();

        $this->actingAs($tamu, 'staff')
            ->post(route('admin.feeder.adopsi.pisahkan', temuanUntuk($m['citra'])), ['catatan' => 'NIM dipakai ulang untuk orang lain.'])
            ->assertForbidden();
    });

    it('menjalankan adopsi lewat artisan untuk setiap periode masuk prodi', function () {
        $m = angkatanAdopsi();

        $this->artisan('openacademic:feeder-adopsi', ['prodi' => $this->prodi->kode])
            ->expectsOutputToContain('Ambigu')
            ->assertSuccessful();

        expect($m['ayu']->refresh()->feeder_id)->toBe('mhs-ayu');
    });
});
