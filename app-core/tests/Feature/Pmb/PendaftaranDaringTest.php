<?php

declare(strict_types=1);

use App\Enums\ApplicantStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Pmb\PmbAkun;
use App\Models\Pmb\PmbBerkas;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbGelombangProdi;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use App\Notifications\Pmb\BerkasDikembalikan;
use App\Notifications\Pmb\PembayaranFormulirDiterima;
use App\Notifications\Pmb\SiapSeleksi;
use App\Services\Pmb\PendaftaranPmbService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * An online application from the first click to the selection pool.
 *
 * The applicant's side (choose, fill, upload, submit, print) and the desk's
 * side (fee, document check) meet at one rule: nobody reaches selection
 * without the fee settled and every required document accepted.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true, 'tahun_mulai' => 2026]);
    $this->prodiA = Prodi::factory()->create(['kode' => 'IF', 'nama' => 'Informatika']);
    $this->prodiB = Prodi::factory()->create(['kode' => 'SI', 'nama' => 'Sistem Informasi']);
    $this->prodiLain = Prodi::factory()->create(['kode' => 'MN', 'nama' => 'Manajemen']);

    $this->gelombang = PmbGelombang::create([
        'tahun_akademik_id' => $this->term->id,
        'kode' => 'PMB-D1',
        'nama' => 'Gelombang Daring',
        'jalur' => 'reguler',
        'tanggal_mulai' => now()->subWeek(),
        'tanggal_selesai' => now()->addWeek(),
        'biaya_pendaftaran' => 200_000,
        'is_active' => true,
        'is_daring' => true,
        'dokumen_wajib' => ['ktp', 'ijazah'],
    ]);

    PmbGelombangProdi::create(['pmb_gelombang_id' => $this->gelombang->id, 'prodi_id' => $this->prodiA->id]);
    PmbGelombangProdi::create([
        'pmb_gelombang_id' => $this->gelombang->id,
        'prodi_id' => $this->prodiB->id,
        'biaya_formulir' => 150_000,
        'dokumen_wajib' => ['rapor'],
    ]);

    $this->akun = PmbAkun::create([
        'nama' => 'Sari Wulandari',
        'email' => 'sari@contoh.test',
        'password' => 'rahasia-123',
        'email_verified_at' => now(),
    ]);

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->kasir = Staff::factory()->create();
    $this->kasir->assignRole('keuangan');

    $this->layanan = app(PendaftaranPmbService::class);
});

/** @return array<string, mixed> */
function pmbdBiodata(array $timpa = []): array
{
    return [
        'nama' => 'Sari Wulandari',
        'nik' => '3273015408080002',
        'nisn' => '0081234567',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2008-08-14',
        'jenis_kelamin' => 'P',
        'agama_kode' => '1',
        'nama_ibu' => 'Siti Aminah',
        'nama_ayah' => 'Budi Santoso',
        'telepon' => '081234567890',
        'alamat' => 'Jl. Merdeka No. 10, RT 02/RW 05',
        'kelurahan' => 'Citarum',
        'kecamatan' => 'Bandung Wetan',
        'kabupaten' => 'Kota Bandung',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '40115',
        'asal_sekolah' => 'SMA Negeri 3 Bandung',
        'tahun_lulus_sekolah' => 2026,
        'is_calon_kip' => '1',
        'nomor_kip' => 'KIP-123456',
        ...$timpa,
    ];
}

function pmbdMulai(?PmbAkun $akun = null, ?Prodi $prodi = null): PmbPendaftar
{
    $t = test();
    $pilihan1 = $prodi ?? $t->prodiA;

    $t->actingAs($akun ?? $t->akun, 'pendaftar')->post('/pmb/portal/pendaftaran', [
        'gelombang' => $t->gelombang->uuid,
        'prodi_pilihan_1_id' => $pilihan1->id,
        'prodi_pilihan_2_id' => $pilihan1->is($t->prodiA) ? $t->prodiB->id : $t->prodiA->id,
    ])->assertSessionHasNoErrors();

    return PmbPendaftar::where('pmb_akun_id', ($akun ?? $t->akun)->id)->latest('id')->firstOrFail();
}

