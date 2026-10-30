<?php

declare(strict_types=1);

use App\DTOs\Akademik\AkreditasiProdiData;
use App\Enums\KeadaanAkreditasi;
use App\Enums\LembagaAkreditasi;
use App\Enums\PeringkatAkreditasi;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\AkreditasiProdi;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Staff;
use App\Services\Akademik\AkreditasiProdiService;
use App\Services\Kemahasiswaan\KesiapanPisnService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * A programme's accreditation as a dated history (ADR-041 §3): PISN asks
 * which decree was in force on the graduation day, the staff dashboard asks
 * which runs out within the warning window, the LKPS prints the one in force.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake((string) config('berkas.disk'));
    Carbon::setTestNow('2026-10-30 09:00:00');

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => '55299', 'akreditasi' => null]);
    $this->staf = Staff::factory()->create()->assignRole('super-admin');
    $this->layanan = app(AkreditasiProdiService::class);

    $this->data = fn (array $lain = []): AkreditasiProdiData => AkreditasiProdiData::fromArray(array_merge([
        'lembaga' => LembagaAkreditasi::Laminfokom->value,
        'peringkat' => PeringkatAkreditasi::BaikSekali->value,
        'nomor_sk' => 'SK/LAM/001/2024',
        'tanggal_sk' => '2024-03-01',
        'tanggal_kedaluwarsa' => '2029-03-01',
    ], $lain));
});

afterEach(fn () => Carbon::setTestNow());

function skAkreditasi(Prodi $prodi, string $mulai, string $sampai, PeringkatAkreditasi $peringkat = PeringkatAkreditasi::Baik, ?string $nomor = null): AkreditasiProdi
{
    return AkreditasiProdi::create([
        'prodi_id' => $prodi->id,
        'lembaga' => LembagaAkreditasi::BanPt,
        'peringkat' => $peringkat,
        'nomor_sk' => $nomor ?? 'SK/'.$mulai,
        'tanggal_sk' => $mulai,
        'tanggal_berlaku' => $mulai,
        'tanggal_kedaluwarsa' => $sampai,
    ]);
}

/** A graduate with a confirmed graduation on that date — the rest of the checklist is not under test here. */
function lulusanPada(Prodi $prodi, ?string $tanggalLulus): Mahasiswa
{
    $m = Mahasiswa::factory()->create(['prodi_id' => $prodi->id, 'status' => StudentStatus::Lulus]);

    Yudisium::create([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'nomor_sk' => 'SK/YUD/1',
        'tanggal_yudisium' => $tanggalLulus,
        'tanggal_lulus' => $tanggalLulus,
        'total_sks' => 146,
        'ipk' => 3.4,
        'status' => 'ditetapkan',
    ]);

    return $m;
}

function butirAkreditasi(Mahasiswa $m): array
{
    return collect(app(KesiapanPisnService::class)->periksa($m)->rincian)->firstWhere('kode', 'akreditasi');
}

