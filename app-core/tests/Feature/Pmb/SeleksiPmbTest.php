<?php

declare(strict_types=1);

use App\Enums\Agama;
use App\Enums\ApplicantStatus;
use App\Enums\Gender;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Pmb\PmbAkun;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbGelombangProdi;
use App\Models\Pmb\PmbJadwalSeleksi;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use App\Notifications\Pmb\HasilSeleksiDiumumkan;
use App\Notifications\Pmb\MenjadiMahasiswa;
use App\Services\Feeder\FeederValidator;
use App\Services\Kemahasiswaan\PindahanService;
use App\Services\Lkps\IndikatorLkps;
use App\Services\Pmb\PmbService;
use App\Services\Pmb\SeleksiPmbService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

/**
 * Selection, announcement, and the step from applicant to student.
 *
 * The score import is held to the Feeder rule — check everything, write all
 * or nothing — and the announcement to the rule that an applicant hears one
 * outcome, once, and only after the committee is done.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true, 'tahun_mulai' => 2026]);
    // kode_pddikti holds PDDIKTI's id_sms, a UUID, as on a campus that has
    // pulled its Feeder references; the NIM is built from the short `kode`.
    $this->prodiA = Prodi::factory()->create([
        'kode' => 'IF', 'kode_pddikti' => 'f127ba98-41bc-3d06-9935-a0fcf911fd19', 'nama' => 'Informatika',
    ]);
    $this->prodiB = Prodi::factory()->create([
        'kode' => 'SI', 'kode_pddikti' => '0a9c3e21-7d44-4f1b-8e2a-5b6c7d8e9f10', 'nama' => 'Sistem Informasi',
    ]);

    $this->gelombang = PmbGelombang::create([
        'tahun_akademik_id' => $this->term->id,
        'kode' => 'PMB-S1',
        'nama' => 'Gelombang Seleksi',
        'jalur' => 'reguler',
        'tanggal_mulai' => now()->subMonth(),
        'tanggal_selesai' => now()->addWeek(),
        'biaya_pendaftaran' => 0,
        'is_active' => true,
        'is_daring' => true,
    ]);

    $this->tawaranA = PmbGelombangProdi::create([
        'pmb_gelombang_id' => $this->gelombang->id, 'prodi_id' => $this->prodiA->id, 'daya_tampung' => 2, 'ambang_lulus' => 70,
    ]);
    $this->tawaranB = PmbGelombangProdi::create([
        'pmb_gelombang_id' => $this->gelombang->id, 'prodi_id' => $this->prodiB->id, 'ambang_lulus' => 60,
    ]);

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->pmb = app(PmbService::class);
    $this->seleksi = app(SeleksiPmbService::class);
});

/** An online applicant already in the selection pool. */
function pmbsPeserta(string $nomor, array $atribut = []): PmbPendaftar
{
    $t = test();

    $akun = PmbAkun::create([
        'nama' => 'Peserta '.$nomor,
        'email' => strtolower($nomor).'@contoh.test',
        'password' => 'rahasia-123',
        'email_verified_at' => now(),
    ]);

    return PmbPendaftar::create([
        'pmb_gelombang_id' => $t->gelombang->id,
        'pmb_akun_id' => $akun->id,
        'nomor_pendaftaran' => $nomor,
        'nama' => 'Peserta '.$nomor,
        'email' => $akun->email,
        'nik' => '3273015408080'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2008-08-14',
        'jenis_kelamin' => Gender::Perempuan,
        'agama_kode' => Agama::Islam,
        'nama_ibu' => 'Siti Aminah',
        'telepon' => '081234567890',
        'alamat' => 'Jl. Merdeka No. 10',
        'kelurahan' => 'Citarum',
        'kecamatan' => 'Bandung Wetan',
        'kabupaten' => 'Kota Bandung',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '40115',
        'prodi_pilihan_1_id' => $t->prodiA->id,
        'prodi_pilihan_2_id' => $t->prodiB->id,
        'status' => ApplicantStatus::Seleksi,
        'diajukan_at' => now(),
        ...$atribut,
    ]);
}

function pmbsCsv(string $isi): UploadedFile
{
    return UploadedFile::fake()->createWithContent('nilai.csv', $isi);
}

