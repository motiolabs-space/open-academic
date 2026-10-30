<?php

declare(strict_types=1);

use App\DTOs\Helpdesk\BalasanData;
use App\DTOs\Helpdesk\DisposisiData;
use App\DTOs\Helpdesk\TiketBaruData;
use App\Enums\SemesterType;
use App\Enums\StatusTiket;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Helpdesk\HelpdeskKategori;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Notifications\Helpdesk\TiketDibalas;
use App\Services\Helpdesk\HelpdeskService;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Staff as helpdesk applicants, and officers typing tickets in for students
 * and lecturers — without either opening a window onto tickets outside their
 * unit, and without anybody ever handling their own ticket.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    Portal::lupakanTerm();

    $this->unitBaak = UnitKerja::create(['kode' => 'BAAK', 'nama' => 'BAAK']);
    $this->unitKeu = UnitKerja::create(['kode' => 'BAUK', 'nama' => 'Keuangan']);
    $this->unitTik = UnitKerja::create(['kode' => 'TIK', 'nama' => 'UPT TIK']);

    $this->kategoriTik = HelpdeskKategori::create(['nama' => 'Perangkat & Akun', 'unit_kerja_id' => $this->unitTik->id]);
    $this->kategoriBaak = HelpdeskKategori::create(['nama' => 'Akademik', 'unit_kerja_id' => $this->unitBaak->id]);
    $this->kategoriKeu = HelpdeskKategori::create(['nama' => 'Keuangan', 'unit_kerja_id' => $this->unitKeu->id]);

    $this->keuangan = Staff::factory()->create(['unit_kerja_id' => $this->unitKeu->id]);
    $this->keuangan->assignRole('keuangan');

    $this->petugasTik = Staff::factory()->create(['unit_kerja_id' => $this->unitTik->id]);
    $this->petugasTik->assignRole('tik');

    $this->baak = Staff::factory()->create(['unit_kerja_id' => $this->unitBaak->id]);
    $this->baak->assignRole('baak');

    $this->helpdesk = app(HelpdeskService::class);
});

function tiketStaf(Staff $pemohon, ?HelpdeskKategori $kategori = null): HelpdeskTiket
{
    return test()->helpdesk->buat($pemohon, new TiketBaruData(
        kategoriId: ($kategori ?? test()->kategoriTik)->id,
        judul: 'Laptop tidak menyala',
        isi: 'Laptop kantor bagian keuangan tidak menyala sejak pagi ini.',
    ));
}

describe('HelpdeskService', function () {
    it('menerima staf sebagai pemohon ke unit lain', function () {
        $tiket = tiketStaf($this->keuangan);

        expect($tiket->milik($this->keuangan))->toBeTrue()
            ->and($tiket->unit_kerja_id)->toBe($this->unitTik->id)
            ->and($tiket->dicatat_oleh_staff_id)->toBeNull();
    });

    it('menolak tiket staf ke unitnya sendiri', function () {
        expect(fn () => tiketStaf($this->keuangan, $this->kategoriKeu))
            ->toThrow(AturanAkademikException::class, 'unit Anda sendiri');
    });

    it('mencatat pencatat pada tiket atas nama mahasiswa, tetapi tidak atas nama staf', function () {
        $mahasiswa = Mahasiswa::factory()->create();

        $tiket = $this->helpdesk->buat($mahasiswa, new TiketBaruData($this->kategoriKeu->id, 'Tagihan ganda', 'Tagihan semester ini muncul dua kali di portal saya.'), $this->baak);

        expect($tiket->milik($mahasiswa))->toBeTrue()
            ->and($tiket->dicatat_oleh_staff_id)->toBe($this->baak->id)
            ->and(fn () => $this->helpdesk->buat($this->keuangan, new TiketBaruData($this->kategoriTik->id, 'Atas nama staf', 'Isi yang cukup panjang untuk diterima.'), $this->baak))
            ->toThrow(AturanAkademikException::class, 'mahasiswa atau dosen');
    });

    it('memperlakukan balasan staf pada tiketnya sendiri sebagai balasan pemohon', function () {
        $tiket = tiketStaf($this->keuangan);
        $this->helpdesk->balas($tiket, $this->petugasTik, new BalasanData('Mohon sebutkan nomor inventaris laptopnya.'));

        expect($tiket->refresh()->status)->toBe(StatusTiket::MenungguPemohon);

        $this->helpdesk->balas($tiket, $this->keuangan, new BalasanData('Nomor inventaris INV-2026-014.'));

        expect($tiket->refresh()->status)->toBe(StatusTiket::Diproses)
            ->and($tiket->petugas_staff_id)->toBe($this->petugasTik->id)
            ->and(fn () => $this->helpdesk->balas($tiket, $this->keuangan, new BalasanData('Catatan', internal: true)))
            ->toThrow(AturanAkademikException::class, 'Catatan internal');
    });

    it('tidak membiarkan pemohon menangani tiketnya sendiri meski didisposisi ke unitnya', function () {
        $tiket = tiketStaf($this->keuangan);
        $this->helpdesk->disposisi($tiket, $this->baak, new DisposisiData(unitKerjaId: $this->unitKeu->id));

        expect(fn () => $this->helpdesk->ubahStatus($tiket->refresh(), $this->keuangan, StatusTiket::Diproses))
            ->toThrow(AturanAkademikException::class, 'ajukan sendiri')
            ->and(fn () => $this->helpdesk->disposisi($tiket, $this->keuangan, new DisposisiData(unitKerjaId: $this->unitTik->id)))
            ->toThrow(AturanAkademikException::class, 'ajukan sendiri')
            ->and($this->helpdesk->antreanUntuk($this->keuangan)->whereKey($tiket->id)->exists())->toBeFalse();
    });
});

