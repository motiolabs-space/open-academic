<?php

declare(strict_types=1);

use App\DTOs\Kemahasiswaan\DataSimkatmawa;
use App\DTOs\Kemahasiswaan\FilterSimkatmawa;
use App\DTOs\Kemahasiswaan\LaporanPrestasiData;
use App\Enums\BidangLomba;
use App\Enums\JenisPoin;
use App\Enums\KategoriSimkatmawa;
use App\Enums\PeringkatPrestasi;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Sdm\Staff;
use App\Notifications\Kemahasiswaan\LaporanPrestasiDiputus;
use App\Services\Feeder\Mappers\PrestasiMapper;
use App\Services\Kemahasiswaan\EksporSimkatmawa;
use App\Services\Kemahasiswaan\PoinKemahasiswaanService;
use App\Support\Portal;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Students reporting their own achievements, and reading their own conduct
 * record (ADR-040 §3). Until now both lived only on the staff screen.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    Notification::fake();

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    Portal::lupakanTerm();

    $this->mahasiswa = Mahasiswa::factory()->create(['status' => StudentStatus::Aktif]);
    $this->mahasiswa->assignRole('mahasiswa');
    $this->lain = Mahasiswa::factory()->create(['status' => StudentStatus::Aktif]);
    $this->lain->assignRole('mahasiswa');

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->kompetisi = PoinKategori::create([
        'kode' => 'PRS-NAS',
        'nama' => 'Juara Lomba Nasional',
        'jenis' => JenisPoin::Prestasi,
        'tingkat' => 'nasional',
        'poin' => 40,
        'wajib_bukti' => true,
        'simkatmawa_kategori' => KategoriSimkatmawa::Kompetisi,
    ]);

    $this->pelanggaran = PoinKategori::create([
        'kode' => 'PLG-RINGAN',
        'nama' => 'Terlambat Berulang',
        'jenis' => JenisPoin::Pelanggaran,
        'tingkat' => 'ringan',
        'poin' => 5,
        'wajib_bukti' => false,
    ]);

    $this->layanan = app(PoinKemahasiswaanService::class);
});

function laporanPrestasiUji(?UploadedFile $bukti = null): LaporanPrestasiData
{
    return new LaporanPrestasiData(
        tanggal: '2026-09-12',
        judul: 'Juara 1 Kompetisi Data Nasional',
        keterangan: 'Tim tiga orang.',
        tahunAkademikId: test()->term->id,
        bukti: $bukti ?? UploadedFile::fake()->create('piagam.pdf', 30, 'application/pdf'),
        simkatmawa: new DataSimkatmawa(
            peringkat: PeringkatPrestasi::Juara1,
            bidangLomba: BidangLomba::RisetStem,
            penyelenggara: 'Asosiasi Data Demo',
        ),
    );
}

