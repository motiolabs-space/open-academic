<?php

declare(strict_types=1);

use App\DTOs\Helpdesk\TiketBaruData;
use App\Enums\KategoriNotifikasi;
use App\Enums\SemesterType;
use App\Enums\StatusTiket;
use App\Models\Akademik\TahunAkademik;
use App\Models\Helpdesk\HelpdeskFaq;
use App\Models\Helpdesk\HelpdeskKategori;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Helpdesk\HelpdeskService;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');

    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    Portal::lupakanTerm();

    $this->unit = UnitKerja::create(['kode' => 'BAAK', 'nama' => 'BAAK']);
    $this->unitLain = UnitKerja::create(['kode' => 'TIK', 'nama' => 'UPT TIK']);

    $this->baak = Staff::factory()->create(['unit_kerja_id' => $this->unit->id]);
    $this->baak->assignRole('baak');

    $this->tik = Staff::factory()->create(['unit_kerja_id' => $this->unitLain->id]);
    $this->tik->assignRole('operator-pddikti');

    $this->kategori = HelpdeskKategori::create(['nama' => 'Akademik & KRS', 'unit_kerja_id' => $this->unit->id, 'sla_jam' => 48]);

    $this->mahasiswa = Mahasiswa::factory()->create();
    $this->mahasiswa->assignRole('mahasiswa');

    $this->dosen = Dosen::factory()->create();
    $this->dosen->assignRole('dosen');

    $this->helpdesk = app(HelpdeskService::class);
});

function tiketLayar(object $pemohon): HelpdeskTiket
{
    return test()->helpdesk->buat($pemohon, new TiketBaruData(
        kategoriId: test()->kategori->id,
        judul: 'Presensi tidak tercatat',
        isi: 'Saya hadir pertemuan ke-5 tetapi presensi menyatakan alpa.',
    ));
}

describe('layar pemohon', function () {
    it('merender daftar dan formulir untuk mahasiswa', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.helpdesk'))
            ->assertOk()
            ->assertSee('Buat Tiket Baru')
            ->assertSee('Akademik & KRS');
    });

    it('merender layar yang sama untuk dosen pada awalan rutenya sendiri', function () {
        $tiket = tiketLayar($this->dosen);

        $this->actingAs($this->dosen, 'dosen')
            ->get(route('dosen.helpdesk'))
            ->assertOk()
            ->assertSee($tiket->nomor)
            ->assertSee(route('dosen.helpdesk.show', $tiket), false);
    });

    it('membuat tiket lewat formulir dengan lampiran, lalu mengarahkan ke detailnya', function () {
        $respons = $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.helpdesk.store'), [
                'helpdesk_kategori_id' => $this->kategori->id,
                'judul' => 'Kelas tidak muncul di jadwal',
                'isi' => 'Kelas Basis Data A sudah disetujui wali tetapi tidak muncul di jadwal saya.',
                'prioritas' => 'tinggi',
                'lampiran' => UploadedFile::fake()->create('jadwal.pdf', 30, 'application/pdf'),
            ]);

        $tiket = HelpdeskTiket::firstOrFail();

        $respons->assertRedirect(route('mahasiswa.helpdesk.show', $tiket));

        expect($tiket->judul)->toBe('Kelas tidak muncul di jadwal')
            ->and($tiket->prioritas->value)->toBe('tinggi')
            ->and($tiket->lampiran_nama)->toBe('jadwal.pdf')
            ->and(Storage::disk('local')->exists($tiket->lampiran_path))->toBeTrue();
    });

    it('menolak uraian yang terlalu singkat dan jenis lampiran yang tidak diizinkan', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.helpdesk.store'), [
                'helpdesk_kategori_id' => $this->kategori->id,
                'judul' => 'Tolong',
                'isi' => 'Error.',
                'lampiran' => UploadedFile::fake()->create('skrip.php', 10, 'application/x-php'),
            ])->assertSessionHasErrors(['isi', 'lampiran']);

        expect(HelpdeskTiket::count())->toBe(0);
    });

    it('menampilkan percakapan dan formulir balas pada detail', function () {
        $tiket = tiketLayar($this->mahasiswa);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.helpdesk.show', $tiket))
            ->assertOk()
            ->assertSee('Presensi tidak tercatat')
            ->assertSee('Tulis Balasan');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.helpdesk.balas', $tiket), ['isi' => 'Menambahkan: tanggal 12 September.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($tiket->balasan()->count())->toBe(1);
    });

    it('menyembunyikan formulir balas pada tiket yang ditutup', function () {
        $tiket = tiketLayar($this->mahasiswa);
        $this->helpdesk->tutup($tiket, $this->baak, 'Duplikat.');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.helpdesk.show', $tiket))
            ->assertOk()
            ->assertDontSee('Tulis Balasan')
            ->assertSee('sudah ditutup');
    });

    it('mengizinkan pemilik mengunduh lampirannya dan menolak orang lain', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, new TiketBaruData(
            kategoriId: $this->kategori->id,
            judul: 'Bukti transfer',
            isi: 'Terlampir bukti transfer UKT semester ini.',
            lampiran: UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
        ));

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('berkas.helpdesk.tiket', $tiket))
            ->assertOk();

        $lain = Mahasiswa::factory()->create();
        $lain->assignRole('mahasiswa');

        $this->actingAs($lain, 'mahasiswa')
            ->get(route('berkas.helpdesk.tiket', $tiket))
            ->assertForbidden();

        // Staff of another unit may not read it either; the handling unit may.
        $this->actingAs($this->tik, 'staff')
            ->get(route('berkas.helpdesk.tiket', $tiket))
            ->assertForbidden();

        $this->actingAs($this->baak, 'staff')
            ->get(route('berkas.helpdesk.tiket', $tiket))
            ->assertOk();
    });
});

