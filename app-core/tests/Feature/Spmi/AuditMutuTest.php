<?php

declare(strict_types=1);

use App\Enums\JenisUnitKerja;
use App\Enums\StatusTemuan;
use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Models\Spmi\AuditMutu;
use App\Models\Spmi\StandarMutu;
use App\Models\Spmi\TemuanAudit;
use App\Services\Spmi\SpmiService;
use Database\Seeders\RolePermissionSeeder;

/**
 * Audit Mutu Internal.
 *
 * Three refusals separate an audit from a task list, and each has a test here:
 * an auditor may not audit their own unit, a closed finding cannot be edited,
 * and a correction cannot be verified by whoever carried it out.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->spmi = app(SpmiService::class);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');

    $this->auditor = Staff::factory()->create();
    $this->auditor->assignRole('super-admin');

    $this->unit = UnitKerja::create([
        'kode' => 'BAAK',
        'nama' => 'Biro Akademik',
        'jenis' => JenisUnitKerja::Struktural,
    ]);
});

function auditUji(?UnitKerja $unit = null): AuditMutu
{
    return test()->spmi->rencanakanAudit($unit ?? test()->unit, [
        'nama' => 'AMI 2026',
        'tahun' => 2026,
        'auditor_staff_id' => test()->auditor->id,
        'tanggal_audit' => '2026-09-01',
    ]);
}

function temuanUji(AuditMutu $audit, string $jenis = 'mayor'): TemuanAudit
{
    return test()->spmi->catatTemuan($audit, [
        'jenis' => $jenis,
        'uraian' => 'Uraian temuan uji',
    ]);
}

describe('independensi auditor', function () {
    it('menolak auditor mengaudit unitnya sendiri', function () {
        /*
         * Seluruh instrumen bergantung pada ini. Yang mengaudit kantornya
         * sendiri sedang melaporkan pekerjaannya sendiri — dan temuan yang ia
         * angkat terhadap dirinya adalah temuan yang boleh ia tutup sendiri.
         */
        $this->auditor->update(['unit_kerja_id' => $this->unit->id]);

        expect(fn () => auditUji())
            ->toThrow(AturanAkademikException::class, 'unitnya sendiri');
    });

    it('mengizinkan auditor dari unit lain', function () {
        $lain = UnitKerja::create([
            'kode' => 'LPM',
            'nama' => 'Lembaga Penjaminan Mutu',
            'jenis' => JenisUnitKerja::Struktural,
        ]);

        $this->auditor->update(['unit_kerja_id' => $lain->id]);

        expect(auditUji()->exists)->toBeTrue();
    });

    it('dapat dimatikan lewat config untuk kampus tanpa cukup auditor', function () {
        // Dimatikan sebagai keputusan sadar yang tercatat, bukan kelalaian yang
        // tidak pernah terlihat.
        config(['spmi.tolak_audit_unit_sendiri' => false]);

        $this->auditor->update(['unit_kerja_id' => $this->unit->id]);

        expect(auditUji()->exists)->toBeTrue();
    });

    it('menolak audit dengan dua auditor sekaligus', function () {
        expect(fn () => $this->spmi->rencanakanAudit($this->unit, [
            'nama' => 'AMI',
            'tahun' => 2026,
            'auditor_staff_id' => $this->auditor->id,
            'auditor_dosen_id' => Dosen::factory()->create()->id,
            'tanggal_audit' => '2026-09-01',
        ]))->toThrow(AturanAkademikException::class, 'satu auditor saja');
    });

    it('menolak audit tanpa auditor', function () {
        expect(fn () => $this->spmi->rencanakanAudit($this->unit, [
            'nama' => 'AMI',
            'tahun' => 2026,
            'tanggal_audit' => '2026-09-01',
        ]))->toThrow(AturanAkademikException::class, 'harus punya auditor');
    });
});

