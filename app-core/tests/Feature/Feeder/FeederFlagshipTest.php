<?php

declare(strict_types=1);

use App\Enums\FeederSyncStatus;
use App\Enums\SemesterType;
use App\Enums\StudentActivityType;
use App\Exceptions\FeederException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederRef;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FlagshipMbkmService;
use Database\Seeders\RolePermissionSeeder;

/**
 * Klaim program flagship MBKM (#45): sesudah aktivitas dan anggotanya sampai
 * di PDDIKTI, aktivitas yang berjalan di bawah program flagship kementerian
 * diklaim. Programnya dari tarikan referensi, tidak di-seed.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'a1b2c3d4']);
    $this->sync = app(FeederSyncService::class);
});

function flagshipLedgerOk(string $entity): void
{
    FeederSyncLog::create([
        'entity' => $entity,
        'action' => config("feeder.entities.{$entity}.action"),
        'tahun_akademik_id' => test()->term->id,
        'status' => FeederSyncStatus::Success,
        'payload_hash' => str_repeat('0', 64),
    ]);
}

function flagshipProgram(string $kode = 'fs-2', string $nama = 'Magang dan Studi Independen Bersertifikat'): FeederRef
{
    return FeederRef::create(['ref_type' => 'program_flagship', 'code' => $kode, 'name' => $nama]);
}

/** A verified MBKM activity PDDIKTI already holds, member included. */
function flagshipAktivitas(array $atribut = []): AktivitasMahasiswa
{
    $m = Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'feeder_id' => 'mhs-1',
        'feeder_registrasi_id' => 'reg-1',
    ]);

    $aktivitas = AktivitasMahasiswa::create([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'jenis' => StudentActivityType::Magang,
        'judul' => 'Magang di PT Nusantara Digital',
        'mitra_nama' => 'PT Nusantara Digital',
        'tanggal_mulai' => '2026-09-01',
        'sks_konversi' => 20,
        'is_verified' => true,
    ]);

    $aktivitas->forceFill(array_merge(['feeder_id' => 'akt-1', 'feeder_anggota_id' => 'ang-1'], $atribut))->save();

    return $aktivitas;
}

it('mengklaim aktivitas berpenanda flagship dengan payload yang dipaku', function () {
    flagshipLedgerOk('anggota_mbkm');
    flagshipProgram();
    $aktivitas = flagshipAktivitas(['flagship_kode' => 'fs-2']);
    flagshipAktivitas(); // not a flagship programme: never claimed

    $hasil = $this->sync->sinkronkan('flagship_mbkm', $this->term);
    $log = FeederSyncLog::entity('flagship_mbkm')->firstOrFail();

    expect($hasil['terkirim'])->toBe(1)
        ->and($log->action)->toBe('InsertAktivitasMBKMFlagship')
        ->and($log->payload)->toBeJsonColumn(['id_aktivitas' => 'akt-1', 'id_flagship' => 'fs-2'])
        // Addressed by the activity, whatever the reply named.
        ->and($aktivitas->fresh()->feeder_flagship_id)->toBe('akt-1')
        ->and($this->fake->jumlahPanggilan('InsertAktivitasMBKMFlagship'))->toBe(1);
});

it('tidak mengirim ulang klaim yang tidak berubah, dan mengirim perpindahan program sebagai update', function () {
    flagshipLedgerOk('anggota_mbkm');
    flagshipProgram();
    flagshipProgram('fs-3', 'Kampus Mengajar');
    $aktivitas = flagshipAktivitas(['flagship_kode' => 'fs-2']);

    $this->sync->sinkronkan('flagship_mbkm', $this->term);
    $kedua = $this->sync->sinkronkan('flagship_mbkm', $this->term);

    expect($kedua['dilewati'])->toBe(1)
        ->and($this->fake->jumlahPanggilan('InsertAktivitasMBKMFlagship'))->toBe(1);

    app(FlagshipMbkmService::class)->tandai($aktivitas->fresh(), 'fs-3');
    $ketiga = $this->sync->sinkronkan('flagship_mbkm', $this->term);
    $update = collect($this->fake->riwayat)->firstWhere('act', 'UpdateAktivitasMBKMFlagship');

    expect($ketiga['terkirim'])->toBe(1)
        ->and($update['payload']['key'])->toBe(['id_aktivitas' => 'akt-1'])
        ->and($update['payload']['record'])->toBe(['id_aktivitas' => 'akt-1', 'id_flagship' => 'fs-3']);
});