describe('jadwal seleksi', function () {
    it('ditambahkan staf dan tampil di portal pendaftar', function () {
        $p = pmbsPeserta('PMB-S1-0001');

        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/gelombang/{$this->gelombang->uuid}/jadwal", [
            'nama' => 'Tes Potensi Akademik',
            'mulai' => '2026-11-20 08:00',
            'selesai' => '2026-11-20 10:00',
            'lokasi' => 'Lab Komputer B',
        ])->assertSessionHasNoErrors();

        $this->actingAs($p->akun, 'pendaftar')->get("/pmb/portal/{$p->uuid}")
            ->assertOk()
            ->assertSee('Tes Potensi Akademik')
            ->assertSee('Lab Komputer B');
    });

    it('menolak waktu selesai sebelum mulai', function () {
        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/gelombang/{$this->gelombang->uuid}/jadwal", [
            'nama' => 'Wawancara',
            'mulai' => '2026-11-20 10:00',
            'selesai' => '2026-11-20 08:00',
        ])->assertSessionHasErrors('selesai');
    });

    it('hanya dihapus lewat gelombangnya sendiri', function () {
        $jadwal = PmbJadwalSeleksi::create(['pmb_gelombang_id' => $this->gelombang->id, 'nama' => 'Tes', 'mulai' => now()]);
        $lain = PmbGelombang::create([
            'tahun_akademik_id' => $this->term->id, 'kode' => 'PMB-X', 'nama' => 'Lain', 'jalur' => 'reguler',
            'tanggal_mulai' => now(), 'tanggal_selesai' => now(), 'biaya_pendaftaran' => 0,
        ]);

        $this->actingAs($this->baak, 'staff')
            ->delete("/admin/pmb/gelombang/{$lain->uuid}/jadwal/{$jadwal->uuid}")
            ->assertNotFound();

        $this->actingAs($this->baak, 'staff')
            ->delete("/admin/pmb/gelombang/{$this->gelombang->uuid}/jadwal/{$jadwal->uuid}")
            ->assertSessionHas('sukses');

        expect($jadwal->fresh()->trashed())->toBeTrue();
    });
});

describe('pengaturan gelombang', function () {
    it('membuka gelombang untuk pendaftaran daring dengan berkas wajib', function () {
        $this->gelombang->update(['is_daring' => false]);

        $this->actingAs($this->baak, 'staff')->put("/admin/pmb/gelombang/{$this->gelombang->uuid}", [
            'is_daring' => '1',
            'dokumen_wajib' => ['ktp', 'ijazah'],
            'keterangan' => 'Tes CBT dan wawancara.',
        ])->assertSessionHasNoErrors();

        $g = $this->gelombang->fresh();

        expect($g->is_daring)->toBeTrue()
            ->and($g->dokumen_wajib)->toBe(['ijazah', 'ktp']);

        $this->get('/pmb')->assertSee('Gelombang Seleksi');
    });

    it('menolak jenis berkas yang tidak dikenal dan ambang di atas 100', function () {
        $this->actingAs($this->baak, 'staff')->put("/admin/pmb/gelombang/{$this->gelombang->uuid}", [
            'dokumen_wajib' => ['paspor'],
        ])->assertSessionHasErrors('dokumen_wajib.0');

        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/gelombang/{$this->gelombang->uuid}/prodi", [
            'prodi_id' => $this->prodiA->id,
            'ambang_lulus' => 120,
        ])->assertSessionHasErrors('ambang_lulus');
    });

    it('memperbarui penawaran prodi tanpa menggandakannya', function () {
        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/gelombang/{$this->gelombang->uuid}/prodi", [
            'prodi_id' => $this->prodiA->id,
            'daya_tampung' => 30,
            'biaya_formulir' => 175000,
        ])->assertSessionHasNoErrors();

        expect(PmbGelombangProdi::where('pmb_gelombang_id', $this->gelombang->id)->count())->toBe(2)
            ->and($this->tawaranA->fresh()->daya_tampung)->toBe(30)
            ->and($this->tawaranA->fresh()->biaya_formulir)->toBe(175000);
    });

    it('menolak menghapus prodi yang sudah dipilih pendaftar', function () {
        pmbsPeserta('PMB-S1-0001');

        $this->actingAs($this->baak, 'staff')
            ->delete("/admin/pmb/gelombang/{$this->gelombang->uuid}/prodi/{$this->tawaranA->id}")
            ->assertSessionHas('galat', __('pmb.admin.tawaran_dipakai'));

        expect($this->tawaranA->fresh())->not->toBeNull();
    });

    it('menolak staf tanpa izin PMB', function () {
        $kasir = Staff::factory()->create();
        $kasir->assignRole('keuangan');

        $this->actingAs($kasir, 'staff')->get("/admin/pmb/gelombang/{$this->gelombang->uuid}")->assertForbidden();
        $this->actingAs($kasir, 'staff')->put("/admin/pmb/gelombang/{$this->gelombang->uuid}", ['is_daring' => '0'])->assertForbidden();
    });

    it('merender layar gelombang', function () {
        pmbsPeserta('PMB-S1-0001');

        $this->actingAs($this->baak, 'staff')->get("/admin/pmb/gelombang/{$this->gelombang->uuid}")
            ->assertOk()
            ->assertSee('Informatika')
            ->assertSee(__('pmb.admin.kelulusan_otomatis_mati'));
    });
});

