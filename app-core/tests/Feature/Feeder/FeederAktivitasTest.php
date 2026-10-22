<?php

declare(strict_types=1);

use App\Enums\FeederSyncStatus;
use App\Enums\JenisUjian;
use App\Enums\PeranPembimbing;
use App\Enums\PeranPenguji;
use App\Enums\SemesterType;
use App\Enums\StatusUjian;
use App\Enums\StudentActivityType;
use App\Enums\TugasAkhirStatus;
use App\Exceptions\FeederException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\Pembimbing;
use App\Models\TugasAkhir\Penguji;
use App\Models\TugasAkhir\TugasAkhir;
use App\Models\TugasAkhir\Ujian;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use Database\Seeders\RolePermissionSeeder;

/**
 * Student activities as PDDIKTI sees them: one activity, its member, its
 * supervisors, its examiners. Two local sources (MBKM programmes and the
 * final project), one chain of Feeder entities each. This is also the route
 * by which supervising and examining reach SISTER.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'a1b2c3d4']);

    // Neither code set is seeded: a campus maps them after the reference pull.
    FeederMapping::create(['group' => 'jenis_aktivitas', 'local_value' => 'magang', 'feeder_code' => '2']);
    FeederMapping::create(['group' => 'jenis_aktivitas', 'local_value' => 'tugas_akhir', 'feeder_code' => '1']);
    FeederMapping::create(['group' => 'kategori_kegiatan', 'local_value' => 'pembimbing_mbkm', 'feeder_code' => '110100']);
    FeederMapping::create(['group' => 'kategori_kegiatan', 'local_value' => 'pembimbing_tugas_akhir', 'feeder_code' => '110401']);
    FeederMapping::create(['group' => 'kategori_kegiatan', 'local_value' => 'penguji_tugas_akhir', 'feeder_code' => '110402']);

    $this->sync = app(FeederSyncService::class);
    $this->validator = app(FeederValidator::class);
});

function ledgerOk(string $entity): void
{
    FeederSyncLog::create([
        'entity' => $entity,
        'action' => config("feeder.entities.{$entity}.action"),
        'tahun_akademik_id' => test()->term->id,
        'status' => FeederSyncStatus::Success,
        'payload_hash' => str_repeat('0', 64),
    ]);
}

function mahasiswaTeregistrasi(string $registrasi = 'reg-1'): Mahasiswa
{
    return Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'feeder_id' => 'mhs-'.$registrasi,
        'feeder_registrasi_id' => $registrasi,
    ]);
}

function dosenDiPddikti(string $id = 'dosen-1'): Dosen
{
    return Dosen::factory()->create(['prodi_id' => test()->prodi->id, 'feeder_id' => $id]);
}

function magangTerverifikasi(Mahasiswa $m, ?Dosen $pembimbing = null, array $atribut = []): AktivitasMahasiswa
{
    return AktivitasMahasiswa::create(array_merge([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'dosen_pembimbing_id' => $pembimbing?->id,
        'jenis' => StudentActivityType::Magang,
        'judul' => 'Magang di PT Nusantara Digital',
        'mitra_nama' => 'PT Nusantara Digital',
        'lokasi' => 'Bandung',
        'tanggal_mulai' => '2026-09-01',
        'sks_konversi' => 20,
        'is_verified' => true,
    ], $atribut));
}

function tugasAkhirDisetujui(Mahasiswa $m, array $atribut = []): TugasAkhir
{
    return TugasAkhir::create(array_merge([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'mahasiswa_aktif_id' => $m->id,
        'judul' => 'Sistem Informasi Akademik Terbuka',
        'bidang_kajian' => 'Rekayasa Perangkat Lunak',
        'status' => TugasAkhirStatus::Dibimbing,
        'tanggal_pengajuan' => '2026-09-01',
        'tanggal_disetujui' => '2026-09-10',
    ], $atribut));
}

describe('aktivitas MBKM', function () {
    it('mengirim aktivitas terverifikasi dengan jenis terpetakan dan menandai kampus merdeka', function () {
        ledgerOk('riwayat_pendidikan');
        $m = mahasiswaTeregistrasi();
        magangTerverifikasi($m);
        magangTerverifikasi($m, null, ['judul' => 'Belum diverifikasi', 'is_verified' => false]);

        $hasil = $this->sync->sinkronkan('aktivitas_mbkm', $this->term);
        $payload = FeederSyncLog::entity('aktivitas_mbkm')->firstOrFail()->payload;

        expect($hasil['terkirim'])->toBe(1)
            ->and($payload)->toBe([
                'id_jenis_aktivitas' => '2',
                'id_prodi' => 'a1b2c3d4',
                'id_semester' => $this->term->kode,
                'judul' => 'Magang di PT Nusantara Digital',
                'lokasi' => 'Bandung',
                'tanggal_sk_tugas' => '2026-09-01',
                'untuk_kampus_merdeka' => 1,
            ])
            ->and(AktivitasMahasiswa::where('is_verified', true)->value('feeder_id'))->not->toBeNull();
    });

    it('menolak jenis aktivitas yang belum dipetakan, dan menyebut referensi yang harus ditarik', function () {
        ledgerOk('riwayat_pendidikan');
        FeederMapping::query()->where('group', 'jenis_aktivitas')->delete();
        magangTerverifikasi(mahasiswaTeregistrasi());

        expect(fn () => $this->sync->sinkronkan('aktivitas_mbkm', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        $isu = FeederValidationIssue::where('rule', 'jenis_aktivitas_belum_dipetakan')->firstOrFail();

        expect($isu->message)->toContain('GetJenisAktivitasMahasiswa')
            ->and($this->fake->dipanggil('InsertAktivitasMahasiswa'))->toBeFalse();
    });

    it('mendaftarkan mahasiswa sebagai anggota aktivitasnya sendiri, dengan id tersendiri', function () {
        ledgerOk('riwayat_pendidikan');
        $m = mahasiswaTeregistrasi('reg-7');
        $aktivitas = magangTerverifikasi($m);
        $aktivitas->forceFill(['feeder_id' => 'akt-1'])->save();
        ledgerOk('aktivitas_mbkm');

        $hasil = $this->sync->sinkronkan('anggota_mbkm', $this->term);
        $payload = FeederSyncLog::entity('anggota_mbkm')->firstOrFail()->payload;

        expect($hasil['terkirim'])->toBe(1)
            ->and($payload)->toBe(['id_aktivitas' => 'akt-1', 'id_registrasi_mahasiswa' => 'reg-7', 'jenis_peran' => '3'])
            ->and($aktivitas->fresh()->feeder_anggota_id)->not->toBeNull()
            ->and($aktivitas->fresh()->feeder_id)->toBe('akt-1');
    });

    it('tidak mendaftarkan anggota sebelum aktivitasnya sendiri sampai di Feeder', function () {
        ledgerOk('riwayat_pendidikan');
        ledgerOk('aktivitas_mbkm');
        magangTerverifikasi(mahasiswaTeregistrasi());

        expect(app(FeederSyncService::MAPPERS['anggota_mbkm'])->rows($this->term->kode))->toHaveCount(0);
    });

    it('mengirim pembimbing MBKM dengan kategori kegiatan dan id_dosen PDDIKTI', function () {
        ledgerOk('riwayat_pendidikan');
        ledgerOk('aktivitas_mbkm');
        $dosen = dosenDiPddikti('dosen-9');
        $aktivitas = magangTerverifikasi(mahasiswaTeregistrasi(), $dosen);
        $aktivitas->forceFill(['feeder_id' => 'akt-1'])->save();

        $hasil = $this->sync->sinkronkan('bimbing_mbkm', $this->term);
        $payload = FeederSyncLog::entity('bimbing_mbkm')->firstOrFail()->payload;

        expect($hasil['terkirim'])->toBe(1)
            ->and($payload)->toBe(['id_aktivitas' => 'akt-1', 'id_kategori_kegiatan' => '110100', 'id_dosen' => 'dosen-9', 'pembimbing_ke' => 1])
            ->and($aktivitas->fresh()->feeder_bimbing_id)->not->toBeNull();
    });

    it('menolak pembimbing yang belum punya id_dosen PDDIKTI', function () {
        ledgerOk('riwayat_pendidikan');
        ledgerOk('aktivitas_mbkm');
        $praktisi = Dosen::factory()->praktisi()->create(['prodi_id' => $this->prodi->id]);
        magangTerverifikasi(mahasiswaTeregistrasi(), $praktisi)->forceFill(['feeder_id' => 'akt-1'])->save();

        expect(fn () => $this->sync->sinkronkan('bimbing_mbkm', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::where('rule', 'dosen_belum_dipetakan')->exists())->toBeTrue();
    });
});

describe('tugas akhir sebagai aktivitas', function () {
    it('mengirim tugas akhir yang disetujui sebagai aktivitas bukan kampus merdeka', function () {
        ledgerOk('riwayat_pendidikan');
        $m = mahasiswaTeregistrasi();
        tugasAkhirDisetujui($m);
        tugasAkhirDisetujui(mahasiswaTeregistrasi('reg-2'), ['status' => TugasAkhirStatus::Diajukan, 'judul' => 'Masih diajukan']);

        $hasil = $this->sync->sinkronkan('tugas_akhir', $this->term);
        $payload = FeederSyncLog::entity('tugas_akhir')->firstOrFail()->payload;

        expect($hasil['terkirim'])->toBe(1)
            ->and($payload['id_jenis_aktivitas'])->toBe('1')
            ->and($payload['judul'])->toBe('Sistem Informasi Akademik Terbuka')
            ->and($payload['keterangan'])->toBe('Rekayasa Perangkat Lunak')
            ->and($payload['tanggal_sk_tugas'])->toBe('2026-09-10')
            ->and($payload['untuk_kampus_merdeka'])->toBe(0);
    });

    it('mengirim pembimbing utama sebagai pembimbing ke-1 dan pendamping ke-2', function () {
        ledgerOk('riwayat_pendidikan');
        ledgerOk('tugas_akhir');
        $ta = tugasAkhirDisetujui(mahasiswaTeregistrasi());
        $ta->forceFill(['feeder_id' => 'akt-ta'])->save();
        Pembimbing::create(['tugas_akhir_id' => $ta->id, 'dosen_id' => dosenDiPddikti('dosen-b')->id, 'peran' => PeranPembimbing::Pendamping, 'ditetapkan_pada' => '2026-09-10']);
        Pembimbing::create(['tugas_akhir_id' => $ta->id, 'dosen_id' => dosenDiPddikti('dosen-a')->id, 'peran' => PeranPembimbing::Utama, 'ditetapkan_pada' => '2026-09-10']);

        $hasil = $this->sync->sinkronkan('bimbing_tugas_akhir', $this->term);
        $payloads = FeederSyncLog::entity('bimbing_tugas_akhir')->orderBy('id')->pluck('payload');

        expect($hasil['terkirim'])->toBe(2)
            ->and($payloads->firstWhere('id_dosen', 'dosen-a')['pembimbing_ke'])->toBe(1)
            ->and($payloads->firstWhere('id_dosen', 'dosen-b')['pembimbing_ke'])->toBe(2)
            ->and($payloads->pluck('id_kategori_kegiatan')->unique()->all())->toBe(['110401'])
            ->and(Pembimbing::whereNull('feeder_id')->count())->toBe(0);
    });

    it('mengirim penguji hanya dari sidang yang sudah terlaksana', function () {
        ledgerOk('riwayat_pendidikan');
        ledgerOk('tugas_akhir');
        $ta = tugasAkhirDisetujui(mahasiswaTeregistrasi());
        $ta->forceFill(['feeder_id' => 'akt-ta'])->save();

        $sidang = Ujian::create(['tugas_akhir_id' => $ta->id, 'jenis' => JenisUjian::Sidang, 'tanggal' => '2026-12-01', 'jam_mulai' => '09:00', 'jam_selesai' => '11:00', 'status' => StatusUjian::Selesai]);
        $proposal = Ujian::create(['tugas_akhir_id' => $ta->id, 'jenis' => JenisUjian::Proposal, 'tanggal' => '2026-10-01', 'jam_mulai' => '09:00', 'jam_selesai' => '10:00', 'status' => StatusUjian::Selesai]);
        $dijadwalkan = Ujian::create(['tugas_akhir_id' => $ta->id, 'jenis' => JenisUjian::Sidang, 'tanggal' => '2026-12-15', 'jam_mulai' => '09:00', 'jam_selesai' => '11:00', 'status' => StatusUjian::Dijadwalkan]);

        Penguji::create(['tugas_akhir_ujian_id' => $sidang->id, 'dosen_id' => dosenDiPddikti('ketua')->id, 'peran' => PeranPenguji::Ketua]);
        Penguji::create(['tugas_akhir_ujian_id' => $sidang->id, 'dosen_id' => dosenDiPddikti('anggota')->id, 'peran' => PeranPenguji::Anggota]);
        Penguji::create(['tugas_akhir_ujian_id' => $proposal->id, 'dosen_id' => dosenDiPddikti('proposal')->id, 'peran' => PeranPenguji::Ketua]);
        Penguji::create(['tugas_akhir_ujian_id' => $dijadwalkan->id, 'dosen_id' => dosenDiPddikti('nanti')->id, 'peran' => PeranPenguji::Ketua]);

        $hasil = $this->sync->sinkronkan('uji_tugas_akhir', $this->term);
        $payloads = FeederSyncLog::entity('uji_tugas_akhir')->pluck('payload');

        expect($hasil['terkirim'])->toBe(2)
            ->and($payloads->pluck('id_dosen')->sort()->values()->all())->toBe(['anggota', 'ketua'])
            ->and($payloads->firstWhere('id_dosen', 'ketua')['penguji_ke'])->toBe(1)
            ->and($payloads->firstWhere('id_dosen', 'anggota')['penguji_ke'])->toBe(3)
            ->and($payloads->firstWhere('id_dosen', 'ketua')['id_kategori_kegiatan'])->toBe('110402');
    });

    it('menolak kategori kegiatan yang belum dipetakan', function () {
        ledgerOk('riwayat_pendidikan');
        ledgerOk('tugas_akhir');
        FeederMapping::query()->where('group', 'kategori_kegiatan')->delete();
        $ta = tugasAkhirDisetujui(mahasiswaTeregistrasi());
        $ta->forceFill(['feeder_id' => 'akt-ta'])->save();
        Pembimbing::create(['tugas_akhir_id' => $ta->id, 'dosen_id' => dosenDiPddikti()->id, 'peran' => PeranPembimbing::Utama, 'ditetapkan_pada' => '2026-09-10']);

        expect(fn () => $this->sync->sinkronkan('bimbing_tugas_akhir', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::where('rule', 'kategori_kegiatan_belum_dipetakan')->firstOrFail()->message)
            ->toContain('GetKategoriKegiatan');
    });

    it('menolak berjalan sebelum tugas akhir sebagai aktivitas disinkronkan', function () {
        expect(fn () => $this->sync->sinkronkan('bimbing_tugas_akhir', $this->term))
            ->toThrow(FeederException::class, 'tugas_akhir');
    });
});

describe('layar monitor', function () {
    it('menampilkan ketujuh entitas aktivitas pada konsol Feeder', function () {
        $this->seed(RolePermissionSeeder::class);
        TahunAkademik::query()->update(['tanggal_mulai' => now()->subWeeks(8), 'tanggal_selesai' => now()->addWeeks(8)]);

        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->get('/admin/feeder')
            ->assertOk()
            ->assertSee('Aktivitas MBKM')
            ->assertSee('Pembimbing Aktivitas MBKM')
            ->assertSee('Tugas Akhir (aktivitas)')
            ->assertSee('Penguji Tugas Akhir');
    });
});