describe('lapor prestasi', function () {
    it('masuk antrean verifikasi yang sama tanpa terhitung', function () {
        $baris = $this->layanan->laporkan($this->mahasiswa, $this->kompetisi, laporanPrestasiUji());

        expect($baris->is_verified)->toBeFalse()
            ->and($baris->dilaporkan_by_mahasiswa_id)->toBe($this->mahasiswa->id)
            ->and($baris->dicatat_by_staff_id)->toBeNull()
            ->and($baris->poin)->toBe(40)
            ->and($baris->peringkat)->toBe(PeringkatPrestasi::Juara1)
            ->and($baris->penyelenggara)->toBe('Asosiasi Data Demo')
            ->and($baris->bukti_path)->toStartWith('prestasi/'.$this->mahasiswa->uuid.'/')
            ->and($this->layanan->antrean()->pluck('id')->all())->toContain($baris->id)
            ->and($this->layanan->rekap($this->mahasiswa)['prestasi'])->toBe(0);

        Storage::disk('local')->assertExists($baris->bukti_path);
    });

    it('tetap mewajibkan bukti bila katalog memintanya dan tidak menyimpan berkas yatim', function () {
        $data = new LaporanPrestasiData('2026-09-12', 'Juara tanpa bukti', null, null, null, new DataSimkatmawa);

        expect(fn () => $this->layanan->laporkan($this->mahasiswa, $this->kompetisi, $data))
            ->toThrow(AturanAkademikException::class, 'bukti');

        $this->kompetisi->update(['is_active' => false]);

        expect(fn () => $this->layanan->laporkan($this->mahasiswa, $this->kompetisi->refresh(), laporanPrestasiUji()))
            ->toThrow(AturanAkademikException::class, 'tidak aktif');

        expect(PoinMahasiswa::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('tidak menerima laporan pelanggaran atas diri sendiri', function () {
        expect(fn () => $this->layanan->laporkan($this->mahasiswa, $this->pelanggaran, laporanPrestasiUji()))
            ->toThrow(AturanAkademikException::class);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.poin.lapor'), [
                'kategori' => $this->pelanggaran->uuid,
                'tanggal' => '2026-09-12',
                'judul' => 'Mencoba kategori pelanggaran',
            ])
            ->assertSessionHasErrors('kategori');

        expect(PoinMahasiswa::count())->toBe(0);
    });

    it('melaporkan lewat portal dengan rincian SIMKATMAWA yang divalidasi seperti layar staf', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.poin.lapor'), [
                'kategori' => $this->kompetisi->uuid,
                'tanggal' => '2026-09-12',
                'judul' => 'Juara 1 Kompetisi Data Nasional',
                'bukti' => UploadedFile::fake()->create('piagam.pdf', 30, 'application/pdf'),
                'tautan_bukti' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('tautan_bukti');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.poin.lapor'), [
                'kategori' => $this->kompetisi->uuid,
                'tanggal' => '2026-09-12',
                'judul' => 'Juara 1 Kompetisi Data Nasional',
                'bukti' => UploadedFile::fake()->create('piagam.pdf', 30, 'application/pdf'),
                'peringkat' => PeringkatPrestasi::Juara2->value,
                'tautan_bukti' => 'https://berita.demo.test/juara',
                // Diabaikan: pelapor selalu yang sedang masuk.
                'mahasiswa_id' => $this->lain->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $baris = PoinMahasiswa::sole();

        expect($baris->mahasiswa_id)->toBe($this->mahasiswa->id)
            ->and($baris->peringkat)->toBe(PeringkatPrestasi::Juara2)
            ->and($baris->tautan_bukti)->toBe('https://berita.demo.test/juara');
    });

    it('menolak tanggal kegiatan di masa depan', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.poin.lapor'), [
                'kategori' => $this->kompetisi->uuid,
                'tanggal' => now()->addWeek()->toDateString(),
                'judul' => 'Lomba yang belum terjadi',
            ])
            ->assertSessionHasErrors('tanggal');
    });
});

