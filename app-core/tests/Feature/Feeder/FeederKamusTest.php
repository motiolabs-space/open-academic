<?php

declare(strict_types=1);

use App\DTOs\Feeder\LaporanKamusEntitas;
use App\DTOs\Feeder\TemuanKamus;
use App\Enums\JenisTemuanKamus;
use App\Enums\SemesterType;
use App\Exceptions\FeederException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederDictionaryAct;
use App\Models\Feeder\FeederDictionaryRun;
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\KamusFeeder;
use App\Services\Feeder\PemeriksaKamusFeeder;
use Database\Seeders\RolePermissionSeeder;

/*
 * The web-service dictionary check (ADR-042 §1): reading GetDictionary,
 * every kind of finding, and that it reports without changing anything.
 *
 * The dictionaries here are invented (FakeFeederClient::KAMUS_FIKTIF and the
 * shapes below). No real Neo Feeder dictionary is, or may be, committed.
 */

beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);
    $this->feeder = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->feeder);
    $this->pemeriksa = app(PemeriksaKamusFeeder::class);
});

/** @return LaporanKamusEntitas the report row for one entity of a reading */
function laporanKamus(FeederDictionaryRun $run, string $entity): LaporanKamusEntitas
{
    return app(PemeriksaKamusFeeder::class)->laporan($run)->firstWhere('entity', $entity);
}

/** @return array<int, array{0: string, 1: ?string, 2: ?string}> findings as [kind, ours, theirs] */
function temuanRingkas(LaporanKamusEntitas $l): array
{
    return array_map(fn (TemuanKamus $t): array => [$t->jenis->value, $t->kita, $t->feeder], $l->temuan);
}

describe('membaca balasan GetDictionary', function () {
    it('membaca peta medan di bawah nama aksi dan record', function () {
        $medan = KamusFeeder::uraikan(['InsertX' => ['record' => [
            'nim' => ['type' => 'varchar(24)', 'required' => true, 'keterangan' => 'Nomor induk'],
            'catatan' => ['type' => 'text', 'required' => false],
        ]]], 'InsertX');

        expect($medan)->toBe([
            ['nama' => 'nim', 'wajib' => true, 'kunci' => false, 'tipe' => 'varchar(24)', 'keterangan' => 'Nomor induk'],
            ['nama' => 'catatan', 'wajib' => false, 'kunci' => false, 'tipe' => 'text', 'keterangan' => null],
        ]);
    });

    it('membaca daftar baris bergaya information_schema, termasuk kunci primer', function () {
        $medan = KamusFeeder::uraikan([
            ['column_name' => 'id_x', 'data_type' => 'uuid', 'is_nullable' => 'NO', 'primary' => 'Y'],
            ['column_name' => 'nama', 'data_type' => 'varchar', 'is_nullable' => 'NO'],
            ['column_name' => 'alias', 'data_type' => 'varchar', 'is_nullable' => 'YES'],
        ], 'InsertX');

        expect(collect($medan)->mapWithKeys(fn ($m) => [$m['nama'] => [$m['wajib'], $m['kunci']]])->all())->toBe([
            'id_x' => [true, true],
            'nama' => [true, false],
            'alias' => [false, false],
        ]);
    });

    it('membaca request yang membungkus record, dan teks "wajib" pada keterangan', function () {
        $medan = KamusFeeder::uraikan(['request' => ['act' => 'InsertX', 'record' => [
            'a' => 'varchar (wajib)',
            'b' => 'varchar',
        ]]], 'InsertX');

        expect(collect($medan)->pluck('wajib', 'nama')->all())->toBe(['a' => true, 'b' => false]);
    });

    it('menolak bentuk yang tidak dikenali alih-alih membacanya sebagai aksi tanpa medan', function () {
        expect(KamusFeeder::uraikan(['fungsi' => 'InsertX', 'keterangan' => 'Menambah X'], 'InsertX'))->toBeNull()
            ->and(KamusFeeder::uraikan([], 'InsertX'))->toBeNull()
            ->and(KamusFeeder::uraikan('teks', 'InsertX'))->toBeNull();
    });

    it('membaca versi yang dilaporkan balasan', function () {
        expect(KamusFeeder::versi(['versi' => '3.1', 'data' => []]))->toBe('3.1')
            ->and(KamusFeeder::versi(['data' => ['version' => '3.0.1']]))->toBe('3.0.1')
            ->and(KamusFeeder::versi(['data' => []]))->toBeNull();
    });
});

