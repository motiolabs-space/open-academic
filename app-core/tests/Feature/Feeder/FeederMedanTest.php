<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Models\Kemahasiswaan\Yudisium;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use App\Services\Feeder\Mappers\LulusDoMapper;
use App\Services\Feeder\Mappers\MahasiswaMapper;
use App\Services\Feeder\PemeriksaKamusFeeder;
use Database\Seeders\Demo\IntegrasiSeeder;

/*
 * Field names a campus sets from its own Feeder's dictionary (ADR-042 §2):
 * the mobile number on the biodata (#52) and the diploma date on Lulus/DO
 * (#53) — payload snapshots before and after, what a rename does to the
 * ledger, and the config the dictionary check suggests.
 */

beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);
    $this->feeder = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->feeder);
    $this->seed(IntegrasiSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => 'IF', 'kode_pddikti' => 'a1b2c3d4']);
});

function mhsMedan(array $atribut = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'nim' => '2026100001',
        'nama' => 'Rani Puspita',
        'prodi_id' => test()->prodi->id,
        'nik' => '9901010101010001',
        'nisn' => null,
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2004-05-12',
        'jenis_kelamin' => Gender::Perempuan,
        'agama_kode' => '1',
        'alamat' => null,
        'kelurahan' => null,
        'kode_pos' => null,
        'nama_ayah' => null,
        'nama_ibu' => 'Sri Wahyuni',
        'term_masuk' => test()->term->kode,
        'telepon' => '081234567890',
        'email' => 'rani@contoh.test',
    ], $atribut));
}

/** A graduate whose exit is due this term: yudisium confirmed, one AKM row. */
function lulusanMedan(array $yudisium = []): Mahasiswa
{
    $m = mhsMedan(['status' => StudentStatus::Lulus, 'feeder_id' => 'mhs-1', 'feeder_registrasi_id' => 'reg-1']);

    Yudisium::create(array_merge([
        'mahasiswa_id' => $m->id, 'tahun_akademik_id' => test()->term->id, 'total_sks' => 144, 'ipk' => 3.5,
        'status' => 'ditetapkan', 'tanggal_lulus' => '2026-10-01', 'tanggal_yudisium' => '2026-10-05', 'nomor_sk' => 'SK/1',
    ], $yudisium));
    StatusMahasiswa::create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => test()->term->id, 'status' => 'A', 'semester_ke' => 8]);

    return app(LulusDoMapper::class)->rows(test()->term->kode)->firstWhere('id', $m->id);
}

describe('nomor HP pada biodata (#52)', function () {
    it('mengirim telepon_seluler secara bawaan', function () {
        expect(app(MahasiswaMapper::class)->payload(mhsMedan()))->toBe([
            'nama_mahasiswa' => 'Rani Puspita',
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2004-05-12',
            'id_agama' => '1',
            'nik' => '9901010101010001',
            'kewarganegaraan' => 'ID',
            'telepon_seluler' => '081234567890',
            'email' => 'rani@contoh.test',
            'nama_ibu_kandung' => 'Sri Wahyuni',
        ]);
    });

    it('mengirim nomor yang sama di bawah nama yang diatur, dan hanya di sana', function () {
        $m = mhsMedan();
        $sebelum = app(MahasiswaMapper::class)->payload($m);

        config(['feeder.medan.telepon_seluler' => 'nomor_hp']);
        $sesudah = app(MahasiswaMapper::class)->payload($m);

        expect($sesudah)->toHaveKey('nomor_hp', '081234567890')
            ->not->toHaveKey('telepon_seluler')
            ->and(array_diff_key($sesudah, ['nomor_hp' => 1]))->toBe(array_diff_key($sebelum, ['telepon_seluler' => 1]))
            ->and(app(MahasiswaMapper::class)->medan())->toContain('nomor_hp')->not->toContain('telepon_seluler')
            ->and(FeederSyncLog::hashPayload($sesudah))->not->toBe(FeederSyncLog::hashPayload($sebelum));
    });

    it('mengabaikan nilai config yang bukan nama medan', function () {
        config(['feeder.medan.telepon_seluler' => 'nomor hp; DROP']);

        expect(app(MahasiswaMapper::class)->payload(mhsMedan()))->toHaveKey('telepon_seluler');
    });
});