describe('impor nilai seleksi', function () {
    it('menyimpan nilai dari CSV bertitik koma, BOM, dan koma desimal', function () {
        $a = pmbsPeserta('PMB-S1-0001');
        $b = pmbsPeserta('PMB-S1-0002');

        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/gelombang/{$this->gelombang->uuid}/impor-nilai", [
            'berkas' => pmbsCsv("\xEF\xBB\xBFnama;nomor_pendaftaran;nilai\nA;PMB-S1-0001;78,5\nB;PMB-S1-0002;64\n"),
        ])->assertSessionHas('sukses', __('pmb.admin.impor_berhasil', ['jumlah' => 2]));

        expect((float) $a->fresh()->nilai_seleksi)->toBe(78.5)
            ->and((float) $b->fresh()->nilai_seleksi)->toBe(64.0);
    });

    it('tidak menyimpan apa pun bila satu baris salah, dan melaporkan semua baris salah sekaligus', function () {
        $a = pmbsPeserta('PMB-S1-0001');
        pmbsPeserta('PMB-S1-0002', ['status' => ApplicantStatus::Verifikasi]);

        $hasil = $this->seleksi->imporNilai($this->gelombang, implode("\n", [
            'nomor_pendaftaran,nilai',
            'PMB-S1-0001,80',
            'PMB-S1-9999,70',
            'PMB-S1-0001,81',
            'PMB-S1-0002,75',
            'PMB-S1-0003,abc',
        ]));

        expect($hasil->berhasil())->toBeFalse()
            ->and($hasil->tersimpan)->toBe(0)
            ->and($hasil->galat)->toHaveCount(4)
            ->and(implode(' ', $hasil->galat))->toContain('Baris 3')->toContain('Baris 4')->toContain('Baris 5')->toContain('Baris 6')
            ->and($a->fresh()->nilai_seleksi)->toBeNull();
    });

    it('menolak nilai di luar 0–100', function () {
        pmbsPeserta('PMB-S1-0001');

        $hasil = $this->seleksi->imporNilai($this->gelombang, "nomor_pendaftaran,nilai\nPMB-S1-0001,101");

        expect($hasil->galat)->toBe([__('pmb.impor.nilai', ['baris' => 2, 'nomor' => 'PMB-S1-0001', 'nilai' => '101', 'maks' => 100])]);
    });

    it('menolak berkas tanpa kolom yang diminta', function () {
        $hasil = $this->seleksi->imporNilai($this->gelombang, "no,skor\nPMB-S1-0001,80");

        expect($hasil->galat)->toBe([__('pmb.impor.tanpa_kolom')]);
    });

    it('menolak nomor pendaftaran dari gelombang lain', function () {
        $lain = PmbGelombang::create([
            'tahun_akademik_id' => $this->term->id, 'kode' => 'PMB-X', 'nama' => 'Lain', 'jalur' => 'reguler',
            'tanggal_mulai' => now(), 'tanggal_selesai' => now(), 'biaya_pendaftaran' => 0,
        ]);
        pmbsPeserta('PMB-X-0001', ['pmb_gelombang_id' => $lain->id]);

        $hasil = $this->seleksi->imporNilai($this->gelombang, "nomor_pendaftaran,nilai\nPMB-X-0001,80");

        expect($hasil->berhasil())->toBeFalse();
    });

    it('melaporkan seluruh baris salah lewat layar', function () {
        pmbsPeserta('PMB-S1-0001');

        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/gelombang/{$this->gelombang->uuid}/impor-nilai", [
            'berkas' => pmbsCsv("nomor_pendaftaran,nilai\nPMB-S1-0001,80\nTIDAK-ADA,70\n"),
        ])
            ->assertSessionHas('galat', __('pmb.admin.impor_gagal', ['jumlah' => 1]))
            ->assertSessionHas('galat_impor');
    });

    it('tidak meluluskan siapa pun selama kelulusan otomatis mati (bawaan)', function () {
        $a = pmbsPeserta('PMB-S1-0001');

        $this->seleksi->imporNilai($this->gelombang, "nomor_pendaftaran,nilai\nPMB-S1-0001,95");

        expect(config('pmb.kelulusan_otomatis'))->toBeFalse()
            ->and($a->fresh()->status)->toBe(ApplicantStatus::Seleksi);
    });

    it('meluluskan otomatis menurut ambang dan daya tampung bila dinyalakan', function () {
        config(['pmb.kelulusan_otomatis' => true]);

        $tertinggi = pmbsPeserta('PMB-S1-0001');
        $kedua = pmbsPeserta('PMB-S1-0002');
        $ketiga = pmbsPeserta('PMB-S1-0003');
        $pilihanDua = pmbsPeserta('PMB-S1-0004');
        $gagal = pmbsPeserta('PMB-S1-0005');

        $hasil = $this->seleksi->imporNilai($this->gelombang, implode("\n", [
            'nomor_pendaftaran,nilai',
            'PMB-S1-0001,95',
            'PMB-S1-0002,90',
            'PMB-S1-0003,85', // IF full (2 seats) → falls to SI
            'PMB-S1-0004,65', // below IF 70, above SI 60
            'PMB-S1-0005,40', // below both
        ]));

        expect($hasil->lulusOtomatis)->toBe(4)
            ->and($hasil->tidakLulusOtomatis)->toBe(1)
            ->and($tertinggi->fresh()->prodi_diterima_id)->toBe($this->prodiA->id)
            ->and($kedua->fresh()->prodi_diterima_id)->toBe($this->prodiA->id)
            ->and($ketiga->fresh()->prodi_diterima_id)->toBe($this->prodiB->id)
            ->and($pilihanDua->fresh()->prodi_diterima_id)->toBe($this->prodiB->id)
            ->and($gagal->fresh()->status)->toBe(ApplicantStatus::TidakLulus);
    });

    it('membiarkan prodi tanpa ambang diputuskan staf', function () {
        config(['pmb.kelulusan_otomatis' => true]);
        $this->tawaranA->update(['ambang_lulus' => null]);
        $this->tawaranB->update(['ambang_lulus' => null]);

        $p = pmbsPeserta('PMB-S1-0001');

        $this->seleksi->imporNilai($this->gelombang, "nomor_pendaftaran,nilai\nPMB-S1-0001,99");

        expect($p->fresh()->status)->toBe(ApplicantStatus::Seleksi);
    });

    it('menolak staf tanpa izin mengelola PMB', function () {
        $operator = Staff::factory()->create();
        $operator->assignRole('pimpinan');

        $this->actingAs($operator, 'staff')->post("/admin/pmb/gelombang/{$this->gelombang->uuid}/impor-nilai", [
            'berkas' => pmbsCsv("nomor_pendaftaran,nilai\n"),
        ])->assertForbidden();
    });
});