describe('layar staf sebagai pemohon', function () {
    it('membuat tiket dari /admin/tiket-saya dan melihatnya di sana', function () {
        $this->actingAs($this->keuangan, 'staff')
            ->post(route('admin.saya.helpdesk.store'), [
                'helpdesk_kategori_id' => $this->kategoriTik->id,
                'judul' => 'Printer macet',
                'isi' => 'Printer di ruang keuangan macet dan menampilkan galat E-04.',
                'prioritas' => 'normal',
            ])->assertRedirect();

        $tiket = HelpdeskTiket::firstOrFail();

        expect($tiket->milik($this->keuangan))->toBeTrue();

        $this->get(route('admin.saya.helpdesk'))->assertOk()->assertSee('Printer macet')->assertSee(__('helpdesk.staf.judul'));
        $this->get(route('admin.saya.helpdesk.show', $tiket))->assertOk()->assertSee('galat E-04');

        // Unit tujuan melihatnya di antreannya.
        $this->actingAs($this->petugasTik, 'staff')->get(route('admin.helpdesk'))->assertOk()->assertSee('Printer macet');
    });

    it('menolak kategori unit sendiri lewat layar dengan pesan', function () {
        $this->actingAs($this->keuangan, 'staff')
            ->from(route('admin.saya.helpdesk'))
            ->post(route('admin.saya.helpdesk.store'), [
                'helpdesk_kategori_id' => $this->kategoriKeu->id,
                'judul' => 'Ke unit sendiri',
                'isi' => 'Ini seharusnya pekerjaan antrean, bukan tiket ke diri sendiri.',
            ])->assertSessionHas('galat');

        expect(HelpdeskTiket::count())->toBe(0);
    });

    it('tidak membuka tiket staf lain maupun tiket di luar unit lewat jalur mana pun', function () {
        $milikKeuangan = tiketStaf($this->keuangan);

        $sarpras = Staff::factory()->create(['unit_kerja_id' => $this->unitBaak->id]);
        $sarpras->assignRole('sarpras');

        $this->actingAs($sarpras, 'staff')->get(route('admin.saya.helpdesk.show', $milikKeuangan))->assertForbidden();
        $this->actingAs($sarpras, 'staff')->get(route('admin.helpdesk.show', $milikKeuangan))->assertForbidden();
        $this->actingAs($sarpras, 'staff')->post(route('admin.saya.helpdesk.balas', $milikKeuangan), ['isi' => 'Ikut campur'])->assertForbidden();
    });

    it('mengarahkan pemohon ke layar pemohon dan menyembunyikan catatan internal, meski tiketnya kini di unitnya', function () {
        $tiket = tiketStaf($this->keuangan);
        $this->helpdesk->disposisi($tiket, $this->baak, new DisposisiData(unitKerjaId: $this->unitKeu->id, catatan: 'Rahasia internal unit'));
        $this->helpdesk->balas($tiket->refresh(), $this->baak, new BalasanData('Catatan internal sangat rahasia', internal: true));

        $this->actingAs($this->keuangan, 'staff')
            ->get(route('admin.helpdesk.show', $tiket))
            ->assertRedirect(route('admin.saya.helpdesk.show', $tiket));

        $this->actingAs($this->keuangan, 'staff')
            ->get(route('admin.saya.helpdesk.show', $tiket))
            ->assertOk()
            ->assertDontSee('Catatan internal sangat rahasia')
            ->assertDontSee('Rahasia internal unit');

        $this->actingAs($this->keuangan, 'staff')
            ->post(route('admin.helpdesk.status', $tiket), ['status' => 'diproses'])
            ->assertForbidden();

        $this->actingAs($this->keuangan, 'staff')->get(route('admin.helpdesk'))->assertOk()->assertDontSee($tiket->nomor);
    });

    it('menaut notifikasi staf ke layar pemohonnya', function () {
        $tiket = tiketStaf($this->keuangan);

        expect((new TiketDibalas($tiket, $tiket->balasan()->make()))->tautan($this->keuangan))
            ->toBe(route('admin.saya.helpdesk.show', $tiket));
    });

    it('menjaga daftar tiket staf datar berapa pun banyaknya tiket', function () {
        tiketStaf($this->keuangan);

        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->keuangan, 'staff')->get(route('admin.saya.helpdesk'))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $awal = $hitung();
        foreach (range(1, 8) as $_) {
            tiketStaf($this->keuangan);
        }

        expect($hitung())->toBeLessThanOrEqual($awal)->toBeLessThanOrEqual(20);
    });
});

