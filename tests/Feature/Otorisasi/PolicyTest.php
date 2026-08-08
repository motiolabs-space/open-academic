<?php

declare(strict_types=1);

use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;

/**
 * Guard separation says which portal you are in. These tests cover what you
 * may do once inside it — the part that guards do not answer.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Portal dijaga middleware term.active; tanpa semester aktif seluruh
    // rutenya membalas 503 sebelum policy sempat dievaluasi.
    termUji();
    Portal::lupakanTerm();
});

/** Satu semester aktif dipakai bersama seluruh tes — kodenya unik per instalasi. */
function termUji(): TahunAkademik
{
    return TahunAkademik::firstWhere('kode', '20261')
        ?? TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
}

function tagihanMilik(Mahasiswa $mahasiswa): Tagihan
{
    return Tagihan::create([
        'nomor' => 'INV/'.$mahasiswa->id,
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => termUji()->id,
        'keterangan' => 'Uji',
        'total' => 1_000_000,
        'jatuh_tempo' => now()->addWeek(),
    ]);
}

describe('tagihan', function () {
    it('membolehkan mahasiswa melihat tagihannya sendiri', function () {
        $mahasiswa = Mahasiswa::factory()->create();
        $mahasiswa->assignRole('mahasiswa');

        expect(Gate::forUser($mahasiswa)->allows('view', tagihanMilik($mahasiswa)))->toBeTrue();
    });

    it('menolak mahasiswa melihat tagihan mahasiswa lain', function () {
        $saya = Mahasiswa::factory()->create();
        $saya->assignRole('mahasiswa');
        $oranglain = Mahasiswa::factory()->create();

        expect(Gate::forUser($saya)->allows('view', tagihanMilik($oranglain)))->toBeFalse();
    });

    it('menolak dosen melihat tagihan mahasiswa bimbingannya', function () {
        $dosen = Dosen::factory()->create();
        $dosen->assignRole('dosen-wali');

        $mahasiswa = Mahasiswa::factory()->create(['dosen_wali_id' => $dosen->id]);

        // Golongan UKT mencerminkan penghasilan keluarga — bukan urusan wali akademik.
        expect(Gate::forUser($dosen)->allows('view', tagihanMilik($mahasiswa)))->toBeFalse();
    });

    it('menolak staf tanpa izin keuangan', function () {
        $baak = Staff::factory()->create();
        $baak->assignRole('baak');

        expect(Gate::forUser($baak)->allows('view', tagihanMilik(Mahasiswa::factory()->create())))->toBeFalse();
    });

    it('membolehkan staf keuangan', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        expect(Gate::forUser($keuangan)->allows('view', tagihanMilik(Mahasiswa::factory()->create())))->toBeTrue();
    });
});

describe('persetujuan KRS', function () {
    function krsMilik(Mahasiswa $mahasiswa): Krs
    {
        return Krs::factory()->diajukan()->create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => termUji()->id,
        ]);
    }

    it('membolehkan dosen wali menyetujui KRS mahasiswa bimbingannya', function () {
        $wali = Dosen::factory()->create();
        $wali->assignRole('dosen-wali');

        $mahasiswa = Mahasiswa::factory()->create(['dosen_wali_id' => $wali->id]);

        expect(Gate::forUser($wali)->allows('approve', krsMilik($mahasiswa)))->toBeTrue();
    });

    it('menolak dosen wali lain meski punya izin krs.approve', function () {
        $wali = Dosen::factory()->create();
        $waliLain = Dosen::factory()->create();
        $waliLain->assignRole('dosen-wali');

        $mahasiswa = Mahasiswa::factory()->create(['dosen_wali_id' => $wali->id]);

        // Kalau ini lolos, tahap persetujuan tidak berarti apa-apa.
        expect(Gate::forUser($waliLain)->allows('approve', krsMilik($mahasiswa)))->toBeFalse();
    });

    it('menolak dosen pengajar biasa tanpa izin krs.approve', function () {
        $pengajar = Dosen::factory()->create();
        $pengajar->assignRole('dosen');

        $mahasiswa = Mahasiswa::factory()->create(['dosen_wali_id' => $pengajar->id]);

        expect(Gate::forUser($pengajar)->allows('approve', krsMilik($mahasiswa)))->toBeFalse();
    });

    it('menolak mahasiswa menyetujui KRS-nya sendiri', function () {
        $mahasiswa = Mahasiswa::factory()->create();
        $mahasiswa->assignRole('mahasiswa');
        $mahasiswa->update(['dosen_wali_id' => Dosen::factory()->create()->id]);

        expect(Gate::forUser($mahasiswa)->allows('approve', krsMilik($mahasiswa)))->toBeFalse();
    });

    it('mengunci penyuntingan KRS yang sudah disetujui', function () {
        $mahasiswa = Mahasiswa::factory()->create();
        $mahasiswa->assignRole('mahasiswa');

        $krs = krsMilik($mahasiswa);
        $krs->update(['status' => KrsStatus::Disetujui]);

        expect(Gate::forUser($mahasiswa)->allows('update', $krs))->toBeFalse();
    });
});