describe('setiap jenis selisih', function () {
    it('melaporkan medan yang kita kirim tetapi tidak dikenal kamus', function () {
        [$temuan] = $this->pemeriksa->bandingkan(['nim', 'warna_favorit'], [
            ['nama' => 'nim', 'wajib' => true, 'kunci' => false],
        ]);

        expect(array_map(fn ($t) => [$t->jenis, $t->kita], $temuan))->toBe([[JenisTemuanKamus::TidakDikenal, 'warna_favorit']]);
    });

    it('melaporkan medan wajib yang tidak dikirim, tetapi bukan kunci primer maupun medan opsional', function () {
        [$temuan, $opsional] = $this->pemeriksa->bandingkan(['nim'], [
            ['nama' => 'nim', 'wajib' => true, 'kunci' => false],
            ['nama' => 'id_wilayah', 'wajib' => true, 'kunci' => false, 'keterangan' => 'Kode wilayah'],
            ['nama' => 'id_registrasi', 'wajib' => true, 'kunci' => true],
            ['nama' => 'catatan', 'wajib' => false, 'kunci' => false],
        ]);

        expect($temuan)->toHaveCount(1)
            ->and($temuan[0]->jenis)->toBe(JenisTemuanKamus::WajibTidakDikirim)
            ->and($temuan[0]->feeder)->toBe('id_wilayah')
            ->and($temuan[0]->keterangan)->toBe('Kode wilayah')
            ->and($opsional)->toBe(['catatan']);
    });

    it('memasangkan sinonim yang ejaannya berjauhan sebagai satu medan yang berganti nama', function () {
        [$temuan] = $this->pemeriksa->bandingkan(['telepon_seluler'], [
            ['nama' => 'handphone', 'wajib' => true, 'kunci' => false],
        ]);

        expect($temuan)->toHaveCount(1)
            ->and([$temuan[0]->jenis, $temuan[0]->kita, $temuan[0]->feeder, $temuan[0]->wajib])
            ->toBe([JenisTemuanKamus::BedaNama, 'telepon_seluler', 'handphone', true]);
    });

    it('memasangkan nama yang katanya termuat di nama lain, satu pasangan per medan', function () {
        [$temuan] = $this->pemeriksa->bandingkan(['nama_ibu', 'judul'], [
            ['nama' => 'nama_ibu_kandung', 'wajib' => true, 'kunci' => false],
            ['nama' => 'nama_ibu_tiri', 'wajib' => false, 'kunci' => false],
            ['nama' => 'judul', 'wajib' => false, 'kunci' => false],
        ]);

        expect(array_map(fn ($t) => [$t->jenis->value, $t->kita, $t->feeder], $temuan))->toBe([
            ['beda_nama', 'nama_ibu', 'nama_ibu_kandung'],
        ]);
    });

    it('tidak memasangkan nama yang hanya kebetulan mirip', function () {
        expect($this->pemeriksa->kemiripan('ipk', 'ips'))->toBeLessThan(0.8)
            ->and($this->pemeriksa->kemiripan('nama', 'nama_inggris'))->toBeLessThan(0.8)
            ->and($this->pemeriksa->kemiripan('telepon_seluler', 'nomor_hp'))->toBe(1.0);
    });
});