describe('layanan', function () {
    it('catat() menambah SK baru tanpa menimpa yang lama, dan menyegarkan peringkat di prodi', function () {
        skAkreditasi($this->prodi, '2019-03-01', '2024-02-29', PeringkatAkreditasi::B);

        $baru = $this->layanan->catat($this->prodi, ($this->data)(), $this->staf, UploadedFile::fake()->create('sk.pdf', 20, 'application/pdf'));

        expect($this->prodi->riwayatAkreditasi()->count())->toBe(2)
            ->and($baru->tanggal_berlaku->toDateString())->toBe('2024-03-01') // defaults to the decree date
            ->and($baru->dicatat_oleh_staff_id)->toBe($this->staf->id)
            ->and($this->prodi->fresh()->akreditasi)->toBe('Baik Sekali');

        Storage::disk((string) config('berkas.disk'))->assertExists($baru->dokumen_path);
    });

    it('catat() menolak nomor SK yang sudah tercatat untuk prodi yang sama', function () {
        $this->layanan->catat($this->prodi, ($this->data)());

        $this->layanan->catat($this->prodi, ($this->data)());
    })->throws(AturanAkademikException::class);

    it('tarik() menghapus lunak dan mengembalikan peringkat ke SK sebelumnya', function () {
        skAkreditasi($this->prodi, '2019-03-01', '2024-02-29', PeringkatAkreditasi::B);
        $keliru = $this->layanan->catat($this->prodi, ($this->data)());

        $this->layanan->tarik($keliru);

        expect(AkreditasiProdi::withTrashed()->find($keliru->id)->trashed())->toBeTrue()
            ->and($this->prodi->fresh()->akreditasi)->toBe('B');
    });

    it('keadaan() membedakan berlaku, segera kedaluwarsa, kedaluwarsa, tidak terakreditasi, dan belum dicatat', function () {
        $keadaan = fn (): KeadaanAkreditasi => $this->layanan->keadaan($this->prodi->fresh());

        expect($keadaan())->toBe(KeadaanAkreditasi::BelumDicatat);

        $sk = skAkreditasi($this->prodi, '2022-01-01', '2027-12-31');
        expect($keadaan())->toBe(KeadaanAkreditasi::Berlaku);

        // Four months left: inside the six-month window.
        $sk->update(['tanggal_kedaluwarsa' => '2027-02-28']);
        expect($keadaan())->toBe(KeadaanAkreditasi::SegeraKedaluwarsa);

        // The window is configuration, not code.
        config(['academic.akreditasi.peringatan_bulan' => 3]);
        expect($keadaan())->toBe(KeadaanAkreditasi::Berlaku);
        config(['academic.akreditasi.peringatan_bulan' => 6]);

        // A successor already on file silences the warning.
        $penerus = skAkreditasi($this->prodi, '2027-03-01', '2032-03-01', nomor: 'SK/penerus');
        expect($keadaan())->toBe(KeadaanAkreditasi::Berlaku);
        $penerus->delete();

        $sk->update(['tanggal_kedaluwarsa' => '2026-10-29']);
        expect($keadaan())->toBe(KeadaanAkreditasi::Kedaluwarsa);

        skAkreditasi($this->prodi, '2026-10-30', '2031-10-30', PeringkatAkreditasi::TidakTerakreditasi, 'SK/tolak');
        expect($keadaan())->toBe(KeadaanAkreditasi::TidakTerakreditasi);
    });

    it('perluPerhatian() hanya menyebut prodi aktif yang akreditasinya tidak berlaku penuh', function () {
        skAkreditasi($this->prodi, '2022-01-01', '2027-01-31'); // three months left
        $aman = Prodi::factory()->create(['kode' => '55298']);
        skAkreditasi($aman, '2024-01-01', '2029-01-01');
        $nonaktif = Prodi::factory()->create(['kode' => '55297', 'is_active' => false]);

        $daftar = $this->layanan->perluPerhatian(
            Prodi::query()->with('riwayatAkreditasi')->whereIn('id', [$this->prodi->id, $aman->id, $nonaktif->id])->get(),
        );

        expect($daftar)->toHaveCount(1)
            ->and($daftar[0]['prodi']->id)->toBe($this->prodi->id)
            ->and($daftar[0]['keadaan'])->toBe(KeadaanAkreditasi::SegeraKedaluwarsa);
    });

    it('ringkasan() menyebut SK yang berlaku pada tanggal itu, atau null', function () {
        skAkreditasi($this->prodi, '2019-01-01', '2023-12-31', PeringkatAkreditasi::B, 'SK/lama');
        skAkreditasi($this->prodi, '2024-01-01', '2028-12-31', PeringkatAkreditasi::Unggul, 'SK/baru');
        $prodi = $this->prodi->fresh();

        expect($this->layanan->ringkasan($prodi))->toBe('Unggul — BAN-PT, SK SK/baru, berlaku 1 Jan 2024 s.d. 31 Des 2028')
            ->and($this->layanan->ringkasan($prodi, Carbon::parse('2020-06-01')))->toStartWith('B — BAN-PT, SK SK/lama')
            ->and($this->layanan->ringkasan($prodi, Carbon::parse('2030-01-01')))->toBeNull();
    });
});

describe('validator PISN', function () {
    it('membaca akreditasi yang berlaku pada tanggal lulus, bukan hari ini', function () {
        // Expired since, but in force on the graduation day.
        skAkreditasi($this->prodi, '2020-01-01', '2025-12-31');
        $lulusSaatBerlaku = lulusanPada($this->prodi, '2025-08-20');

        expect(butirAkreditasi($lulusSaatBerlaku)['siap'])->toBeTrue()
            ->and(butirAkreditasi($lulusSaatBerlaku)['label'])->toBe('Program studi terakreditasi pada tanggal lulus');
    });

    it('menandai lulusan yang lulus saat akreditasi sudah kedaluwarsa', function () {
        skAkreditasi($this->prodi, '2020-01-01', '2025-12-31');
        skAkreditasi($this->prodi, '2026-03-01', '2031-03-01', nomor: 'SK/baru');
        // Graduated in the gap between the two decrees.
        $diCelah = lulusanPada($this->prodi, '2026-02-10');

        $butir = butirAkreditasi($diCelah);

        expect($butir['siap'])->toBeFalse()
            ->and($butir['keterangan'])->toContain('kedaluwarsa 31 Des 2025')
            ->and($butir['keterangan'])->toContain('10 Feb 2026');
    });

    it('tidak menerima peringkat lama yang tercatat tanpa SK dan masa berlaku', function () {
        $this->prodi->update(['akreditasi' => 'Baik Sekali']);
        $m = lulusanPada($this->prodi, '2026-09-01');

        expect(butirAkreditasi($m)['siap'])->toBeFalse()
            ->and(butirAkreditasi($m)['keterangan'])->toContain('tanpa SK');
    });

    it('menolak SK yang menyatakan tidak terakreditasi', function () {
        skAkreditasi($this->prodi, '2025-01-01', '2030-01-01', PeringkatAkreditasi::TidakTerakreditasi);
        $m = lulusanPada($this->prodi, '2026-09-01');

        expect(butirAkreditasi($m)['siap'])->toBeFalse();
    });

    it('memeriksa banyak lulusan tanpa kueri per prodi', function () {
        skAkreditasi($this->prodi, '2024-01-01', '2029-01-01');
        $lain = Prodi::factory()->create(['kode' => '55296']);
        skAkreditasi($lain, '2024-01-01', '2029-01-01');

        $semua = collect([
            lulusanPada($this->prodi, '2026-09-01'),
            lulusanPada($this->prodi, '2026-09-02'),
            lulusanPada($lain, '2026-09-03'),
        ])->map->fresh();

        DB::enableQueryLog();
        app(KesiapanPisnService::class)->periksaBanyak($semua);
        $kueri = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'akreditasi_prodi'));
        DB::disableQueryLog();

        expect($kueri)->toHaveCount(1);
    });
});