function pmbdUnggah(PmbPendaftar $p, string $jenis): void
{
    test()->actingAs($p->akun, 'pendaftar')->post("/pmb/portal/{$p->uuid}/berkas", [
        'jenis' => $jenis,
        'berkas' => UploadedFile::fake()->create("{$jenis}.pdf", 50, 'application/pdf'),
    ])->assertSessionHasNoErrors();
}

/** Draft with biodata and required documents, not yet submitted. */
function pmbdLengkap(?PmbAkun $akun = null): PmbPendaftar
{
    $p = pmbdMulai($akun);

    test()->actingAs($p->akun, 'pendaftar')
        ->put("/pmb/portal/{$p->uuid}/biodata", pmbdBiodata())
        ->assertSessionHasNoErrors();

    pmbdUnggah($p, 'ktp');
    pmbdUnggah($p, 'ijazah');

    return $p->refresh();
}

describe('memulai pendaftaran', function () {
    it('membuat draf dengan nomor berurutan dan biaya yang dibekukan', function () {
        $p = pmbdMulai();

        expect($p->status)->toBe(ApplicantStatus::Draf)
            ->and($p->nomor_pendaftaran)->toBe('PMB-D1-0001')
            ->and($p->email)->toBe('sari@contoh.test')
            ->and($p->biaya_formulir)->toBe(200_000);

        // A price change afterwards does not reprice it.
        $this->gelombang->update(['biaya_pendaftaran' => 999_000]);
        expect($p->fresh()->biaya_formulir)->toBe(200_000);
    });

    it('memakai biaya formulir prodi bila ditetapkan', function () {
        $p = pmbdMulai(prodi: $this->prodiB);

        expect($p->biaya_formulir)->toBe(150_000);
    });

    it('menolak gelombang yang tidak membuka pendaftaran daring', function () {
        $this->gelombang->update(['is_daring' => false]);

        $this->actingAs($this->akun, 'pendaftar')->post('/pmb/portal/pendaftaran', [
            'gelombang' => $this->gelombang->uuid,
            'prodi_pilihan_1_id' => $this->prodiA->id,
        ])->assertSessionHas('galat', __('pmb.validasi.gelombang_tutup'));

        expect(PmbPendaftar::count())->toBe(0);
    });

    it('menolak gelombang yang periodenya sudah lewat', function () {
        $this->gelombang->update(['tanggal_selesai' => now()->subDay()]);

        $this->actingAs($this->akun, 'pendaftar')->post('/pmb/portal/pendaftaran', [
            'gelombang' => $this->gelombang->uuid,
            'prodi_pilihan_1_id' => $this->prodiA->id,
        ])->assertSessionHas('galat');

        expect(PmbPendaftar::count())->toBe(0);
    });

    it('menolak prodi yang tidak ditawarkan gelombang', function () {
        $this->actingAs($this->akun, 'pendaftar')->post('/pmb/portal/pendaftaran', [
            'gelombang' => $this->gelombang->uuid,
            'prodi_pilihan_1_id' => $this->prodiLain->id,
        ])->assertSessionHas('galat', __('pmb.validasi.prodi_tidak_ditawarkan'));
    });

    it('menolak pilihan kedua yang sama dengan pilihan pertama', function () {
        $this->actingAs($this->akun, 'pendaftar')->post('/pmb/portal/pendaftaran', [
            'gelombang' => $this->gelombang->uuid,
            'prodi_pilihan_1_id' => $this->prodiA->id,
            'prodi_pilihan_2_id' => $this->prodiA->id,
        ])->assertSessionHasErrors('prodi_pilihan_2_id');
    });

    it('menolak pendaftaran kedua di gelombang yang sama', function () {
        $p = pmbdMulai();

        $this->actingAs($this->akun, 'pendaftar')->post('/pmb/portal/pendaftaran', [
            'gelombang' => $this->gelombang->uuid,
            'prodi_pilihan_1_id' => $this->prodiB->id,
        ])->assertSessionHas('galat', __('pmb.validasi.sudah_terdaftar', ['nomor' => $p->nomor_pendaftaran]));
    });

    it('menghitung kuota dari pendaftaran yang diajukan, bukan draf', function () {
        $this->gelombang->update(['kuota' => 1, 'biaya_pendaftaran' => 0]);

        $pertama = pmbdLengkap();
        $kedua = pmbdLengkap(PmbAkun::create([
            'nama' => 'Calon Dua', 'email' => 'dua@contoh.test', 'password' => 'x', 'email_verified_at' => now(),
        ]));

        // Both drafts exist; only one place.
        $this->layanan->ajukan($pertama);

        expect(fn () => $this->layanan->ajukan($kedua->refresh()))
            ->toThrow(AturanAkademikException::class, __('pmb.validasi.kuota_penuh'));
    });
});