describe('membaca kamus Feeder kampus', function () {
    it('menyimpan kamus setiap aksi yang kita kirim dan mendapati semuanya cocok bila kamusnya selaras', function () {
        $run = $this->pemeriksa->periksa(versi: '3.0.1');

        $aksi = collect(FeederSyncService::MAPPERS)->map(fn ($k) => app($k)->act())->unique();

        expect($run->jumlah_aksi)->toBe($aksi->count())
            ->and($run->jumlah_gagal)->toBe(0)
            ->and($run->versi_feeder)->toBe('3.0.1')
            ->and($run->driver)->toBe('fake')
            ->and(FeederDictionaryAct::query()->pluck('act')->sort()->values()->all())->toBe($aksi->sort()->values()->all())
            ->and($this->feeder->jumlahPanggilan('GetDictionary'))->toBe($aksi->count())
            ->and($this->feeder->riwayat[0]['payload'])->toBe(['fungsi' => $aksi->first()]);

        expect($this->pemeriksa->laporan($run)->reject->cocok())->toBeEmpty()
            ->and($this->pemeriksa->laporan($run))->toHaveCount(count(FeederSyncService::MAPPERS));
    });

    it('menemukan nama medan HP yang lain dan medan wajib baru pada skenario 3.0.1', function () {
        $this->feeder->kamus('3.0.1');
        $run = $this->pemeriksa->periksa(versi: 'diabaikan');

        expect($run->versi_feeder)->toBe('3.0.1')
            ->and(temuanRingkas(laporanKamus($run, 'mahasiswa')))->toEqualCanonicalizing([
                ['beda_nama', 'telepon_seluler', 'nomor_hp'],
                ['wajib_tidak_dikirim', null, 'id_wilayah'],
            ])
            ->and(laporanKamus($run, 'lulus_do')->cocok())->toBeTrue()
            ->and(laporanKamus($run, 'lulus_do')->opsionalTidakDikirim)->toBe(['tanggal_terbit_ijazah']);
    });

    it('melaporkan aksi yang tidak dilayani, medan yang dihapus, dan medan baru yang diwajibkan pada skenario 3.1', function () {
        $this->feeder->kamus('3.1');
        $run = $this->pemeriksa->periksa();

        $flagship = laporanKamus($run, 'flagship_mbkm');

        expect($run->jumlah_gagal)->toBe(1)
            ->and($flagship->terbaca)->toBeFalse()
            ->and($flagship->temuan[0]->jenis)->toBe(JenisTemuanKamus::AksiTidakTerbaca)
            ->and($flagship->galat)->toContain('tidak ditemukan')
            ->and(temuanRingkas(laporanKamus($run, 'mahasiswa')))->toContain(['tidak_dikenal', 'kewarganegaraan', null])
            ->and(temuanRingkas(laporanKamus($run, 'lulus_do')))->toBe([['wajib_tidak_dikirim', null, 'tanggal_terbit_ijazah']]);
    });

    it('mencatat aksi yang ditolak Feeder dan balasan yang bentuknya tidak dikenal, lalu tetap membaca aksi lain', function () {
        $this->feeder->tolak('GetDictionary', 7, 'Token kamus tidak berlaku');
        $run = $this->pemeriksa->periksa(hanya: ['InsertKRSMahasiswa']);

        expect($run->jumlah_aksi)->toBe(1)
            ->and($run->aksi->first()->galat_kode)->toBe('7')
            ->and($run->aksi->first()->galat)->toBe('Token kamus tidak berlaku');

        $this->feeder->balas('GetDictionary', ['fungsi' => 'InsertKRSMahasiswa', 'keterangan' => 'teks bebas']);
        $run = $this->pemeriksa->periksa(hanya: ['InsertKRSMahasiswa']);

        expect($run->aksi->first()->galat_kode)->toBe('bentuk')
            ->and(laporanKamus($run, 'krs')->terbaca)->toBeFalse();
    });

    it('tidak mengubah pemetaan, config, maupun buku besar', function () {
        $this->feeder->kamus('3.1');
        $this->pemeriksa->periksa();

        expect(config('feeder.entities.mahasiswa.action'))->toBe('InsertBiodataMahasiswa')
            ->and(FeederSyncLog::query()->count())->toBe(0)
            ->and(FeederMapping::query()->count())->toBe(0)
            ->and(collect($this->feeder->riwayat)->pluck('act')->unique()->all())->toBe(['GetDictionary']);
    });

    it('menolak berjalan ketika integrasi dinonaktifkan', function () {
        config(['feeder.enabled' => false]);

        $this->pemeriksa->periksa();
    })->throws(FeederException::class);
});

