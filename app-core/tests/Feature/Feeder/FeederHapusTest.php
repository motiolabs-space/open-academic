<?php

declare(strict_types=1);

use App\Enums\FeederSyncStatus;
use App\Enums\Gender;
use App\Enums\SemesterType;
use App\Exceptions\FeederException;
use App\Models\Akademik\KelasDosen;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use Database\Seeders\RolePermissionSeeder;

/**
 * The other half of a correction. A push-only sync leaves at PDDIKTI
 * whatever was reported and then removed here; the ledger knows what left,
 * and this reads it back against what is still here.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'a1b2c3d4']);

    $this->sync = app(FeederSyncService::class);
});

function mahasiswaTerkirim(string $nim = '2201001'): Mahasiswa
{
    $m = Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'nim' => $nim,
        'nik' => '32012345678901'.substr($nim, -2),
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2004-05-12',
        'jenis_kelamin' => Gender::LakiLaki,
        'term_masuk' => test()->term->kode,
    ]);

    test()->sync->sinkronkan('mahasiswa', test()->term);

    return $m->fresh();
}

describe('hapus yang sudah tidak ada', function () {
    it('menghapus di Feeder baris yang terhapus lunak di sini, dengan kunci Feeder-nya', function () {
        $m = mahasiswaTerkirim();
        $idFeeder = $m->feeder_id;
        $m->delete();

        $hasil = $this->sync->hapusYangTerhapus('mahasiswa', $this->term);
        $panggilan = collect($this->fake->riwayat)->last();

        expect($hasil)->toMatchArray(['diperiksa' => 1, 'dihapus' => 1, 'dilewati' => 0, 'gagal' => 0, 'didukung' => true])
            ->and($panggilan['act'])->toBe('DeleteBiodataMahasiswa')
            ->and($panggilan['payload'])->toBe(['key' => ['id_mahasiswa' => $idFeeder]]);

        $log = FeederSyncLog::entity('mahasiswa')->where('action', 'DeleteBiodataMahasiswa')->firstOrFail();

        expect($log->status)->toBe(FeederSyncStatus::Success)
            ->and($log->local_id)->toBe($m->id)
            ->and($log->feeder_id)->toBe($idFeeder);
    });

    it('tidak menyentuh baris yang masih ada', function () {
        mahasiswaTerkirim('2201001');
        mahasiswaTerkirim('2201002')->delete();

        $hasil = $this->sync->hapusYangTerhapus('mahasiswa', $this->term);

        expect($hasil['diperiksa'])->toBe(2)
            ->and($hasil['dihapus'])->toBe(1)
            ->and($hasil['dilewati'])->toBe(1)
            ->and($this->fake->jumlahPanggilan('DeleteBiodataMahasiswa'))->toBe(1);
    });

    it('tidak menghapus dua kali: penghapusan yang sudah tercatat dilewati pada putaran berikutnya', function () {
        mahasiswaTerkirim()->delete();

        $this->sync->hapusYangTerhapus('mahasiswa', $this->term);
        $kedua = $this->sync->hapusYangTerhapus('mahasiswa', $this->term);

        expect($kedua['diperiksa'])->toBe(0)
            ->and($kedua['dihapus'])->toBe(0)
            ->and($this->fake->jumlahPanggilan('DeleteBiodataMahasiswa'))->toBe(1);
    });

    it('menangani baris yang benar-benar dihapus dari tabel, bukan hanya terhapus lunak', function () {
        FeederSyncLog::create([
            'entity' => 'kelas_kuliah', 'action' => 'InsertKelasKuliah', 'tahun_akademik_id' => $this->term->id,
            'status' => FeederSyncStatus::Success, 'payload_hash' => str_repeat('0', 64),
        ]);
        $kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->term->id,
            'mata_kuliah_id' => MataKuliah::factory()->create(['prodi_id' => $this->prodi->id])->id,
            'prodi_id' => $this->prodi->id,
            'feeder_id' => 'kelas-1',
        ]);
        $dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'feeder_id' => 'dosen-1']);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        $this->sync->sinkronkan('dosen_pengajar', $this->term);
        $idFeeder = KelasDosen::firstOrFail()->feeder_id;
        KelasDosen::query()->delete(); // no SoftDeletes on this table: the row is gone

        $hasil = $this->sync->hapusYangTerhapus('dosen_pengajar', $this->term);

        expect($hasil['dihapus'])->toBe(1)
            ->and(collect($this->fake->riwayat)->last())->toMatchArray([
                'act' => 'DeleteDosenPengajarKelasKuliah',
                'payload' => ['key' => ['id_aktivitas_mengajar' => $idFeeder]],
            ]);
    });

    it('mencatat penolakan Feeder sebagai gagal dan mencoba lagi pada putaran berikutnya', function () {
        mahasiswaTerkirim()->delete();
        $this->fake->tolak('DeleteBiodataMahasiswa', 7, 'Data masih dirujuk riwayat pendidikan');

        $pertama = $this->sync->hapusYangTerhapus('mahasiswa', $this->term);

        $log = FeederSyncLog::entity('mahasiswa')->where('action', 'DeleteBiodataMahasiswa')->latest('id')->firstOrFail();

        expect($pertama['gagal'])->toBe(1)
            ->and($log->status)->toBe(FeederSyncStatus::Failed)
            ->and($log->error_message)->toContain('masih dirujuk');

        // The failed delete is not the last *successful* action, so it is due again.
        $this->fake = new FakeFeederClient;
        $this->app->instance(FeederClientInterface::class, $this->fake);

        expect(app(FeederSyncService::class)->hapusYangTerhapus('mahasiswa', $this->term)['dihapus'])->toBe(1);
    });

    it('menyatakan entitas yang Feeder-nya tidak punya aksi hapus, alih-alih menebak', function () {
        $hasil = $this->sync->hapusYangTerhapus('nilai_komponen', $this->term);

        expect($hasil['didukung'])->toBeFalse()
            ->and($hasil['diperiksa'])->toBe(0)
            ->and($this->fake->riwayat)->toBe([]);
    });

    it('menolak berjalan ketika integrasi dinonaktifkan', function () {
        config(['feeder.enabled' => false]);

        expect(fn () => $this->sync->hapusYangTerhapus('mahasiswa', $this->term))
            ->toThrow(FeederException::class, 'dinonaktifkan');
    });
});

describe('layar dan perintah', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        TahunAkademik::query()->update(['tanggal_mulai' => now()->subWeeks(8), 'tanggal_selesai' => now()->addWeeks(8)]);
        $this->staf = Staff::factory()->create();
        $this->staf->assignRole('super-admin');
    });

    it('menawarkan tombol hapus hanya untuk entitas yang punya aksi hapus', function () {
        $html = $this->actingAs($this->staf, 'staff')->get('/admin/feeder')->assertOk()->getContent();

        expect(substr_count($html, '/admin/feeder/mahasiswa/hapus'))->toBe(1)
            ->and(substr_count($html, '/admin/feeder/nilai_komponen/hapus'))->toBe(0);
    });

    it('menjalankan penghapusan dari layar dan melaporkan angkanya', function () {
        mahasiswaTerkirim()->delete();

        $this->actingAs($this->staf, 'staff')
            ->post('/admin/feeder/mahasiswa/hapus')
            ->assertRedirect()
            ->assertSessionHas('sukses', fn (string $pesan): bool => str_contains($pesan, '1 sudah tidak ada di sini dan dihapus dari Feeder'));
    });

    it('menjalankan penghapusan dari perintah artisan', function () {
        mahasiswaTerkirim()->delete();

        $this->artisan('openacademic:feeder-hapus', ['entity' => 'mahasiswa'])
            ->expectsOutputToContain('mahasiswa')
            ->assertSuccessful();

        expect($this->fake->jumlahPanggilan('DeleteBiodataMahasiswa'))->toBe(1);
    });
});
