<?php

declare(strict_types=1);

use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;

/**
 * The KRS round trip over HTTP: the student fills and submits, the advisor
 * decides, the student sees the outcome. The service is tested separately —
 * these cover wiring, authorisation and what actually reaches the screen.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    Portal::lupakanTerm();

    $prodi = Prodi::factory()->create();
    $this->kurikulum = Kurikulum::factory()->create(['prodi_id' => $prodi->id]);

    $this->wali = Dosen::factory()->create(['prodi_id' => $prodi->id]);
    $this->wali->assignRole('dosen-wali');

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $prodi->id,
        'kurikulum_id' => $this->kurikulum->id,
        'dosen_wali_id' => $this->wali->id,
        'status' => StudentStatus::Aktif,
    ]);
    $this->mahasiswa->assignRole('mahasiswa');

    $this->kelas = kelasUji($prodi->id, $this->kurikulum, $this->term->id);
});

function kelasUji(int $prodiId, Kurikulum $kurikulum, int $termId, string $kode = 'A', int $jam = 7): KelasKuliah
{
    $mk = MataKuliah::factory()->create(['prodi_id' => $prodiId, 'sks' => 3]);
    $kurikulum->mataKuliah()->attach($mk->id, ['semester' => 1, 'jenis' => 'wajib']);

    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $termId,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $prodiId,
        'sks' => 3,
        'kode' => $kode,
        'kuota' => 40,
    ]);

    JadwalKuliah::create([
        'kelas_kuliah_id' => $kelas->id,
        'hari' => 1,
        'jam_mulai' => sprintf('%02d:00:00', $jam),
        'jam_selesai' => sprintf('%02d:30:00', $jam + 2),
    ]);

    return $kelas;
}

it('membuka layar KRS dan membuat rencana studi pada kunjungan pertama', function () {
    $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/krs')
        ->assertOk()
        ->assertSee('Rencana Studi');

    expect(Krs::where('mahasiswa_id', $this->mahasiswa->id)->exists())->toBeTrue();
});

it('menambah dan mengeluarkan kelas lewat layar', function () {
    $this->actingAs($this->mahasiswa, 'mahasiswa')->get('/mahasiswa/krs');

    $this->post("/mahasiswa/krs/kelas/{$this->kelas->uuid}")->assertRedirect();

    $krs = Krs::where('mahasiswa_id', $this->mahasiswa->id)->firstOrFail();
    expect($krs->total_sks)->toBe(3)
        ->and($this->kelas->fresh()->terisi)->toBe(1);

    $this->delete('/mahasiswa/krs/detail/'.$krs->detail()->first()->uuid)->assertRedirect();

    expect($krs->fresh()->total_sks)->toBe(0)
        ->and($this->kelas->fresh()->terisi)->toBe(0);
});

it('menampilkan alasan penolakan aturan tanpa membuat halaman meledak', function () {
    $this->actingAs($this->mahasiswa, 'mahasiswa')->get('/mahasiswa/krs');

    $penuh = kelasUji($this->mahasiswa->prodi_id, $this->kurikulum, $this->term->id, 'B', 13);
    $penuh->update(['kuota' => 1, 'terisi' => 1]);

    $this->post("/mahasiswa/krs/kelas/{$penuh->uuid}")
        ->assertRedirect()
        ->assertSessionHas('galat', fn (string $pesan): bool => str_contains($pesan, 'Kuota'));
});

it('menolak mahasiswa menyunting rencana studi mahasiswa lain', function () {
    $this->actingAs($this->mahasiswa, 'mahasiswa')->get('/mahasiswa/krs');
    $this->post("/mahasiswa/krs/kelas/{$this->kelas->uuid}");

    $krs = Krs::where('mahasiswa_id', $this->mahasiswa->id)->firstOrFail();
    $detail = $krs->detail()->first();

    $penyusup = Mahasiswa::factory()->create(['prodi_id' => $this->mahasiswa->prodi_id]);
    $penyusup->assignRole('mahasiswa');

    $this->actingAs($penyusup, 'mahasiswa')
        ->delete("/mahasiswa/krs/detail/{$detail->uuid}")
        ->assertForbidden();

    expect($krs->fresh()->total_sks)->toBe(3);
});

it('menjalankan satu putaran penuh: ajukan, tolak, sunting ulang, setujui', function () {
    // Mahasiswa mengisi dan mengajukan.
    $this->actingAs($this->mahasiswa, 'mahasiswa')->get('/mahasiswa/krs');
    $this->post("/mahasiswa/krs/kelas/{$this->kelas->uuid}");
    $this->post('/mahasiswa/krs/ajukan')->assertSessionHas('krs_diajukan');

    $krs = Krs::where('mahasiswa_id', $this->mahasiswa->id)->firstOrFail();
    expect($krs->status)->toBe(KrsStatus::Diajukan);

    // Dosen wali menolak, wajib disertai catatan.
    $this->actingAs($this->wali, 'dosen')
        ->post("/dosen/persetujuan-krs/{$krs->uuid}", ['disetujui' => '0'])
        ->assertSessionHasErrors('catatan');

    $this->post("/dosen/persetujuan-krs/{$krs->uuid}", [
        'disetujui' => '0',
        'catatan' => 'Kurangi beban SKS semester ini.',
    ])->assertRedirect();

    expect($krs->fresh()->status)->toBe(KrsStatus::Ditolak);

    // Mahasiswa melihat catatannya dan dapat menyunting kembali.
    $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/krs')
        ->assertOk()
        ->assertSee('Kurangi beban SKS semester ini.');

    $this->post('/mahasiswa/krs/ajukan')->assertRedirect();

    // Kali ini disetujui.
    $this->actingAs($this->wali, 'dosen')
        ->post("/dosen/persetujuan-krs/{$krs->uuid}", ['disetujui' => '1'])
        ->assertRedirect();

    expect($krs->fresh()->status)->toBe(KrsStatus::Disetujui);

    // Rencana studi yang disetujui terkunci dari penyuntingan.
    $lain = kelasUji($this->mahasiswa->prodi_id, $this->kurikulum, $this->term->id, 'C', 15);

    $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->post("/mahasiswa/krs/kelas/{$lain->uuid}")
        ->assertForbidden();
});

it('menolak dosen yang bukan wali membuka antrean persetujuan', function () {
    $pengajarBiasa = Dosen::factory()->create();
    $pengajarBiasa->assignRole('dosen');

    $this->actingAs($pengajarBiasa, 'dosen')
        ->get('/dosen/persetujuan-krs')
        ->assertForbidden();
});

it('hanya menampilkan mahasiswa bimbingan sendiri di antrean', function () {
    $this->actingAs($this->mahasiswa, 'mahasiswa')->get('/mahasiswa/krs');
    $this->post("/mahasiswa/krs/kelas/{$this->kelas->uuid}");
    $this->post('/mahasiswa/krs/ajukan');

    $waliLain = Dosen::factory()->create();
    $waliLain->assignRole('dosen-wali');

    $this->actingAs($waliLain, 'dosen')
        ->get('/dosen/persetujuan-krs')
        ->assertOk()
        ->assertDontSee($this->mahasiswa->nama)
        ->assertSee('Antrean persetujuan kosong');
});