describe('tanggal ijazah pada Lulus/DO (#53)', function () {
    it('tidak mengirim tanggal ijazah selama nama medannya belum diatur', function () {
        expect(app(LulusDoMapper::class)->payload(lulusanMedan()))->toBe([
            'id_registrasi_mahasiswa' => 'reg-1',
            'id_jenis_keluar' => '1',
            'tanggal_keluar' => '2026-10-01',
            'id_periode_keluar' => '20261',
            'nomor_sk_yudisium' => 'SK/1',
            'tanggal_sk_yudisium' => '2026-10-05',
            'ipk' => '3.50',
        ])->and(app(LulusDoMapper::class)->medan())->not->toContain('tanggal_terbit_ijazah');
    });

    it('mengirim tanggal lulus di bawah nama yang diatur, atau tanggal SK bila sumbernya yudisium', function () {
        $m = lulusanMedan();
        config(['feeder.medan.tanggal_ijazah' => 'tanggal_terbit_ijazah']);

        expect(app(LulusDoMapper::class)->payload($m))->toHaveKey('tanggal_terbit_ijazah', '2026-10-01')
            ->and(app(LulusDoMapper::class)->medan())->toContain('tanggal_terbit_ijazah');

        config(['feeder.medan.tanggal_ijazah_sumber' => 'yudisium']);

        expect(app(LulusDoMapper::class)->payload($m))->toHaveKey('tanggal_terbit_ijazah', '2026-10-05');
    });

    it('mengambil tanggal wisuda bila sumbernya wisuda, dan memperingatkan lulusan yang belum terdaftar wisuda', function () {
        config(['feeder.medan.tanggal_ijazah' => 'tanggal_terbit_ijazah', 'feeder.medan.tanggal_ijazah_sumber' => 'wisuda']);
        $m = lulusanMedan();

        expect(app(LulusDoMapper::class)->payload($m))->not->toHaveKey('tanggal_terbit_ijazah')
            ->and(app(FeederValidator::class)->isuUntuk('lulus_do', $m)->firstWhere('rule', 'tanggal_ijazah_kosong')['severity'])->toBe('warning');

        $periode = WisudaPeriode::create(['nama' => 'Wisuda Desember 2026', 'tanggal' => '2026-12-12']);
        WisudaPeserta::create(['wisuda_periode_id' => $periode->id, 'yudisium_id' => $m->yudisium->id]);
        $m = app(LulusDoMapper::class)->rows($this->term->kode)->firstWhere('id', $m->id);

        expect(app(LulusDoMapper::class)->payload($m))->toHaveKey('tanggal_terbit_ijazah', '2026-12-12')
            ->and(app(FeederValidator::class)->isuUntuk('lulus_do', $m)->pluck('rule'))->not->toContain('tanggal_ijazah_kosong');
    });
});