describe('layar', function () {
    it('mencatat SK dari layar riwayat akreditasi prodi', function () {
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.master.prodi.akreditasi.store', $this->prodi), [
                'lembaga' => 'laminfokom',
                'peringkat' => 'unggul',
                'nomor_sk' => 'SK/LAM/777/2026',
                'tanggal_sk' => '2026-05-01',
                'tanggal_kedaluwarsa' => '2031-05-01',
                'dokumen' => UploadedFile::fake()->create('sk.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $sk = AkreditasiProdi::sole();

        expect($sk->peringkat)->toBe(PeringkatAkreditasi::Unggul)
            ->and($this->prodi->fresh()->akreditasi)->toBe('Unggul');

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.master.prodi.akreditasi', $this->prodi))
            ->assertOk()
            ->assertSee('SK/LAM/777/2026')
            ->assertSee(route('berkas.akreditasi-prodi', $sk), false);

        $this->actingAs($this->staf, 'staff')->get(route('berkas.akreditasi-prodi', $sk))->assertOk();
    });

    it('menolak masa berlaku yang berakhir sebelum SK terbit', function () {
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.master.prodi.akreditasi.store', $this->prodi), [
                'lembaga' => 'ban_pt', 'peringkat' => 'baik', 'nomor_sk' => 'SK/1',
                'tanggal_sk' => '2026-05-01', 'tanggal_kedaluwarsa' => '2026-04-01',
            ])
            ->assertSessionHasErrors('tanggal_kedaluwarsa');

        expect(AkreditasiProdi::count())->toBe(0);
    });

    it('hanya staf ber-master.manage yang mencatat dan menarik; master.view hanya membaca', function () {
        $pembaca = Staff::factory()->create();
        $pembaca->givePermissionTo('master.view');
        $sk = skAkreditasi($this->prodi, '2024-01-01', '2029-01-01');

        $this->actingAs($pembaca, 'staff')->get(route('admin.master.prodi.akreditasi', $this->prodi))->assertOk();
        $this->actingAs($pembaca, 'staff')
            ->post(route('admin.master.prodi.akreditasi.store', $this->prodi), ['lembaga' => 'ban_pt'])
            ->assertForbidden();
        $this->actingAs($pembaca, 'staff')
            ->delete(route('admin.master.prodi.akreditasi.destroy', [$this->prodi, $sk]))
            ->assertForbidden();

        expect($sk->fresh()->trashed())->toBeFalse();
    });

    it('tidak menarik SK lewat URL prodi lain', function () {
        $sk = skAkreditasi($this->prodi, '2024-01-01', '2029-01-01');
        $lain = Prodi::factory()->create(['kode' => '55295']);

        $this->actingAs($this->staf, 'staff')
            ->delete(route('admin.master.prodi.akreditasi.destroy', [$lain, $sk]))
            ->assertNotFound();

        $this->actingAs($this->staf, 'staff')
            ->delete(route('admin.master.prodi.akreditasi.destroy', [$this->prodi, $sk]))
            ->assertRedirect();

        expect($sk->fresh()->trashed())->toBeTrue();
    });

    it('memperingatkan di master prodi dan dasbor staf ketika akreditasi akan kedaluwarsa', function () {
        $this->prodi->update(['nama' => 'Prodi Hampir Habis']);
        skAkreditasi($this->prodi, '2022-01-01', '2027-01-31');

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/master/prodi')
            ->assertOk()
            ->assertSee('Segera kedaluwarsa')
            ->assertSee(route('admin.master.prodi.akreditasi', $this->prodi), false);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Akreditasi program studi perlu perhatian')
            ->assertSee('Prodi Hampir Habis')
            ->assertSee('31 Jan 2027');
    });

    it('menuliskan akreditasi yang berlaku ke layar dan CSV LKPS', function () {
        skAkreditasi($this->prodi, '2024-01-01', '2028-12-31', PeringkatAkreditasi::Unggul, 'SK/LKPS/1');

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/lkps?prodi='.$this->prodi->uuid)
            ->assertOk()
            ->assertSee('Unggul — BAN-PT, SK SK/LKPS/1');

        $csv = $this->actingAs($this->staf, 'staff')
            ->get('/admin/lkps/ekspor?prodi='.$this->prodi->uuid)
            ->assertOk()
            ->streamedContent();

        expect($csv)->toContain('Akreditasi')->toContain('SK/LKPS/1');
    });
});