describe('perintah artisan', function () {
    it('mencetak temuan dan selesai dengan sukses walau ada selisih', function () {
        $this->feeder->kamus('3.0.1');

        $this->artisan('openacademic:feeder-kamus', ['--act' => ['InsertBiodataMahasiswa']])
            ->expectsOutputToContain('versi Feeder: 3.0.1')
            ->expectsOutputToContain('nomor_hp')
            ->expectsOutputToContain('1 entitas berselisih')
            ->assertSuccessful();

        expect(FeederDictionaryRun::query()->sole()->jumlah_aksi)->toBe(1);
    });

    it('gagal bila tidak satu aksi pun terbaca', function () {
        $this->feeder->tolak('GetDictionary');

        $this->artisan('openacademic:feeder-kamus', ['--act' => ['InsertKRSMahasiswa']])->assertFailed();
    });
});

describe('layar konsol', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
        $this->operator = Staff::factory()->create();
        $this->operator->assignRole('operator-pddikti');
        $this->pimpinan = Staff::factory()->create();
        $this->pimpinan->assignRole('pimpinan');
    });

    it('membaca kamus dari layar dan menampilkan laporannya', function () {
        $this->feeder->kamus('3.0.1');

        $this->actingAs($this->operator, 'staff')
            ->post('/admin/feeder/kamus', ['versi' => '3.0.1'])
            ->assertRedirect()
            ->assertSessionHas('peringatan');

        $run = FeederDictionaryRun::query()->sole();

        expect($run->dijalankan_by_staff_id)->toBe($this->operator->id);

        $this->actingAs($this->operator, 'staff')
            ->get('/admin/feeder/kamus?run='.$run->uuid)
            ->assertOk()
            ->assertSee('nomor_hp')
            ->assertSee('id_wilayah')
            ->assertSee(__('feeder.kamus.jenis.beda_nama'));

        $this->actingAs($this->operator, 'staff')
            ->get('/admin/feeder')
            ->assertOk()
            ->assertSee(__('feeder.kamus.konsol.judul'));
    });

    it('membolehkan pimpinan membaca laporan tetapi tidak menjalankan pemeriksaan', function () {
        $this->actingAs($this->pimpinan, 'staff')->get('/admin/feeder/kamus')->assertOk()->assertSee(__('feeder.kamus.kosong.judul'));
        $this->actingAs($this->pimpinan, 'staff')->post('/admin/feeder/kamus')->assertForbidden();

        expect(FeederDictionaryRun::query()->count())->toBe(0);
    });

    it('menolak versi yang bukan nomor versi', function () {
        $this->actingAs($this->operator, 'staff')
            ->post('/admin/feeder/kamus', ['versi' => '<script>'])
            ->assertSessionHasErrors('versi');
    });
});

describe('daftar medan setiap mapper', function () {
    it('memuat setiap kunci yang ditulis payload mapper, tanpa nama ganda', function () {
        foreach (FeederSyncService::MAPPERS as $entity => $kelas) {
            $mapper = app($kelas);
            $metode = new ReflectionMethod($mapper, 'payload');
            $sumber = implode('', array_slice(
                file((string) $metode->getFileName()),
                $metode->getStartLine() - 1,
                $metode->getEndLine() - $metode->getStartLine() + 1,
            ));

            preg_match_all("/^\s+'([a-z_0-9]+)' =>/m", $sumber, $kunci);

            expect(array_diff($kunci[1], $mapper->medan()))->toBe([], "{$entity}: payload menulis medan yang tidak ada di medan()")
                ->and($mapper->medan())->toBe(array_values(array_unique($mapper->medan())), "{$entity}: medan() memuat nama ganda");
        }
    });
});