describe('temuan', function () {
    it('menolak temuan pada audit yang belum berlangsung', function () {
        expect(fn () => temuanUji(auditUji()))
            ->toThrow(AturanAkademikException::class, 'tidak menerima temuan baru');
    });

    it('mengambil tenggat dari beratnya, bukan dari formulir', function () {
        /*
         * Ketidaksesuaian mayor yang diberi sembilan puluh hari oleh yang
         * mengetiknya adalah kampus yang diam-diam menurunkan aturannya sendiri.
         */
        $audit = $this->spmi->mulaiAudit(auditUji());

        $mayor = temuanUji($audit, 'mayor');
        $minor = temuanUji($audit, 'minor');
        $observasi = temuanUji($audit, 'observasi');

        // Dibandingkan sebagai tanggal, bukan selisih hari: `tenggat` di-cast
        // ke tengah malam sementara `now()` punya jam, dan selisihnya jadi
        // pecahan bertanda yang tidak pernah sama dengan bilangan bulat.
        expect($mayor->tenggat->toDateString())->toBe(now()->addDays(30)->toDateString())
            ->and($minor->tenggat->toDateString())->toBe(now()->addDays(90)->toDateString())
            ->and($observasi->tenggat)->toBeNull();
    });

    it('menolak jenis temuan yang tidak dikenal', function () {
        $audit = $this->spmi->mulaiAudit(auditUji());

        expect(fn () => $this->spmi->catatTemuan($audit, [
            'jenis' => 'gawat_darurat',
            'uraian' => 'Sesuatu',
        ]))->toThrow(AturanAkademikException::class, 'tidak dikenal');
    });

    it('menolak menutup ketidaksesuaian tanpa tindak lanjut terverifikasi', function () {
        // Menutup tanpa perbaikan adalah cara sebuah audit berubah jadi
        // administrasi: temuannya hilang dari daftar, dan tidak ada yang
        // berubah di unitnya.
        $audit = $this->spmi->mulaiAudit(auditUji());
        $temuan = temuanUji($audit, 'mayor');

        expect(fn () => $this->spmi->tutupTemuan($temuan, $this->staf))
            ->toThrow(AturanAkademikException::class, 'terverifikasi');
    });

    it('mengizinkan menutup observasi tanpa tindak lanjut', function () {
        /*
         * Mewajibkan perbaikan untuk observasi membuat auditor berhenti
         * menuliskannya — dan justru catatan ringan itulah yang paling sering
         * berguna tahun berikutnya.
         */
        $audit = $this->spmi->mulaiAudit(auditUji());
        $temuan = temuanUji($audit, 'observasi');

        expect($this->spmi->tutupTemuan($temuan, $this->staf)->status)
            ->toBe(StatusTemuan::Ditutup);
    });

    it('menolak menyunting temuan yang sudah ditutup', function () {
        /*
         * Inilah yang menjadikannya SPMI. Temuan yang dapat diubah setelah
         * ditutup adalah temuan yang dapat dihaluskan menjelang asesmen
         * lapangan.
         */
        $audit = $this->spmi->mulaiAudit(auditUji());
        $temuan = $this->spmi->tutupTemuan(temuanUji($audit, 'saran'), $this->staf);

        expect(fn () => $this->spmi->perbaruiTemuan($temuan, ['uraian' => 'Diperhalus']))
            ->toThrow(AturanAkademikException::class, 'tidak dapat diubah');
    });

    it('menutup audit tanpa ikut menutup temuannya', function () {
        // Temuan lazim berumur lebih panjang daripada auditnya — perbaikannya
        // berjalan berminggu-minggu sesudahnya.
        $audit = $this->spmi->mulaiAudit(auditUji());
        $temuan = temuanUji($audit, 'mayor');

        $this->spmi->tutupAudit($audit->refresh(), 'Ringkasan');

        expect($temuan->refresh()->status)->toBe(StatusTemuan::Terbuka);
    });
});

