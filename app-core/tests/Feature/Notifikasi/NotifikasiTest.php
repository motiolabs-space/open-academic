<?php

declare(strict_types=1);

use App\Enums\KategoriNotifikasi;
use App\Enums\LeaveStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Models\System\PreferensiNotifikasi;
use App\Services\Kemahasiswaan\CutiService;
use App\Services\Notifikasi\Preferensi;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create();
    $this->staf = Staff::factory()->create();

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);
});

/** Pengajuan cuti yang siap diputus. */
function cutiMenunggu(?Mahasiswa $mahasiswa = null): CutiMahasiswa
{
    return CutiMahasiswa::create([
        'mahasiswa_id' => ($mahasiswa ?? test()->mahasiswa)->id,
        'tahun_akademik_id' => test()->term->id,
        'jenis' => 'akademik',
        'alasan' => 'Alasan kesehatan.',
        'status' => LeaveStatus::Diajukan,
        'diajukan_at' => now(),
    ]);
}

describe('pengiriman', function () {
    it('benar-benar tersimpan meski antrean berjalan setelah commit', function () {
        // Yang diperiksa di sini bukan fitur, melainkan konfigurasi:
        // queue.after_commit menunda job sampai transaksi selesai, sedangkan
        // setiap tes berjalan di dalam transaksi yang dibatalkan. Bila Laravel
        // tidak menangani kombinasi itu, seluruh notifikasi akan senyap di tes
        // dan seluruh berkas ini akan hijau tanpa membuktikan apa pun.
        app(CutiService::class)->setujui(cutiMenunggu(), $this->staf);

        expect(DB::table('notifications')->count())->toBe(1);
    });

    it('mencatat kategori, judul, dan tautan pada muatannya', function () {
        app(CutiService::class)->setujui(cutiMenunggu(), $this->staf);

        $data = json_decode((string) DB::table('notifications')->value('data'), true);

        expect($data['kategori'])->toBe(KategoriNotifikasi::Kemahasiswaan->value)
            ->and($data['judul'])->toContain('cuti')
            ->and($data['tautan'])->not->toBeNull()
            ->and($data['ringkasan'])->toContain($this->term->nama);
    });

    it('mengirim ke pemilik catatan, bukan ke orang lain', function () {
        $lain = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        app(CutiService::class)->setujui(cutiMenunggu(), $this->staf);

        expect(DB::table('notifications')->where('notifiable_id', $this->mahasiswa->id)->count())->toBe(1)
            ->and(DB::table('notifications')->where('notifiable_id', $lain->id)->count())->toBe(0);
    });
});

describe('kegagalan tidak boleh membatalkan kejadiannya', function () {
    it('tetap menyetujui cuti walau pengiriman melempar galat', function () {
        /*
         * Aturan yang paling penting pada modul ini. Peristiwanya sudah
         * terjadi — mahasiswa itu sudah disetujui cutinya. Server surel yang
         * tak terjangkau tidak boleh mengubah kenyataan tersebut.
         *
         * Kegagalan dipaksa dengan menyabot kanalnya: mail driver diarahkan ke
         * transport yang tidak ada, sehingga pengiriman melempar galat di
         * dalam Notifier.
         */
        config(['mail.default' => 'tidak-ada-transport-ini']);

        $cuti = cutiMenunggu();

        app(CutiService::class)->setujui($cuti, $this->staf);

        expect($cuti->fresh()->status)->toBe(LeaveStatus::Disetujui)
            ->and($this->mahasiswa->fresh()->status)->toBe(StudentStatus::Cuti);
    });
});

describe('preferensi', function () {
    it('mengirim ke aplikasi dan surel secara bawaan', function () {
        $kanal = app(Preferensi::class)->kanalUntuk($this->mahasiswa, KategoriNotifikasi::Pengingat);

        expect($kanal)->toBe(['database', 'mail']);
    });

    it('menghormati pematian kategori opsional', function () {
        app(Preferensi::class)->simpan($this->mahasiswa, KategoriNotifikasi::Pengingat, false, false);

        expect(app(Preferensi::class)->kanalUntuk($this->mahasiswa, KategoriNotifikasi::Pengingat))
            ->toBe([]);
    });

    it('menolak mematikan catatan aplikasi untuk kategori wajib', function () {
        // Seseorang yang mematikan semuanya tahun lalu tetap harus bisa
        // menunjuk pemberitahuan bahwa rencana studinya ditolak.
        app(Preferensi::class)->simpan($this->mahasiswa, KategoriNotifikasi::Akademik, false, false);

        expect(app(Preferensi::class)->kanalUntuk($this->mahasiswa, KategoriNotifikasi::Akademik))
            ->toBe(['database'])

            // Dipaksa kembali menyala saat disimpan, bukan sekadar diabaikan
            // saat dibaca: baris yang menyimpan "mati" untuk kategori wajib
            // adalah kebohongan yang akan dibaca layar preferensi.
            ->and(PreferensiNotifikasi::query()
                ->where('kategori', KategoriNotifikasi::Akademik->value)
                ->first()->aplikasi)->toBeTrue();
    });

    it('tetap mengirim kategori wajib walau seluruh preferensi dimatikan', function () {
        foreach (KategoriNotifikasi::cases() as $kategori) {
            app(Preferensi::class)->simpan($this->mahasiswa, $kategori, false, false);
        }

        app(CutiService::class)->setujui(cutiMenunggu(), $this->staf);

        expect(DB::table('notifications')->count())->toBe(1);
    });

    it('mematikan surel tanpa mematikan catatan aplikasi', function () {
        // Pemisahan yang menjadi inti rancangannya: surel adalah kenyamanan
        // pengiriman, catatan dalam aplikasi adalah buktinya.
        app(Preferensi::class)->simpan($this->mahasiswa, KategoriNotifikasi::Pengingat, true, false);

        expect(app(Preferensi::class)->kanalUntuk($this->mahasiswa, KategoriNotifikasi::Pengingat))
            ->toBe(['database']);
    });
});