describe('nilai', function () {
    function nilaiKelas(Dosen $pengampu, Mahasiswa $mahasiswa, bool $final): Nilai
    {
        $term = termUji();
        $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $term->id]);
        $kelas->dosen()->attach($pengampu->id, ['peran' => 'pengampu']);

        // Satu KRS per mahasiswa per semester — tes yang memanggil helper ini
        // dua kali untuk mahasiswa yang sama menambah kelas, bukan KRS baru.
        $krs = Krs::firstOrCreate(
            ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $term->id],
            ['semester_ke' => 1, 'batas_sks' => 24],
        );

        $detail = KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_kuliah_id' => $kelas->id,
            'sks' => 3,
        ]);

        return Nilai::create([
            'krs_detail_id' => $detail->id,
            'kelas_kuliah_id' => $kelas->id,
            'mahasiswa_id' => $mahasiswa->id,
            'nilai_angka' => 82,
            'nilai_huruf' => 'A',
            'bobot' => 4,
            'is_final' => $final,
        ]);
    }

    it('membolehkan dosen pengampu mengisi nilai yang belum final', function () {
        $pengampu = Dosen::factory()->create();
        $pengampu->assignRole('dosen');

        $nilai = nilaiKelas($pengampu, Mahasiswa::factory()->create(), final: false);

        expect(Gate::forUser($pengampu)->allows('update', $nilai))->toBeTrue();
    });

    it('mengunci nilai yang sudah final bahkan dari dosen pengampunya', function () {
        $pengampu = Dosen::factory()->create();
        $pengampu->assignRole('dosen');

        $nilai = nilaiKelas($pengampu, Mahasiswa::factory()->create(), final: true);

        // Perbaikan nilai final hanya lewat jalur koreksi ter-audit.
        expect(Gate::forUser($pengampu)->allows('update', $nilai))->toBeFalse();
    });

    it('menolak dosen lain mengisi nilai kelas yang tidak diampunya', function () {
        $pengampu = Dosen::factory()->create();
        $dosenLain = Dosen::factory()->create();
        $dosenLain->assignRole('dosen');

        $nilai = nilaiKelas($pengampu, Mahasiswa::factory()->create(), final: false);

        expect(Gate::forUser($dosenLain)->allows('update', $nilai))->toBeFalse();
    });

    it('menolak dosen membuka kembali nilai final lewat jalur koreksi', function () {
        $pengampu = Dosen::factory()->create();
        $pengampu->assignRole('dosen');

        $nilai = nilaiKelas($pengampu, Mahasiswa::factory()->create(), final: true);

        expect(Gate::forUser($pengampu)->allows('correct', $nilai))->toBeFalse();
    });

    it('membolehkan mahasiswa melihat nilainya sendiri, bukan milik orang lain', function () {
        $saya = Mahasiswa::factory()->create();
        $saya->assignRole('mahasiswa');
        $oranglain = Mahasiswa::factory()->create();

        $pengampu = Dosen::factory()->create();

        expect(Gate::forUser($saya)->allows('view', nilaiKelas($pengampu, $saya, final: true)))->toBeTrue()
            ->and(Gate::forUser($saya)->allows('view', nilaiKelas($pengampu, $oranglain, final: true)))->toBeFalse();
    });
});

describe('rute admin', function () {
    it('menolak staf tanpa peran sama sekali', function () {
        // Akun staf yang sudah dibuat tapi belum diberi peran tidak boleh
        // otomatis melihat data mahasiswa hanya karena berhasil masuk.
        $tanpaPeran = Staff::factory()->create();

        $this->actingAs($tanpaPeran, 'staff')
            ->get('/admin/mahasiswa')
            ->assertForbidden();
    });

    it('mengizinkan BAAK membuka daftar mahasiswa', function () {
        $baak = Staff::factory()->create();
        $baak->assignRole('baak');

        $this->actingAs($baak, 'staff')
            ->get('/admin/mahasiswa')
            ->assertOk();
    });

    it('mengizinkan staf keuangan mencari mahasiswa untuk urusan tagihan', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')
            ->get('/admin/mahasiswa')
            ->assertOk();
    });
});