describe('keputusan kelulusan', function () {
    it('menegakkan daya tampung prodi', function () {
        $this->pmb->luluskan(pmbsPeserta('PMB-S1-0001'), $this->prodiA);
        $this->pmb->luluskan(pmbsPeserta('PMB-S1-0002'), $this->prodiA);

        expect(fn () => $this->pmb->luluskan(pmbsPeserta('PMB-S1-0003'), $this->prodiA))
            ->toThrow(AturanAkademikException::class, 'Daya tampung');
    });

    it('membebaskan kursi saat keputusan dikoreksi', function () {
        $a = $this->pmb->luluskan(pmbsPeserta('PMB-S1-0001'), $this->prodiA);
        $this->pmb->luluskan(pmbsPeserta('PMB-S1-0002'), $this->prodiA);
        $this->pmb->tidakLuluskan($a);

        $this->pmb->luluskan(pmbsPeserta('PMB-S1-0003'), $this->prodiA);

        expect(PmbPendaftar::where('status', ApplicantStatus::Lulus->value)->count())->toBe(2);
    });

    it('tidak memutuskan pendaftar daring yang belum masuk seleksi', function () {
        $p = pmbsPeserta('PMB-S1-0001', ['status' => ApplicantStatus::Mendaftar]);

        expect(fn () => $this->pmb->luluskan($p, $this->prodiA))
            ->toThrow(AturanAkademikException::class, __('pmb.kelulusan.belum_seleksi'));
    });

    it('tetap memutuskan pendaftar yang diinput panitia dari tahap mana pun', function () {
        $p = pmbsPeserta('PMB-S1-0001', ['status' => ApplicantStatus::Mendaftar, 'pmb_akun_id' => null]);

        expect($this->pmb->luluskan($p, $this->prodiA)->status)->toBe(ApplicantStatus::Lulus);
    });
});