describe('tindak lanjut', function () {
    it('memindahkan temuan ke ditindaklanjuti saat rencana dicatat', function () {
        $audit = $this->spmi->mulaiAudit(auditUji());
        $temuan = temuanUji($audit, 'mayor');

        $this->spmi->catatTindakLanjut($temuan, ['rencana' => 'Menyusun SOP baru'], $this->staf);

        expect($temuan->refresh()->status)->toBe(StatusTemuan::Ditindaklanjuti);
    });

    it('menolak verifikasi oleh orang yang mencatatnya sendiri', function () {
        /*
         * Perbaikan yang diverifikasi pelaksananya bukan verifikasi — ia hanya
         * pernyataan kedua dari orang yang sama, dan jejaknya tidak dapat
         * membedakan keduanya sesudahnya.
         */
        $audit = $this->spmi->mulaiAudit(auditUji());
        $temuan = temuanUji($audit, 'mayor');

        $tindak = $this->spmi->catatTindakLanjut($temuan, [
            'rencana' => 'Menyusun SOP baru',
            'realisasi' => 'SOP terbit',
        ], $this->staf);

        expect(fn () => $this->spmi->verifikasiTindakLanjut($tindak, $this->staf))
            ->toThrow(AturanAkademikException::class, 'mencatatnya sendiri');
    });

    it('menolak verifikasi sebelum ada realisasi', function () {
        $audit = $this->spmi->mulaiAudit(auditUji());
        $temuan = temuanUji($audit, 'mayor');

        $tindak = $this->spmi->catatTindakLanjut($temuan, ['rencana' => 'Rencana saja'], $this->staf);

        expect(fn () => $this->spmi->verifikasiTindakLanjut($tindak, $this->auditor))
            ->toThrow(AturanAkademikException::class, 'belum punya realisasi');
    });

    it('menutup ketidaksesuaian setelah tindak lanjut diverifikasi orang lain', function () {
        $audit = $this->spmi->mulaiAudit(auditUji());
        $temuan = temuanUji($audit, 'mayor');

        $tindak = $this->spmi->catatTindakLanjut($temuan, [
            'rencana' => 'Menyusun SOP baru',
            'realisasi' => 'SOP terbit dan disosialisasikan',
        ], $this->staf);

        $this->spmi->verifikasiTindakLanjut($tindak, $this->auditor, 'Bukti memadai');

        expect($this->spmi->tutupTemuan($temuan->refresh(), $this->staf)->status)
            ->toBe(StatusTemuan::Ditutup);
    });
});

describe('rekap', function () {
    it('menghitung temuan terbuka per jenis dan yang terlambat', function () {
        $audit = $this->spmi->mulaiAudit(auditUji());

        temuanUji($audit, 'mayor');
        temuanUji($audit, 'minor');
        temuanUji($audit, 'observasi');

        // Satu temuan yang tenggatnya lewat.
        TemuanAudit::where('jenis', 'mayor')->update(['tenggat' => now()->subDay()]);

        $rekap = $this->spmi->rekapTemuan(2026);

        expect($rekap['mayor'])->toBe(1)
            ->and($rekap['minor'])->toBe(1)
            ->and($rekap['observasi'])->toBe(1)
            ->and($rekap['terlambat'])->toBe(1);
    });

    it('tidak menghitung temuan yang sudah ditutup', function () {
        $audit = $this->spmi->mulaiAudit(auditUji());

        $this->spmi->tutupTemuan(temuanUji($audit, 'saran'), $this->staf);

        expect($this->spmi->rekapTemuan(2026)['saran'])->toBe(0);
    });
});

describe('standar mutu', function () {
    it('menyimpan pernyataan terpisah dari namanya', function () {
        /*
         * Sebuah standar dirujuk dengan namanya dan diaudit dengan
         * pernyataannya. Auditor yang harus menyimpulkan pernyataan dari nama
         * akan menyimpulkannya berbeda dari auditor berikutnya.
         */
        $standar = StandarMutu::create([
            'kode' => 'SM-01',
            'nama' => 'Standar Kompetensi Lulusan',
            'pernyataan' => 'Setiap prodi menetapkan CPL yang ditinjau setiap dua tahun.',
            'kategori' => 'pendidikan',
        ]);

        expect($standar->pernyataan)->toContain('ditinjau setiap dua tahun')
            ->and($standar->siklusLabel())->toBe('Penetapan')
            ->and($standar->melampaui_sndikti)->toBeFalse();
    });
});