describe('keputusan staf', function () {
    it('memberi tahu pelapor saat diverifikasi, lalu baru terhitung dan terkirim ke SIMKATMAWA dan Feeder', function () {
        $this->mahasiswa->update(['feeder_id' => 'feeder-demo-1']);
        $baris = $this->layanan->laporkan($this->mahasiswa, $this->kompetisi, laporanPrestasiUji());

        $filter = new FilterSimkatmawa(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
        $ekspor = app(EksporSimkatmawa::class);
        $mapper = app(PrestasiMapper::class);

        expect($ekspor->tabel($filter)[KategoriSimkatmawa::Kompetisi->value])->toHaveCount(0)
            ->and($mapper->jumlah($this->term->kode))->toBe(0);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.poin-kemahasiswaan.verifikasi', $baris))
            ->assertRedirect();

        expect($baris->refresh()->is_verified)->toBeTrue()
            ->and($this->layanan->rekap($this->mahasiswa)['prestasi'])->toBe(40)
            ->and($ekspor->tabel($filter)[KategoriSimkatmawa::Kompetisi->value])->toHaveCount(1)
            ->and($mapper->jumlah($this->term->kode))->toBe(1);

        Notification::assertSentTo($this->mahasiswa, LaporanPrestasiDiputus::class,
            fn (LaporanPrestasiDiputus $n): bool => $n->tone() === 'success');
    });

    it('memberi tahu pelapor saat ditolak beserta alasannya', function () {
        $baris = $this->layanan->laporkan($this->mahasiswa, $this->kompetisi, laporanPrestasiUji());

        $this->layanan->tolak($baris, $this->baak, 'Piagam tidak terbaca.');

        Notification::assertSentTo($this->mahasiswa, LaporanPrestasiDiputus::class,
            fn (LaporanPrestasiDiputus $n): bool => str_contains($n->ringkasan($this->mahasiswa), 'Piagam tidak terbaca.'));

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.poin'))
            ->assertOk()
            ->assertSee('Piagam tidak terbaca.');
    });

    it('tidak mengirim notifikasi untuk catatan yang dicatat staf', function () {
        $baris = $this->layanan->catat($this->mahasiswa, $this->pelanggaran, [
            'tanggal' => '2026-09-01',
            'judul' => 'Terlambat lima kali',
        ], $this->baak);

        $this->layanan->verifikasi($baris, $this->baak);

        Notification::assertNothingSent();
    });

    it('menandai laporan mahasiswa di antrean staf beserta tautan buktinya', function () {
        $baris = $this->layanan->laporkan($this->mahasiswa, $this->kompetisi, laporanPrestasiUji());

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.poin-kemahasiswaan'))
            ->assertOk()
            ->assertSee('data-dilaporkan-mahasiswa', false)
            ->assertSee(route('berkas.poin', $baris), false);
    });
});

describe('layar poin dan batas otorisasi', function () {
    it('menampilkan prestasi dan pelanggaran sendiri berdampingan, tanpa milik orang lain', function () {
        $milikSendiri = $this->layanan->catat($this->mahasiswa, $this->pelanggaran, [
            'tanggal' => '2026-09-01',
            'judul' => 'Terlambat lima kali',
        ], $this->baak);
        $this->layanan->verifikasi($milikSendiri, $this->baak);

        $milikLain = $this->layanan->laporkan($this->lain, $this->kompetisi, laporanPrestasiUji());

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.poin'))
            ->assertOk()
            ->assertSee($milikSendiri->uuid)
            ->assertSee('Terlambat lima kali')
            ->assertDontSee($milikLain->uuid);
    });

    it('membuka bukti sendiri tetapi tidak milik mahasiswa lain', function () {
        $milikLain = $this->layanan->laporkan($this->lain, $this->kompetisi, laporanPrestasiUji());

        $this->actingAs($this->mahasiswa, 'mahasiswa')->get(route('berkas.poin', $milikLain))->assertForbidden();
        $this->actingAs($this->lain, 'mahasiswa')->get(route('berkas.poin', $milikLain))->assertOk();
        $this->actingAs($this->baak, 'staff')->get(route('berkas.poin', $milikLain))->assertOk();
    });

    it('tidak membolehkan mahasiswa memverifikasi laporannya sendiri', function () {
        $baris = $this->layanan->laporkan($this->mahasiswa, $this->kompetisi, laporanPrestasiUji());

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('admin.poin-kemahasiswaan.verifikasi', $baris))
            ->assertRedirect();

        expect($baris->refresh()->is_verified)->toBeFalse();
    });

    it('menolak mahasiswa tanpa izin prestasi.lapor', function () {
        $this->mahasiswa->roles()->first()->revokePermissionTo('prestasi.lapor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->mahasiswa->refresh(), 'mahasiswa')
            ->post(route('mahasiswa.poin.lapor'), [
                'kategori' => $this->kompetisi->uuid,
                'tanggal' => '2026-09-12',
                'judul' => 'Juara 1 Kompetisi Data Nasional',
            ])
            ->assertForbidden();
    });
});