describe('pengumuman', function () {
    it('menyembunyikan keputusan dari pendaftar sampai diumumkan', function () {
        Notification::fake();
        $p = $this->pmb->luluskan(pmbsPeserta('PMB-S1-0001'), $this->prodiA, 88);

        $this->actingAs($p->akun, 'pendaftar')->get("/pmb/portal/{$p->uuid}")
            ->assertOk()
            ->assertSee(__('pmb.status_pesan.menunggu_pengumuman'))
            ->assertDontSee(__('pmb.notifikasi.lulus_judul'));

        expect($p->statusTerlihat())->toBe(ApplicantStatus::Seleksi);
        Notification::assertNothingSent();
    });

    it('memberi tahu setiap pendaftar yang sudah diputuskan, sekali saja', function () {
        Notification::fake();

        $lulus = $this->pmb->luluskan(pmbsPeserta('PMB-S1-0001'), $this->prodiA);
        $tidak = $this->pmb->tidakLuluskan(pmbsPeserta('PMB-S1-0002'));
        $menunggu = pmbsPeserta('PMB-S1-0003');

        $this->actingAs($this->baak, 'staff')
            ->post("/admin/pmb/gelombang/{$this->gelombang->uuid}/umumkan")
            ->assertSessionHas('sukses', __('pmb.pengumuman.diumumkan', ['jumlah' => 2]));

        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/gelombang/{$this->gelombang->uuid}/umumkan");

        Notification::assertSentToTimes($lulus->akun, HasilSeleksiDiumumkan::class, 1);
        Notification::assertSentToTimes($tidak->akun, HasilSeleksiDiumumkan::class, 1);
        Notification::assertNotSentTo($menunggu->akun, HasilSeleksiDiumumkan::class);

        $this->actingAs($lulus->akun, 'pendaftar')->get("/pmb/portal/{$lulus->uuid}")
            ->assertSee(__('pmb.status_pesan.lulus', ['prodi' => 'Informatika']));
    });

    it('memberi tahu keputusan yang diambil sesudah pengumuman saat itu juga, dan koreksinya', function () {
        Notification::fake();
        $this->gelombang->update(['diumumkan_at' => now()->subMinute()]);

        $p = $this->pmb->tidakLuluskan(pmbsPeserta('PMB-S1-0001'));
        $this->pmb->luluskan($p, $this->prodiB);

        Notification::assertSentToTimes($p->akun, HasilSeleksiDiumumkan::class, 2);
    });

    it('menyebut hasil yang benar di surel', function () {
        $p = $this->pmb->luluskan(pmbsPeserta('PMB-S1-0001'), $this->prodiA);
        $p->load(['akun', 'gelombang', 'prodiDiterima']);

        $surel = (new HasilSeleksiDiumumkan($p))->toMail($p->akun);

        expect($surel->subject)->toBe(__('pmb.notifikasi.lulus_judul'))
            ->and(implode(' ', $surel->introLines))->toContain('Informatika');
    });
});