describe('idempotensi sesudah nama medan berubah', function () {
    it('mengirim ulang biodata yang sudah diterima sebagai pembaruan, sekali, lalu melewatinya lagi', function () {
        $m = mhsMedan();
        $sync = app(FeederSyncService::class);

        $sync->sinkronkan('mahasiswa', $this->term);
        $sync->sinkronkan('mahasiswa', $this->term);
        $idMahasiswa = $m->fresh()->feeder_id;

        config(['feeder.medan.telepon_seluler' => 'nomor_hp']);
        $sync->sinkronkan('mahasiswa', $this->term);
        $sync->sinkronkan('mahasiswa', $this->term);

        $panggilan = collect($this->feeder->riwayat)->pluck('act')->all();
        $pembaruan = collect($this->feeder->riwayat)->firstWhere('act', 'UpdateBiodataMahasiswa');

        expect($panggilan)->toBe(['InsertBiodataMahasiswa', 'UpdateBiodataMahasiswa'])
            ->and($pembaruan['payload']['key'])->toBe(['id_mahasiswa' => $idMahasiswa])
            ->and($pembaruan['payload']['record'])->toHaveKey('nomor_hp')->not->toHaveKey('telepon_seluler')
            ->and(FeederSyncLog::query()->orderBy('id')->pluck('status')->map->value->all())
            ->toBe(['success', 'skipped', 'success', 'skipped'])
            ->and($m->fresh()->feeder_id)->toBe($idMahasiswa);
    });
});

describe('saran config dari kamus', function () {
    it('menyarankan nama HP dan nama tanggal ijazah yang ditemukan di kamus 3.0.1', function () {
        $this->feeder->kamus('3.0.1');
        $pemeriksa = app(PemeriksaKamusFeeder::class);
        $saran = collect($pemeriksa->saranConfig($pemeriksa->periksa()))->keyBy('env');

        expect($saran['FEEDER_MEDAN_HP']->status)->toBe('ubah')
            ->and($saran['FEEDER_MEDAN_HP']->barisEnv())->toBe('FEEDER_MEDAN_HP=nomor_hp')
            ->and($saran['FEEDER_MEDAN_TANGGAL_IJAZAH']->status)->toBe('isi')
            ->and($saran['FEEDER_MEDAN_TANGGAL_IJAZAH']->barisEnv())->toBe('FEEDER_MEDAN_TANGGAL_IJAZAH=tanggal_terbit_ijazah');
    });

    it('menyatakan sesuai dan menghapus temuan beda nama sesudah config mengikuti kamus', function () {
        $this->feeder->kamus('3.1');
        config(['feeder.medan.telepon_seluler' => 'nomor_hp', 'feeder.medan.tanggal_ijazah' => 'tanggal_terbit_ijazah']);

        $pemeriksa = app(PemeriksaKamusFeeder::class);
        $run = $pemeriksa->periksa();
        $laporan = $pemeriksa->laporan($run)->keyBy('entity');

        expect(collect($pemeriksa->saranConfig($run))->pluck('status')->all())->toBe(['sesuai', 'sesuai'])
            ->and(collect($laporan['mahasiswa']->temuan)->pluck('jenis.value')->all())->toEqualCanonicalizing(['wajib_tidak_dikirim', 'tidak_dikenal'])
            ->and($laporan['lulus_do']->cocok())->toBeTrue();
    });

    it('menyarankan mengosongkan nama tanggal ijazah yang tidak ada di kamus', function () {
        config(['feeder.medan.tanggal_ijazah' => 'tgl_ijazah_tebakan']);
        $pemeriksa = app(PemeriksaKamusFeeder::class);
        $saran = collect($pemeriksa->saranConfig($pemeriksa->periksa()))->keyBy('env');

        expect($saran['FEEDER_MEDAN_HP']->status)->toBe('sesuai')
            ->and($saran['FEEDER_MEDAN_TANGGAL_IJAZAH']->status)->toBe('kosongkan')
            ->and($saran['FEEDER_MEDAN_TANGGAL_IJAZAH']->barisEnv())->toBe('FEEDER_MEDAN_TANGGAL_IJAZAH=');
    });

    it('mencetak saran pada perintah artisan', function () {
        $this->feeder->kamus('3.0.1');

        $this->artisan('openacademic:feeder-kamus')
            ->expectsOutputToContain('FEEDER_MEDAN_HP=nomor_hp')
            ->expectsOutputToContain('FEEDER_MEDAN_TANGGAL_IJAZAH=tanggal_terbit_ijazah')
            ->assertSuccessful();
    });
});
