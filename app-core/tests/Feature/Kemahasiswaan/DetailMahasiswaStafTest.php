<?php

declare(strict_types=1);

use App\Enums\GradeLetter;
use App\Enums\InvoiceStatus;
use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;

/**
 * Before this page, Admin\MahasiswaController was a list and nothing else:
 * staff could not open one student, and KHS and transcript existed only in
 * the student's own portal.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2025, SemesterType::Ganjil)->create();
    $this->aktif = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    Portal::lupakanTerm();

    $this->prodi = Prodi::factory()->create();
    $this->wali = Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'nama' => 'Siti Wali Akademik']);

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'dosen_wali_id' => $this->wali->id,
        'status' => StudentStatus::Aktif,
        'nik' => '9900112233445566',
    ]);

    // One closed term with one final grade.
    $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'kode' => 'IF101', 'nama' => 'Algoritma Dasar', 'sks' => 3]);
    $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id, 'mata_kuliah_id' => $mk->id, 'prodi_id' => $this->prodi->id, 'sks' => 3]);
    $krs = Krs::factory()->create(['mahasiswa_id' => $this->mahasiswa->id, 'tahun_akademik_id' => $this->term->id, 'status' => KrsStatus::Disetujui, 'total_sks' => 3]);
    $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 3]);
    Nilai::create([
        'krs_detail_id' => $detail->id,
        'mahasiswa_id' => $this->mahasiswa->id,
        'kelas_kuliah_id' => $kelas->id,
        'nilai_angka' => 85,
        'nilai_huruf' => GradeLetter::A,
        'bobot' => 4,
        'is_final' => true,
        'finalized_at' => now(),
    ]);
    StatusMahasiswa::create([
        'mahasiswa_id' => $this->mahasiswa->id,
        'tahun_akademik_id' => $this->term->id,
        'status' => StudentStatus::Aktif,
        'semester_ke' => 1,
        'sks_semester' => 3,
        'sks_kumulatif' => 3,
        'ips' => 4.00,
        'ipk' => 3.75,
        'is_final' => true,
    ]);

    Tagihan::create([
        'nomor' => 'INV/DTL/0001',
        'mahasiswa_id' => $this->mahasiswa->id,
        'tahun_akademik_id' => $this->aktif->id,
        'keterangan' => 'Biaya kuliah',
        'total' => 4_850_000,
        'terbayar' => 0,
        'status' => InvoiceStatus::BelumBayar,
        'jatuh_tempo' => now()->addWeek(),
    ]);

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');
});

it('menampilkan detail mahasiswa untuk BAAK beserta KRS, KHS, wali, dan kesiapan PDDIKTI', function () {
    $this->actingAs($this->baak, 'staff')
        ->get("/admin/mahasiswa/{$this->mahasiswa->uuid}")
        ->assertOk()
        ->assertSee($this->mahasiswa->nim)
        ->assertSee('Siti Wali Akademik')
        ->assertSee('Algoritma Dasar')
        ->assertSee('data-khs="'.$this->term->kode.'"', false)
        ->assertSee('3,75')
        ->assertSee('data-pddikti=', false)
        // NIK is masked, not printed in full.
        ->assertDontSee('9900112233445566')
        // BAAK holds no finance permission: the invoice section stays out.
        ->assertDontSee('INV/DTL/0001')
        // Identity is changed through the PDM/PJK path, not edited here.
        ->assertSee(__('kemahasiswaan.detail.pddikti_kunci'));
});

it('menampilkan tagihan tetapi tidak nilai bagi staf keuangan', function () {
    $keuangan = Staff::factory()->create();
    $keuangan->assignRole('keuangan');

    $this->actingAs($keuangan, 'staff')
        ->get("/admin/mahasiswa/{$this->mahasiswa->uuid}")
        ->assertOk()
        ->assertSee('INV/DTL/0001')
        ->assertDontSee('data-khs=', false);

    $this->get("/admin/mahasiswa/{$this->mahasiswa->uuid}/transkrip")->assertForbidden();
    $this->get("/admin/mahasiswa/{$this->mahasiswa->uuid}/khs/{$this->term->uuid}")->assertForbidden();
});

it('mencetak KHS dan transkrip PDF dari sisi staf', function () {
    $this->actingAs($this->baak, 'staff');

    $khs = $this->get("/admin/mahasiswa/{$this->mahasiswa->uuid}/khs/{$this->term->uuid}");
    $khs->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($khs->headers->get('content-disposition'))->toContain('KHS-'.$this->mahasiswa->nim.'-'.$this->term->kode.'.pdf');

    $transkrip = $this->get("/admin/mahasiswa/{$this->mahasiswa->uuid}/transkrip");
    $transkrip->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($transkrip->headers->get('content-disposition'))->toContain('Transkrip-'.$this->mahasiswa->nim);
});

it('menolak mencetak KHS semester yang belum ditutup', function () {
    $this->actingAs($this->baak, 'staff')
        ->from("/admin/mahasiswa/{$this->mahasiswa->uuid}")
        ->get("/admin/mahasiswa/{$this->mahasiswa->uuid}/khs/{$this->aktif->uuid}")
        ->assertRedirect()
        ->assertSessionHas('galat');
});

it('mencetak KHS yang sama dari portal mahasiswa', function () {
    $this->mahasiswa->assignRole('mahasiswa');

    $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get("/mahasiswa/khs/{$this->term->uuid}/cetak")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('menolak staf tanpa izin data mahasiswa', function () {
    $sarpras = Staff::factory()->create();
    $sarpras->assignRole('sarpras');

    $this->actingAs($sarpras, 'staff')->get("/admin/mahasiswa/{$this->mahasiswa->uuid}")->assertForbidden();
});

it('menautkan daftar mahasiswa ke halaman detail', function () {
    $this->actingAs($this->baak, 'staff')
        ->get('/admin/mahasiswa')
        ->assertOk()
        ->assertSee(route('admin.mahasiswa.show', $this->mahasiswa), false);
});