describe('layar staf', function () {
    it('merender antrean dengan tiket terbuka dan menandai yang lewat SLA', function () {
        $tiket = tiketLayar($this->mahasiswa);
        $tiket->forceFill(['tenggat_sla_at' => now()->subHour()])->saveQuietly();
        $this->helpdesk->tandaiLewatSla();

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.helpdesk'))
            ->assertOk()
            ->assertSee($tiket->nomor)
            ->assertSee('Lewat SLA');
    });

    it('mendisposisi lewat formulir', function () {
        $tiket = tiketLayar($this->mahasiswa);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.helpdesk.disposisi', $tiket), [
                'unit_kerja_id' => $this->unitLain->id,
                'petugas_staff_id' => $this->tik->id,
                'prioritas' => 'mendesak',
                'catatan' => 'Masalah akun.',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $tiket->refresh();

        expect($tiket->status)->toBe(StatusTiket::Didisposisi)
            ->and($tiket->unit_kerja_id)->toBe($this->unitLain->id)
            ->and($tiket->petugas_staff_id)->toBe($this->tik->id);
    });

    it('menolak disposisi yang tidak menamai siapa pun di FormRequest', function () {
        $tiket = tiketLayar($this->mahasiswa);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.helpdesk.disposisi', $tiket), ['catatan' => 'Tolong urus.'])
            ->assertSessionHasErrors('unit_kerja_id');
    });

    it('menolak transisi yang tidak sah lewat layar dengan pesan terbaca', function () {
        $tiket = tiketLayar($this->mahasiswa);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.helpdesk.status', $tiket), ['status' => 'selesai'])
            ->assertRedirect()
            ->assertSessionHas('galat');

        expect($tiket->refresh()->status)->toBe(StatusTiket::Baru);
    });

    it('menutup lewat layar wajib beralasan, lalu berhasil dengan alasan', function () {
        $tiket = tiketLayar($this->mahasiswa);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.helpdesk.status', $tiket), ['status' => 'ditutup'])
            ->assertSessionHasErrors('catatan');

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.helpdesk.status', $tiket), ['status' => 'ditutup', 'catatan' => 'Duplikat tiket lain.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($tiket->refresh()->status)->toBe(StatusTiket::Ditutup)
            ->and($tiket->ditutup_at)->not->toBeNull();
    });

    it('mengelola kategori dan FAQ lewat layar pengaturan', function () {
        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.helpdesk.kategori.simpan'), [
                'nama' => 'Keuangan & Tagihan',
                'unit_kerja_id' => $this->unitLain->id,
                'sla_jam' => 72,
                'urutan' => 1,
                'is_active' => 1,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $kategori = HelpdeskKategori::where('nama', 'Keuangan & Tagihan')->firstOrFail();

        expect($kategori->sla_jam)->toBe(72)->and($kategori->unit_kerja_id)->toBe($this->unitLain->id);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.helpdesk.faq.simpan'), [
                'helpdesk_kategori_id' => $kategori->id,
                'pertanyaan' => 'Bagaimana mengunggah bukti transfer?',
                'jawaban' => 'Lampirkan pada tiket kategori Keuangan.',
                'is_published' => 0,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $faq = HelpdeskFaq::firstOrFail();

        expect($faq->is_published)->toBeFalse();

        $this->actingAs($this->baak, 'staff')
            ->put(route('admin.helpdesk.faq.perbarui', $faq), [
                'helpdesk_kategori_id' => $kategori->id,
                'pertanyaan' => $faq->pertanyaan,
                'jawaban' => $faq->jawaban,
                'is_published' => 1,
            ])->assertRedirect()->assertSessionHasNoErrors();

        expect($faq->refresh()->is_published)->toBeTrue();

        $this->actingAs($this->baak, 'staff')
            ->delete(route('admin.helpdesk.faq.hapus', $faq))
            ->assertRedirect();

        expect(HelpdeskFaq::count())->toBe(0);
    });
});

describe('FAQ publik', function () {
    it('tampil tanpa login dan hanya memuat yang terbit', function () {
        HelpdeskFaq::create(['pertanyaan' => 'Lupa kata sandi?', 'jawaban' => 'Pakai tautan lupa kata sandi.', 'is_published' => true]);
        HelpdeskFaq::create(['pertanyaan' => 'DRAF-RAHASIA', 'jawaban' => 'Belum siap.', 'is_published' => false]);

        $this->get(route('bantuan'))
            ->assertOk()
            ->assertSee('Lupa kata sandi?')
            ->assertDontSee('DRAF-RAHASIA');
    });

    it('tidak membocorkan nama siapa pun dan boleh diindeks', function () {
        tiketLayar($this->mahasiswa);

        $this->get(route('bantuan'))
            ->assertOk()
            ->assertDontSee($this->mahasiswa->nama)
            ->assertSee('index, follow', false);
    });
});

describe('notifikasi', function () {
    it('kategori helpdesk tampil di preferensi dan dapat dimatikan', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('notifikasi.preferensi'))
            ->assertOk()
            ->assertSee('Helpdesk');

        expect(KategoriNotifikasi::Helpdesk->wajib())->toBeFalse();
    });
});