it('menunggu anggota aktivitas terkirim lebih dulu', function () {
    flagshipProgram();
    flagshipAktivitas(['flagship_kode' => 'fs-2']);

    expect(fn () => $this->sync->sinkronkan('flagship_mbkm', $this->term))
        ->toThrow(FeederException::class, 'anggota_mbkm');
});

it('melaporkan seluruh baris yang akan ditolak sebelum mengirim satu pun', function () {
    flagshipLedgerOk('anggota_mbkm');
    flagshipProgram();
    flagshipAktivitas(['flagship_kode' => 'fs-2', 'feeder_anggota_id' => null]);
    flagshipAktivitas(['flagship_kode' => 'fs-lama']);

    expect(fn () => $this->sync->sinkronkan('flagship_mbkm', $this->term))
        ->toThrow(FeederException::class, 'tidak lolos validasi');

    $isu = FeederValidationIssue::where('entity', 'flagship_mbkm')->pluck('message', 'rule');

    expect($isu->keys()->sort()->values()->all())->toBe(['anggota_belum_sinkron', 'program_flagship_tidak_dikenal'])
        ->and($isu['program_flagship_tidak_dikenal'])->toContain('GetListAktivitasMBKMFlagship')
        ->and($this->fake->dipanggil('InsertAktivitasMBKMFlagship'))->toBeFalse();
});

it('menarik daftar program flagship sebagai referensi, bukan seed', function () {
    expect(FeederRef::options('program_flagship'))->toBe([]);

    $this->fake->balas('GetListAktivitasMBKMFlagship', [
        ['id_flagship' => 'fs-1', 'nama_program_flagship' => 'Kampus Mengajar'],
    ]);

    $hasil = $this->sync->tarikReferensi();

    expect($hasil['program_flagship'])->toBe(1)
        ->and(FeederRef::options('program_flagship'))->toBe(['fs-1' => 'Kampus Mengajar']);
});

describe('penanda di layar Verifikasi Data IKU', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        $this->staf = Staff::factory()->create();
        $this->staf->assignRole('super-admin');
    });

    it('menandai aktivitas dengan program dari referensi', function () {
        flagshipProgram();
        $aktivitas = flagshipAktivitas();

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.iku-records', ['belum' => 0]))
            ->assertOk()
            ->assertSee('Magang dan Studi Independen Bersertifikat');

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.iku-records.aktivitas.flagship', $aktivitas), ['flagship_kode' => 'fs-2'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($aktivitas->fresh()->flagship_kode)->toBe('fs-2');
    });

    it('menolak program yang tidak ada pada referensi', function () {
        flagshipProgram();
        $aktivitas = flagshipAktivitas();

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.iku-records.aktivitas.flagship', $aktivitas), ['flagship_kode' => 'karangan'])
            ->assertSessionHas('galat');

        expect($aktivitas->fresh()->flagship_kode)->toBeNull();
    });

    it('tidak mencabut penanda yang klaimnya sudah diterima PDDIKTI', function () {
        flagshipProgram();
        $aktivitas = flagshipAktivitas(['flagship_kode' => 'fs-2', 'feeder_flagship_id' => 'akt-1']);

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.iku-records.aktivitas.flagship', $aktivitas), ['flagship_kode' => ''])
            ->assertSessionHas('galat');

        expect($aktivitas->fresh()->flagship_kode)->toBe('fs-2');
    });
});