describe('diterima menjadi mahasiswa', function () {
    it('membawa biodata pendaftar ke mahasiswa, siap lapor ke Feeder', function () {
        Notification::fake();

        $p = $this->pmb->luluskan(pmbsPeserta('PMB-S1-0001', ['nik' => '3273015408080002']), $this->prodiA);
        $hasil = $this->pmb->daftarUlang($p->fresh(), $this->term);
        $m = $hasil['mahasiswa']->fresh();

        // From the short `kode`, not the id_sms UUID in kode_pddikti (#23).
        expect($m->nim)->toStartWith('26IF')
            ->and(strlen($m->nim))->toBeLessThanOrEqual(32);

        expect($m->nik)->toBe('3273015408080002')
            ->and($m->agama_kode)->toBe('1')
            ->and($m->nama_ibu)->toBe('Siti Aminah')
            ->and($m->kelurahan)->toBe('Citarum')
            ->and($m->kabupaten)->toBe('Kota Bandung')
            ->and($m->kode_pos)->toBe('40115');

        $isuBiodata = app(FeederValidator::class)->isuUntuk('mahasiswa', $m)
            ->pluck('rule')
            ->intersect(['nik_kosong', 'nik_tidak_valid', 'kelahiran_kosong', 'jenis_kelamin_kosong', 'nama_ibu_kosong', 'agama_kosong']);

        expect($isuBiodata)->toBeEmpty();

        Notification::assertSentTo($p->akun, MenjadiMahasiswa::class);
    });

    it('menampilkan NIM di portal pendaftar, tetapi sesi pendaftar tetap tidak masuk portal mahasiswa', function () {
        $p = $this->pmb->luluskan(pmbsPeserta('PMB-S1-0001'), $this->prodiA);
        $this->gelombang->update(['diumumkan_at' => now()->subMinute()]);
        $nim = $this->pmb->daftarUlang($p->fresh(), $this->term)['mahasiswa']->nim;

        $this->actingAs($p->akun, 'pendaftar')->get("/pmb/portal/{$p->uuid}")->assertSee($nim);
        $this->actingAs($p->akun, 'pendaftar')->get('/mahasiswa')->assertRedirect(route('login'));
    });

    it('meneruskan pendaftar jalur pindahan ke antrean intake #33, bukan daftar ulang biasa', function () {
        $this->gelombang->update(['jalur' => 'transfer']);
        $p = $this->pmb->luluskan(pmbsPeserta('PMB-S1-0001', ['pt_asal' => 'Universitas Asal', 'prodi_asal' => 'TI']), $this->prodiA);

        expect(app(PindahanService::class)->menungguRegistrasi()->pluck('id'))->toContain($p->id);

        $this->actingAs($this->baak, 'staff')->get('/admin/pmb')->assertSee(route('admin.pindahan.create', ['pendaftar' => $p->uuid]), false);

        // The intake form starts from what the applicant typed.
        $admin = Staff::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin, 'staff')->get(route('admin.pindahan.create', ['pendaftar' => $p->uuid]))
            ->assertOk()
            ->assertSee('Universitas Asal');
    });
});

describe('hitungan pendaftar', function () {
    it('tidak menghitung draf sebagai pendaftar di LKPS', function () {
        pmbsPeserta('PMB-S1-0001', ['status' => ApplicantStatus::Draf]);
        pmbsPeserta('PMB-S1-0002', ['status' => ApplicantStatus::Verifikasi]);

        $tahun = (int) $this->gelombang->tanggal_mulai->format('Y');

        expect(app(IndikatorLkps::class)->keketatan($this->prodiA, $tahun)['pendaftar'])->toBe(1);
    });
});