describe('biodata', function () {
    it('menyimpan biodata lengkap beserta penanda calon KIP', function () {
        $p = pmbdLengkap();

        expect($p->nik)->toBe('3273015408080002')
            ->and($p->agama_kode?->value)->toBe('1')
            ->and($p->nama_ibu)->toBe('Siti Aminah')
            ->and($p->is_calon_kip)->toBeTrue()
            ->and($p->nomor_kip)->toBe('KIP-123456');
    });

    it('menolak NIK yang bukan 16 digit, seperti Feeder', function (string $nik) {
        $p = pmbdMulai();

        $this->actingAs($this->akun, 'pendaftar')
            ->put("/pmb/portal/{$p->uuid}/biodata", pmbdBiodata(['nik' => $nik]))
            ->assertSessionHasErrors(['nik' => __('pmb.validasi.nik')]);
    })->with(['terlalu pendek' => '327301540808', 'berhuruf' => '32730154080800AB', 'kosong' => '']);

    it('mewajibkan kolom yang dibutuhkan biodata Feeder', function (string $kolom) {
        $p = pmbdMulai();

        $this->actingAs($this->akun, 'pendaftar')
            ->put("/pmb/portal/{$p->uuid}/biodata", pmbdBiodata([$kolom => '']))
            ->assertSessionHasErrors($kolom);
    })->with(['tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama_kode', 'nama_ibu', 'telepon', 'alamat', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi']);

    it('menolak tanggal lahir di masa depan, agama dan jenis kelamin di luar kode', function () {
        $p = pmbdMulai();

        $this->actingAs($this->akun, 'pendaftar')
            ->put("/pmb/portal/{$p->uuid}/biodata", pmbdBiodata([
                'tanggal_lahir' => now()->addDay()->toDateString(),
                'agama_kode' => '7',
                'jenis_kelamin' => 'X',
                'kode_pos' => '4011',
                'nisn' => '123',
            ]))
            ->assertSessionHasErrors(['tanggal_lahir', 'agama_kode', 'jenis_kelamin', 'kode_pos', 'nisn']);
    });

    it('mewajibkan asal perguruan tinggi pada jalur pindahan', function () {
        $this->gelombang->update(['jalur' => 'transfer']);
        $p = pmbdMulai();

        $this->actingAs($this->akun, 'pendaftar')
            ->put("/pmb/portal/{$p->uuid}/biodata", pmbdBiodata())
            ->assertSessionHasErrors(['pt_asal', 'prodi_asal']);

        $this->actingAs($this->akun, 'pendaftar')
            ->put("/pmb/portal/{$p->uuid}/biodata", pmbdBiodata(['pt_asal' => 'Universitas Asal', 'prodi_asal' => 'Teknik Informatika']))
            ->assertSessionHasNoErrors();
    });

    it('membuang nomor KIP bila penanda KIP tidak dicentang', function () {
        $p = pmbdMulai();

        $this->actingAs($this->akun, 'pendaftar')
            ->put("/pmb/portal/{$p->uuid}/biodata", pmbdBiodata(['is_calon_kip' => '0', 'nomor_kip' => 'KIP-1']));

        expect($p->fresh()->is_calon_kip)->toBeFalse()
            ->and($p->fresh()->nomor_kip)->toBeNull();
    });

    it('mengunci biodata sesudah diajukan', function () {
        $this->gelombang->update(['biaya_pendaftaran' => 0]);
        $p = pmbdLengkap();
        $this->layanan->ajukan($p);

        $this->actingAs($this->akun, 'pendaftar')
            ->put("/pmb/portal/{$p->uuid}/biodata", pmbdBiodata(['nama' => 'Nama Diganti']))
            ->assertSessionHas('galat', __('pmb.validasi.bukan_draf'));

        expect($p->fresh()->nama)->toBe('Sari Wulandari');
    });
});

describe('berkas', function () {
    it('menyimpan berkas di disk privat dan mengganti unggahan jenis yang sama', function () {
        $p = pmbdMulai();

        pmbdUnggah($p, 'ktp');
        $lama = $p->berkas()->first();
        pmbdUnggah($p, 'ktp');

        expect($p->berkas()->count())->toBe(1);
        Storage::disk('local')->assertMissing($lama->file_path);
        Storage::disk('local')->assertExists($p->berkas()->first()->file_path);
    });

    it('menolak jenis berkas yang tidak diminta', function () {
        $p = pmbdMulai();

        $this->actingAs($this->akun, 'pendaftar')->post("/pmb/portal/{$p->uuid}/berkas", [
            'jenis' => 'kk',
            'berkas' => UploadedFile::fake()->create('kk.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('galat', __('pmb.validasi.berkas_tidak_wajib'));
    });

    it('meminta berkas tambahan prodi di atas berkas gelombang', function () {
        $p = pmbdMulai(prodi: $this->prodiB);
        $p->load('gelombang.prodiDitawarkan');

        expect($p->dokumenWajib())->toEqualCanonicalizing(['ijazah', 'ktp', 'rapor']);
    });

    it('tidak membiarkan pendaftar membuka atau menghapus berkas pendaftaran orang lain', function () {
        $p = pmbdMulai();
        pmbdUnggah($p, 'ktp');
        $berkas = $p->berkas()->first();

        $lain = pmbdMulai(PmbAkun::create([
            'nama' => 'Lain', 'email' => 'lain@contoh.test', 'password' => 'x', 'email_verified_at' => now(),
        ]));

        $this->actingAs($lain->akun, 'pendaftar')->get("/pmb/portal/{$p->uuid}/berkas/{$berkas->id}")->assertForbidden();
        $this->actingAs($lain->akun, 'pendaftar')->delete("/pmb/portal/{$p->uuid}/berkas/{$berkas->id}")->assertForbidden();

        // A document id under the wrong application does not resolve at all.
        $this->actingAs($lain->akun, 'pendaftar')->get("/pmb/portal/{$lain->uuid}/berkas/{$berkas->id}")->assertNotFound();

        expect(PmbBerkas::find($berkas->id))->not->toBeNull();
    });
});

describe('mengajukan', function () {
    it('menolak pengajuan tanpa biodata atau berkas wajib, dengan menyebut yang kurang', function () {
        $p = pmbdMulai();

        expect(fn () => $this->layanan->ajukan($p))
            ->toThrow(AturanAkademikException::class, __('pmb.kolom.nik'));

        $this->actingAs($this->akun, 'pendaftar')->put("/pmb/portal/{$p->uuid}/biodata", pmbdBiodata());
        pmbdUnggah($p, 'ktp');

        expect(fn () => $this->layanan->ajukan($p->refresh()))
            ->toThrow(AturanAkademikException::class, 'Ijazah');
    });

    it('menunggu pembayaran bila gelombang memungut biaya', function () {
        $p = pmbdLengkap();

        $this->actingAs($this->akun, 'pendaftar')->post("/pmb/portal/{$p->uuid}/ajukan")
            ->assertSessionHas('sukses');

        expect($p->fresh()->status)->toBe(ApplicantStatus::Mendaftar)
            ->and($p->fresh()->diajukan_at)->not->toBeNull();
    });

    it('langsung ke verifikasi berkas bila formulir gratis', function () {
        $this->gelombang->update(['biaya_pendaftaran' => 0]);
        $p = pmbdLengkap();

        $this->layanan->ajukan($p);

        expect($p->fresh()->status)->toBe(ApplicantStatus::Verifikasi);
    });

    it('menolak pengajuan oleh akun lain', function () {
        $p = pmbdLengkap();
        $lain = PmbAkun::create(['nama' => 'Lain', 'email' => 'lain@contoh.test', 'password' => 'x', 'email_verified_at' => now()]);

        $this->actingAs($lain, 'pendaftar')->post("/pmb/portal/{$p->uuid}/ajukan")->assertForbidden();
        $this->actingAs($lain, 'pendaftar')->get("/pmb/portal/{$p->uuid}")->assertForbidden();

        expect($p->fresh()->status)->toBe(ApplicantStatus::Draf);
    });

    it('mencatat setiap perpindahan status ke log aktivitas', function () {
        $p = pmbdLengkap();
        $this->layanan->ajukan($p);

        $this->assertDatabaseHas('log_aktivitas', [
            'subject_type' => $p->getMorphClass(),
            'subject_id' => $p->id,
            'event' => 'status_changed',
        ]);
    });

    it('menolak transisi yang tidak terdaftar di enum', function () {
        $p = pmbdLengkap();

        expect(fn () => $this->layanan->pindahkan($p, ApplicantStatus::Mahasiswa))
            ->toThrow(AturanAkademikException::class);
    });
});

describe('biaya formulir', function () {
    beforeEach(function () {
        $this->p = pmbdLengkap();
        $this->layanan->ajukan($this->p);
    });

    it('dicatat kasir, memindahkan ke verifikasi, dan mengirim kuitansi', function () {
        Notification::fake();

        $this->actingAs($this->kasir, 'staff')
            ->post("/admin/pmb/pendaftar/{$this->p->uuid}/pembayaran", ['nominal' => 200_000, 'channel' => 'transfer'])
            ->assertSessionHas('sukses');

        $p = $this->p->fresh();

        expect($p->status)->toBe(ApplicantStatus::Verifikasi)
            ->and($p->formulirLunas())->toBeTrue();

        Notification::assertSentTo($this->akun, PembayaranFormulirDiterima::class);
    });

    it('tidak menulis apa pun ke tagihan mahasiswa', function () {
        $this->actingAs($this->kasir, 'staff')
            ->post("/admin/pmb/pendaftar/{$this->p->uuid}/pembayaran", ['nominal' => 200_000, 'channel' => 'tunai']);

        $this->assertDatabaseCount('tagihan', 0);
        $this->assertDatabaseCount('pembayaran', 0);
    });

    it('menerima cicilan dan baru berpindah status saat lunas', function () {
        $this->actingAs($this->kasir, 'staff')
            ->post("/admin/pmb/pendaftar/{$this->p->uuid}/pembayaran", ['nominal' => 50_000, 'channel' => 'tunai']);

        expect($this->p->fresh()->status)->toBe(ApplicantStatus::Mendaftar)
            ->and($this->p->fresh()->sisaFormulir())->toBe(150_000);
    });

    it('menolak kelebihan bayar', function () {
        $this->actingAs($this->kasir, 'staff')
            ->post("/admin/pmb/pendaftar/{$this->p->uuid}/pembayaran", ['nominal' => 250_000, 'channel' => 'tunai'])
            ->assertSessionHas('galat');

        $this->assertDatabaseCount('pmb_pembayaran', 0);
    });

    it('menolak staf tanpa izin keuangan maupun PMB', function () {
        $operator = Staff::factory()->create();
        $operator->assignRole('operator-pddikti');

        $this->actingAs($operator, 'staff')
            ->post("/admin/pmb/pendaftar/{$this->p->uuid}/pembayaran", ['nominal' => 200_000, 'channel' => 'tunai'])
            ->assertForbidden();
    });

    it('membatalkan pembayaran tanpa menghapus barisnya dan mengembalikan status', function () {
        $this->actingAs($this->kasir, 'staff')
            ->post("/admin/pmb/pendaftar/{$this->p->uuid}/pembayaran", ['nominal' => 200_000, 'channel' => 'tunai']);

        $bayar = $this->p->pembayaran()->first();

        $this->actingAs($this->kasir, 'staff')
            ->post("/admin/pmb/pembayaran/{$bayar->uuid}/batal", ['alasan' => 'Salah input'])
            ->assertSessionHas('sukses');

        expect($bayar->fresh()->status)->toBe(PaymentStatus::Refund)
            ->and($this->p->fresh()->status)->toBe(ApplicantStatus::Mendaftar);
    });

    it('menampilkan antrean kasir tanpa membuka berkas identitas', function () {
        $this->actingAs($this->kasir, 'staff')->get('/admin/pmb/formulir')
            ->assertOk()
            ->assertSee($this->p->nomor_pendaftaran);

        // The finance office may not read the applicant's ID card...
        $ktp = $this->p->berkas()->where('jenis', 'ktp')->first();
        $this->actingAs($this->kasir, 'staff')->get("/berkas/pmb/{$ktp->id}")->assertForbidden();

        // ...but may read the transfer receipt.
        pmbdUnggah($this->p->refresh(), 'bukti_bayar');
        $bukti = $this->p->berkas()->where('jenis', 'bukti_bayar')->first();
        $this->actingAs($this->kasir, 'staff')->get("/berkas/pmb/{$bukti->id}")->assertOk();
    });
});

describe('verifikasi berkas', function () {
    beforeEach(function () {
        Notification::fake();

        $this->p = pmbdLengkap();
        $this->layanan->ajukan($this->p);
        $this->actingAs($this->kasir, 'staff')
            ->post("/admin/pmb/pendaftar/{$this->p->uuid}/pembayaran", ['nominal' => 200_000, 'channel' => 'transfer']);
    });

    it('memindahkan ke seleksi begitu berkas wajib terakhir diterima', function () {
        $berkas = $this->p->berkas()->get();

        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/berkas/{$berkas[0]->id}/verifikasi");
        expect($this->p->fresh()->status)->toBe(ApplicantStatus::Verifikasi);

        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/berkas/{$berkas[1]->id}/verifikasi");
        expect($this->p->fresh()->status)->toBe(ApplicantStatus::Seleksi);

        Notification::assertSentTo($this->akun, SiapSeleksi::class);
    });

    it('mengembalikan berkas dengan alasan, dan pendaftar dapat menggantinya sesudah diajukan', function () {
        $ktp = $this->p->berkas()->where('jenis', 'ktp')->first();

        $this->actingAs($this->baak, 'staff')
            ->post("/admin/pmb/berkas/{$ktp->id}/kembalikan", ['catatan' => 'Foto KTP buram'])
            ->assertSessionHas('sukses');

        Notification::assertSentTo($this->akun, BerkasDikembalikan::class);
        expect($ktp->fresh()->dikembalikan())->toBeTrue();

        // Replacing a returned document is allowed after submission...
        pmbdUnggah($this->p->refresh(), 'ktp');
        expect($this->p->berkas()->where('jenis', 'ktp')->first()->catatan)->toBeNull();

        // ...replacing an accepted one is not.
        $ijazah = $this->p->berkas()->where('jenis', 'ijazah')->first();
        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/berkas/{$ijazah->id}/verifikasi");

        $this->actingAs($this->akun, 'pendaftar')->post("/pmb/portal/{$this->p->uuid}/berkas", [
            'jenis' => 'ijazah',
            'berkas' => UploadedFile::fake()->create('ijazah.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('galat', __('pmb.validasi.berkas_terkunci'));
    });

    it('meminta alasan saat mengembalikan berkas', function () {
        $ktp = $this->p->berkas()->where('jenis', 'ktp')->first();

        $this->actingAs($this->baak, 'staff')
            ->post("/admin/pmb/berkas/{$ktp->id}/kembalikan", ['catatan' => ''])
            ->assertSessionHasErrors('catatan');
    });

    it('menolak kasir mengembalikan berkas', function () {
        $ktp = $this->p->berkas()->where('jenis', 'ktp')->first();

        $this->actingAs($this->kasir, 'staff')
            ->post("/admin/pmb/berkas/{$ktp->id}/kembalikan", ['catatan' => 'x'])
            ->assertForbidden();
    });
});

describe('kartu ujian', function () {
    it('menolak mencetak sebelum berkas terverifikasi, dengan alasannya', function () {
        $p = pmbdLengkap();
        $this->layanan->ajukan($p);

        $this->actingAs($this->akun, 'pendaftar')
            ->from("/pmb/portal/{$p->uuid}")
            ->get("/pmb/portal/{$p->uuid}/kartu-ujian")
            ->assertRedirect("/pmb/portal/{$p->uuid}")
            ->assertSessionHas('galat', fn (string $pesan) => str_contains($pesan, __('pmb.kartu.alasan_bayar')));
    });

    it('mencetak PDF untuk peserta seleksi', function () {
        $this->gelombang->update(['biaya_pendaftaran' => 0]);
        $p = pmbdLengkap();
        $this->layanan->ajukan($p);
        foreach ($p->berkas()->get() as $b) {
            $this->layanan->verifikasiBerkas($b);
        }

        expect($p->fresh()->status)->toBe(ApplicantStatus::Seleksi);

        $this->actingAs($this->akun, 'pendaftar')
            ->get("/pmb/portal/{$p->uuid}/kartu-ujian")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });
});

describe('membatalkan', function () {
    it('membatalkan pendaftaran sebelum diputuskan', function () {
        $p = pmbdLengkap();

        $this->actingAs($this->akun, 'pendaftar')->post("/pmb/portal/{$p->uuid}/batal")->assertSessionHas('sukses');

        expect($p->fresh()->status)->toBe(ApplicantStatus::Batal);
    });

    it('menolak pembatalan sesudah diputuskan', function () {
        $p = pmbdLengkap();
        $p->update(['status' => ApplicantStatus::Lulus]);

        $this->actingAs($this->akun, 'pendaftar')->post("/pmb/portal/{$p->uuid}/batal")
            ->assertSessionHas('galat', __('pmb.validasi.tidak_bisa_batal'));
    });

    it('membolehkan mendaftar lagi di gelombang yang sama sesudah membatalkan', function () {
        $p = pmbdMulai();
        $this->layanan->batalkan($p);

        $baru = pmbdMulai();

        expect($baru->id)->not->toBe($p->id)
            ->and($baru->nomor_pendaftaran)->toBe('PMB-D1-0002');
    });
});

describe('layar', function () {
    it('merender portal, halaman pendaftaran, dan beranda publik', function () {
        $p = pmbdLengkap();

        $this->get('/pmb')->assertOk()->assertSee('Gelombang Daring')->assertSee('index, follow', false);
        $this->actingAs($this->akun, 'pendaftar')->get('/pmb/portal')->assertOk()->assertSee($p->nomor_pendaftaran);
        $this->actingAs($this->akun, 'pendaftar')->get("/pmb/portal/{$p->uuid}")->assertOk()->assertSee(__('pmb.formulir.ajukan'));
    });

    it('menyembunyikan draf dari daftar staf kecuali diminta, dan menyaring calon KIP', function () {
        $draf = pmbdMulai();
        $kip = pmbdLengkap(PmbAkun::create(['nama' => 'Calon KIP', 'email' => 'kip@contoh.test', 'password' => 'x', 'email_verified_at' => now()]));
        $this->layanan->ajukan($kip);

        $this->actingAs($this->baak, 'staff')->get('/admin/pmb')
            ->assertOk()
            ->assertDontSee($draf->nomor_pendaftaran)
            ->assertSee($kip->nomor_pendaftaran);

        $this->actingAs($this->baak, 'staff')->get('/admin/pmb?status=draf')->assertSee($draf->nomor_pendaftaran);
        $this->actingAs($this->baak, 'staff')->get('/admin/pmb?kip=1')->assertSee($kip->nomor_pendaftaran);
    });

    it('merender detail pendaftar untuk staf PMB dan menolak kasir', function () {
        $p = pmbdLengkap();

        $this->actingAs($this->baak, 'staff')->get("/admin/pmb/pendaftar/{$p->uuid}")->assertOk()->assertSee('3273015408080002');
        $this->actingAs($this->kasir, 'staff')->get("/admin/pmb/pendaftar/{$p->uuid}")->assertForbidden();
    });
});
