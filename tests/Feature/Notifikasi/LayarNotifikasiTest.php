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

    $this->mahasiswa->assignRole('mahasiswa');
});

/** Memberi seseorang satu notifikasi nyata lewat jalur service. */
function beriNotifikasi(Mahasiswa $mahasiswa): void
{
    $cuti = CutiMahasiswa::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'jenis' => 'akademik',
        'alasan' => 'Alasan kesehatan.',
        'status' => LeaveStatus::Diajukan,
        'diajukan_at' => now(),
    ]);

    app(CutiService::class)->setujui($cuti, test()->staf);
}

describe('daftar notifikasi', function () {
    it('merender milik sendiri', function () {
        beriNotifikasi($this->mahasiswa);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('notifikasi'))
            ->assertOk()
            ->assertSee('Pengajuan cuti');
    });

    it('tidak menampilkan notifikasi milik orang lain', function () {
        $lain = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        $lain->assignRole('mahasiswa');
        beriNotifikasi($lain);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('notifikasi'))
            ->assertOk()
            ->assertSee('Belum ada notifikasi');
    });

    it('menandai satu notifikasi sudah dibaca', function () {
        beriNotifikasi($this->mahasiswa);
        $id = DB::table('notifications')->value('id');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('notifikasi.baca', $id))
            ->assertRedirect();

        expect(DB::table('notifications')->whereNotNull('read_at')->count())->toBe(1);
    });

    it('menolak menandai notifikasi milik orang lain', function () {
        /*
         * Kueri dimulai dari relasi milik aktor, bukan dari pengenal di URL,
         * sehingga pengenal milik orang lain sama sekali tidak ditemukan.
         * Bukan pemeriksaan tambahan yang bisa terlupa di satu rute — melainkan
         * bentuk kuerinya sendiri yang membuatnya mustahil.
         */
        $korban = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        $korban->assignRole('mahasiswa');
        beriNotifikasi($korban);

        $id = DB::table('notifications')->value('id');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('notifikasi.baca', $id))
            ->assertNotFound();

        expect(DB::table('notifications')->whereNotNull('read_at')->count())->toBe(0);
    });

    it('menyaring berdasarkan kategori lewat kolom, bukan jalur JSON', function () {
        beriNotifikasi($this->mahasiswa);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('notifikasi', ['kategori' => KategoriNotifikasi::Kemahasiswaan->value]))
            ->assertOk()
            ->assertSee('Pengajuan cuti');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('notifikasi', ['kategori' => KategoriNotifikasi::Keuangan->value]))
            ->assertOk()
            ->assertSee('Belum ada notifikasi');
    });

    it('menulis kategori ke kolomnya sendiri', function () {
        beriNotifikasi($this->mahasiswa);

        expect(DB::table('notifications')->value('kategori'))
            ->toBe(KategoriNotifikasi::Kemahasiswaan->value);
    });
});

describe('layar preferensi', function () {
    it('merender seluruh kategori', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('notifikasi.preferensi'))
            ->assertOk()
            ->assertSee('Selalu aktif')
            ->assertSee(KategoriNotifikasi::Pengingat->label());
    });

    it('menyimpan pematian kategori opsional lewat layar', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->put(route('notifikasi.preferensi.simpan'), [
                'kategori' => [
                    KategoriNotifikasi::Pengingat->value => ['aplikasi' => '0', 'email' => '0'],
                ],
            ])
            ->assertRedirect();

        expect(app(Preferensi::class)->kanalUntuk($this->mahasiswa, KategoriNotifikasi::Pengingat))
            ->toBe([]);
    });

    it('menolak permintaan langsung yang mematikan kategori wajib', function () {
        // Layarnya tidak menawarkan sakelar ini, tetapi permintaan POST langsung
        // bisa mengirimnya. Yang menegakkan aturan adalah service, bukan borang.
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->put(route('notifikasi.preferensi.simpan'), [
                'kategori' => [
                    KategoriNotifikasi::Akademik->value => ['aplikasi' => '0', 'email' => '0'],
                ],
            ])
            ->assertRedirect();

        expect(app(Preferensi::class)->kanalUntuk($this->mahasiswa, KategoriNotifikasi::Akademik))
            ->toBe(['database']);
    });
});

describe('lonceng', function () {
    it('menampilkan jumlah belum dibaca pada topbar', function () {
        beriNotifikasi($this->mahasiswa);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.dashboard'))
            ->assertOk()

            // Jumlahnya masuk ke nama aksesibel, bukan hanya ke lencana warna.
            ->assertSee('Notifikasi, 1 belum dibaca');
    });

    it('tidak menampilkan lencana saat tidak ada yang belum dibaca', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notifikasi"', false);
    });
});