describe('mencatat atas nama', function () {
    it('mencatat tiket atas nama mahasiswa dari NIM; tiketnya milik mahasiswa', function () {
        $mahasiswa = Mahasiswa::factory()->create();
        $mahasiswa->assignRole('mahasiswa');

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.saya.helpdesk.store'), [
                'helpdesk_kategori_id' => $this->kategoriKeu->id,
                'judul' => 'Tagihan ganda',
                'isi' => 'Mahasiswa datang ke loket: tagihan semester muncul dua kali.',
                'atas_nama' => '1',
                'atas_nama_identitas' => $mahasiswa->nim,
            ])->assertSessionHas('sukses');

        $tiket = HelpdeskTiket::firstOrFail();

        expect($tiket->milik($mahasiswa))->toBeTrue()
            ->and($tiket->dicatat_oleh_staff_id)->toBe($this->baak->id);

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.helpdesk.show', $tiket))
            ->assertOk()
            ->assertSee(__('helpdesk.staf.dicatat_oleh', ['nama' => $this->baak->nama]));

        // Pencatat tidak mendapat akses lanjutan: tiketnya di antrean keuangan.
        $this->actingAs($this->baak, 'staff')->get(route('admin.saya.helpdesk'))->assertOk()->assertDontSee('Tagihan ganda');
        $this->actingAs($this->keuangan, 'staff')->get(route('admin.helpdesk.show', $tiket))->assertOk()
            ->assertSee(__('helpdesk.staf.dicatat_oleh', ['nama' => $this->baak->nama]));
    });

    it('menolak nomor yang tidak dikenal', function () {
        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.saya.helpdesk.store'), [
                'helpdesk_kategori_id' => $this->kategoriKeu->id,
                'judul' => 'Tagihan ganda',
                'isi' => 'Mahasiswa datang ke loket: tagihan semester muncul dua kali.',
                'atas_nama' => '1',
                'atas_nama_identitas' => '0000000000',
            ])->assertSessionHasErrors('atas_nama_identitas');

        expect(HelpdeskTiket::count())->toBe(0);
    });

    it('hanya untuk petugas yang menangani tiket', function () {
        $mahasiswa = Mahasiswa::factory()->create();

        $pimpinan = Staff::factory()->create(['unit_kerja_id' => $this->unitKeu->id]);
        $pimpinan->assignRole('pimpinan');

        $this->actingAs($pimpinan, 'staff')
            ->post(route('admin.saya.helpdesk.store'), [
                'helpdesk_kategori_id' => $this->kategoriBaak->id,
                'judul' => 'Atas nama tanpa hak',
                'isi' => 'Pimpinan mencoba mencatat atas nama mahasiswa.',
                'atas_nama' => '1',
                'atas_nama_identitas' => $mahasiswa->nim,
            ])->assertForbidden();

        $this->actingAs($pimpinan, 'staff')->get(route('admin.saya.helpdesk'))->assertOk()
            ->assertDontSee(__('helpdesk.staf.atas_nama_judul'));

        expect(HelpdeskTiket::count())->toBe(0);
    });
});
