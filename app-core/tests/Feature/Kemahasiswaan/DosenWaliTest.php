<?php

declare(strict_types=1);

use App\Enums\CaraPenetapanWali;
use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\Krs;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\RiwayatDosenWali;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Notifications\Kemahasiswaan\DosenWaliDitetapkan;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Assigning academic advisors. Before this screen existed nothing outside the
 * demo seeder wrote mahasiswa.dosen_wali_id, so a real campus could not run
 * KRS approval at all.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    Portal::lupakanTerm();

    $this->prodi = Prodi::factory()->create();

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->dosenA = Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'nama' => 'Ahmad Wali']);
    $this->dosenA->assignRole('dosen');
    $this->dosenB = Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'nama' => 'Budi Wali']);
    $this->dosenB->assignRole('dosen');
});

function mahasiswaPerwalian(Prodi $prodi, array $atribut = []): Mahasiswa
{
    $m = Mahasiswa::factory()->create($atribut + ['prodi_id' => $prodi->id, 'status' => StudentStatus::Aktif, 'angkatan' => 2026]);
    $m->assignRole('mahasiswa');

    return $m;
}

it('menetapkan dosen wali satu mahasiswa, mencatat riwayat, dan memberi tahu mahasiswa', function () {
    Notification::fake();
    $m = mahasiswaPerwalian($this->prodi);

    $this->actingAs($this->baak, 'staff')
        ->post("/admin/mahasiswa/{$m->uuid}/dosen-wali", ['dosen' => $this->dosenA->uuid, 'alasan' => 'Penetapan awal'])
        ->assertRedirect()
        ->assertSessionHas('sukses');

    expect($m->fresh()->dosen_wali_id)->toBe($this->dosenA->id);

    $riwayat = RiwayatDosenWali::sole();
    expect($riwayat->dosen_lama_id)->toBeNull()
        ->and($riwayat->dosen_baru_id)->toBe($this->dosenA->id)
        ->and($riwayat->cara)->toBe(CaraPenetapanWali::Perorangan)
        ->and($riwayat->alasan)->toBe('Penetapan awal')
        ->and($riwayat->ditetapkan_by_staff_id)->toBe($this->baak->id);

    // The change on the student row itself is in the audit trail.
    expect(LogAktivitas::where('subject_type', $m->getMorphClass())->where('subject_id', $m->id)
        ->where('event', 'updated')->exists())->toBeTrue();

    // A lecturer given advisees gets the role that carries krs.approve.
    expect($this->dosenA->fresh()->hasRole('dosen-wali'))->toBeTrue();

    Notification::assertSentTo($m, DosenWaliDitetapkan::class);
    Notification::assertNotSentTo($this->dosenA, DosenWaliDitetapkan::class);
});

it('menolak dosen dari prodi lain, dosen nonaktif, dan mahasiswa yang sudah lulus', function () {
    $m = mahasiswaPerwalian($this->prodi);
    $lain = Dosen::factory()->create();
    $nonaktif = Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'is_active' => false]);
    $lulus = mahasiswaPerwalian($this->prodi, ['status' => StudentStatus::Lulus]);

    $this->actingAs($this->baak, 'staff');

    $this->post("/admin/mahasiswa/{$m->uuid}/dosen-wali", ['dosen' => $lain->uuid])->assertSessionHas('galat');
    $this->post("/admin/mahasiswa/{$m->uuid}/dosen-wali", ['dosen' => $nonaktif->uuid])->assertSessionHas('galat');
    $this->post("/admin/mahasiswa/{$lulus->uuid}/dosen-wali", ['dosen' => $this->dosenA->uuid])->assertSessionHas('galat');

    expect($m->fresh()->dosen_wali_id)->toBeNull()
        ->and($lulus->fresh()->dosen_wali_id)->toBeNull()
        ->and(RiwayatDosenWali::count())->toBe(0);
});

it('mengalihkan KRS yang sudah diajukan ke dosen wali baru', function () {
    Notification::fake();
    $this->dosenA->assignRole('dosen-wali');
    $m = mahasiswaPerwalian($this->prodi, ['dosen_wali_id' => $this->dosenA->id]);
    $krs = Krs::factory()->diajukan()->create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id]);

    $this->actingAs($this->baak, 'staff')
        ->post("/admin/mahasiswa/{$m->uuid}/dosen-wali", ['dosen' => $this->dosenB->uuid])
        ->assertSessionHas('sukses', fn (string $pesan): bool => str_contains($pesan, '1 KRS'));

    expect(RiwayatDosenWali::sole())
        ->dosen_lama_id->toBe($this->dosenA->id)
        ->krs_dialihkan->toBe(1);

    Notification::assertSentTo($this->dosenB, DosenWaliDitetapkan::class);

    // The old advisor no longer sees it and may not decide it.
    $this->actingAs($this->dosenA, 'dosen')
        ->get('/dosen/persetujuan-krs')->assertOk()->assertDontSee($krs->uuid);
    $this->post("/dosen/persetujuan-krs/{$krs->uuid}", ['disetujui' => '1'])->assertForbidden();

    // The new advisor does, and may.
    $this->actingAs($this->dosenB, 'dosen')
        ->get('/dosen/persetujuan-krs')->assertOk()->assertSee($krs->uuid);
    $this->post("/dosen/persetujuan-krs/{$krs->uuid}", ['disetujui' => '1'])->assertRedirect();

    expect($krs->fresh())
        ->status->toBe(KrsStatus::Disetujui)
        ->disetujui_by_dosen_id->toBe($this->dosenB->id);
});

it('membagi satu angkatan merata dan tidak mengubah apa pun saat dijalankan ulang', function () {
    Notification::fake();
    $mahasiswa = collect(range(1, 5))->map(fn () => mahasiswaPerwalian($this->prodi));
    mahasiswaPerwalian($this->prodi, ['angkatan' => 2025]); // other cohort, untouched

    $kirim = fn () => $this->actingAs($this->baak, 'staff')->post('/admin/dosen-wali/merata', [
        'prodi' => $this->prodi->id,
        'angkatan' => 2026,
        'dosen' => [$this->dosenA->uuid, $this->dosenB->uuid],
    ]);

    $kirim()->assertRedirect()->assertSessionHas('sukses');

    $sebaran = Mahasiswa::whereIn('id', $mahasiswa->pluck('id'))->pluck('dosen_wali_id')->countBy();
    expect($sebaran->get($this->dosenA->id))->toBe(3)
        ->and($sebaran->get($this->dosenB->id))->toBe(2)
        ->and(Mahasiswa::where('angkatan', 2025)->whereNotNull('dosen_wali_id')->count())->toBe(0)
        ->and(RiwayatDosenWali::where('cara', CaraPenetapanWali::Merata->value)->count())->toBe(5);

    $kirim()->assertSessionHas('sukses', fn (string $pesan): bool => str_contains($pesan, '0 mahasiswa berganti'));
    expect(RiwayatDosenWali::count())->toBe(5);
});

it('mempertahankan dosen wali yang sudah ada selama kuotanya cukup', function () {
    $m1 = mahasiswaPerwalian($this->prodi, ['dosen_wali_id' => $this->dosenB->id]);
    $m2 = mahasiswaPerwalian($this->prodi);

    $this->actingAs($this->baak, 'staff')->post('/admin/dosen-wali/merata', [
        'prodi' => $this->prodi->id,
        'dosen' => [$this->dosenA->uuid, $this->dosenB->uuid],
    ])->assertSessionHas('sukses');

    expect($m1->fresh()->dosen_wali_id)->toBe($this->dosenB->id)
        ->and($m2->fresh()->dosen_wali_id)->toBe($this->dosenA->id);
});

it('menetapkan per baris dan menolak seluruh kiriman bila satu baris tidak sah', function () {
    $m1 = mahasiswaPerwalian($this->prodi);
    $m2 = mahasiswaPerwalian($this->prodi);
    $lain = Dosen::factory()->create();

    $this->actingAs($this->baak, 'staff');

    $this->post('/admin/dosen-wali/massal', ['penetapan' => [
        $m1->uuid => $this->dosenA->uuid,
        $m2->uuid => $lain->uuid,
    ]])->assertSessionHas('galat');

    expect($m1->fresh()->dosen_wali_id)->toBeNull();

    $this->post('/admin/dosen-wali/massal', ['penetapan' => [
        $m1->uuid => $this->dosenA->uuid,
        $m2->uuid => '',
    ]])->assertSessionHas('sukses');

    expect($m1->fresh()->dosen_wali_id)->toBe($this->dosenA->id)
        ->and($m2->fresh()->dosen_wali_id)->toBeNull()
        ->and(RiwayatDosenWali::sole()->cara)->toBe(CaraPenetapanWali::Manual);
});

it('menampilkan layar penetapan dan menolak staf tanpa izin', function () {
    $m = mahasiswaPerwalian($this->prodi, ['dosen_wali_id' => $this->dosenA->id]);

    $this->actingAs($this->baak, 'staff')
        ->get('/admin/dosen-wali?prodi='.$this->prodi->id)
        ->assertOk()
        ->assertSee($m->nim)
        ->assertSee($this->dosenB->nama);

    $keuangan = Staff::factory()->create();
    $keuangan->assignRole('keuangan');

    $this->actingAs($keuangan, 'staff')->get('/admin/dosen-wali')->assertForbidden();
    $this->post("/admin/mahasiswa/{$m->uuid}/dosen-wali", ['dosen' => $this->dosenB->uuid])->assertForbidden();

    expect($m->fresh()->dosen_wali_id)->toBe($this->dosenA->id);
});

it('menampilkan mahasiswa wali di layar bimbingan dosen setelah ditetapkan', function () {
    $m = mahasiswaPerwalian($this->prodi);

    $this->actingAs($this->baak, 'staff')
        ->post("/admin/mahasiswa/{$m->uuid}/dosen-wali", ['dosen' => $this->dosenA->uuid]);

    $this->actingAs($this->dosenA->fresh(), 'dosen')
        ->get('/dosen/bimbingan')
        ->assertOk()
        ->assertSee($m->nim);
});
