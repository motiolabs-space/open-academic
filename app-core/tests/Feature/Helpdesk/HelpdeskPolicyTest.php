<?php

declare(strict_types=1);

use App\DTOs\Helpdesk\DisposisiData;
use App\DTOs\Helpdesk\TiketBaruData;
use App\Enums\SemesterType;
use App\Models\Akademik\TahunAkademik;
use App\Models\Helpdesk\HelpdeskKategori;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Helpdesk\HelpdeskService;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;

/**
 * Batas otorisasi helpdesk, per portal.
 *
 * Tiap aturan diuji berpasangan — yang boleh dan yang tidak — pada rute dan
 * binding yang sama, supaya penolakan tidak lulus hanya karena salah rute.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    Portal::lupakanTerm();

    $this->baakUnit = UnitKerja::create(['kode' => 'BAAK', 'nama' => 'BAAK']);
    $this->keuUnit = UnitKerja::create(['kode' => 'BAUK', 'nama' => 'Keuangan']);

    $this->baak = Staff::factory()->create(['unit_kerja_id' => $this->baakUnit->id]);
    $this->baak->assignRole('baak');

    $this->keuangan = Staff::factory()->create(['unit_kerja_id' => $this->keuUnit->id]);
    $this->keuangan->assignRole('keuangan');

    $this->pimpinan = Staff::factory()->create(['unit_kerja_id' => $this->keuUnit->id]);
    $this->pimpinan->assignRole('pimpinan');

    $this->kategori = HelpdeskKategori::create(['nama' => 'Akademik', 'unit_kerja_id' => $this->baakUnit->id]);
    $this->kategoriKeu = HelpdeskKategori::create(['nama' => 'Keuangan', 'unit_kerja_id' => $this->keuUnit->id]);

    $this->saya = Mahasiswa::factory()->create();
    $this->saya->assignRole('mahasiswa');

    $this->orangLain = Mahasiswa::factory()->create();
    $this->orangLain->assignRole('mahasiswa');

    $this->dosen = Dosen::factory()->create();
    $this->dosen->assignRole('dosen');

    $this->helpdesk = app(HelpdeskService::class);

    $this->tiket = $this->helpdesk->buat($this->saya, new TiketBaruData(
        kategoriId: $this->kategori->id,
        judul: 'Nilai belum muncul',
        isi: 'Nilai mata kuliah Basis Data belum tampil di KHS saya.',
    ));

    $this->tiketKeu = $this->helpdesk->buat($this->orangLain, new TiketBaruData(
        kategoriId: $this->kategoriKeu->id,
        judul: 'Transfer belum tercatat',
        isi: 'Saya sudah transfer tetapi tagihan masih belum lunas.',
    ));
});

describe('gate', function () {
    it('pemohon melihat tiketnya sendiri, bukan tiket orang lain', function () {
        expect(Gate::forUser($this->saya)->allows('view', $this->tiket))->toBeTrue()
            ->and(Gate::forUser($this->orangLain)->allows('view', $this->tiket))->toBeFalse()
            ->and(Gate::forUser($this->dosen)->allows('view', $this->tiket))->toBeFalse();
    });

    it('staf unit melihat tiket unitnya, tidak unit lain; baak melihat semua', function () {
        expect(Gate::forUser($this->keuangan)->allows('view', $this->tiketKeu))->toBeTrue()
            ->and(Gate::forUser($this->keuangan)->allows('view', $this->tiket))->toBeFalse()
            ->and(Gate::forUser($this->baak)->allows('view', $this->tiket))->toBeTrue()
            ->and(Gate::forUser($this->baak)->allows('view', $this->tiketKeu))->toBeTrue();
    });

    it('staf yang ditugaskan melihat tiket itu meski di unit lain', function () {
        $this->helpdesk->disposisi($this->tiket, $this->baak, new DisposisiData(petugasStaffId: $this->keuangan->id));

        expect(Gate::forUser($this->keuangan)->allows('view', $this->tiket->refresh()))->toBeTrue();
    });

    it('pimpinan membaca tetapi tidak menangani; hanya manage yang mengelola kategori', function () {
        expect(Gate::forUser($this->pimpinan)->allows('view', $this->tiketKeu))->toBeTrue()
            ->and(Gate::forUser($this->pimpinan)->allows('handle', $this->tiketKeu))->toBeFalse()
            ->and(Gate::forUser($this->keuangan)->allows('handle', $this->tiketKeu))->toBeTrue()
            ->and(Gate::forUser($this->keuangan)->allows('manage', HelpdeskTiket::class))->toBeFalse()
            ->and(Gate::forUser($this->baak)->allows('manage', HelpdeskTiket::class))->toBeTrue();
    });

    it('pemohon dapat membuat; staf tidak', function () {
        expect(Gate::forUser($this->saya)->allows('create', HelpdeskTiket::class))->toBeTrue()
            ->and(Gate::forUser($this->dosen)->allows('create', HelpdeskTiket::class))->toBeTrue()
            ->and(Gate::forUser($this->baak)->allows('create', HelpdeskTiket::class))->toBeFalse();
    });
});

describe('portal mahasiswa', function () {
    it('membuka tiket sendiri, 403 untuk tiket orang lain, 403 untuk dosen', function () {
        $this->actingAs($this->saya, 'mahasiswa')
            ->get(route('mahasiswa.helpdesk.show', $this->tiket))
            ->assertOk()
            ->assertSee('Nilai belum muncul');

        $this->actingAs($this->orangLain, 'mahasiswa')
            ->get(route('mahasiswa.helpdesk.show', $this->tiket))
            ->assertForbidden();

        $this->actingAs($this->dosen, 'dosen')
            ->get(route('dosen.helpdesk.show', $this->tiket))
            ->assertForbidden();
    });

    it('daftar hanya memuat tiket milik sendiri', function () {
        $this->actingAs($this->orangLain, 'mahasiswa')
            ->get(route('mahasiswa.helpdesk'))
            ->assertOk()
            ->assertSee('Transfer belum tercatat')
            ->assertDontSee('Nilai belum muncul');
    });

    it('membalas tiket orang lain ditolak sebelum menyentuh service', function () {
        $this->actingAs($this->orangLain, 'mahasiswa')
            ->post(route('mahasiswa.helpdesk.balas', $this->tiket), ['isi' => 'Saya juga mengalami.'])
            ->assertForbidden();

        expect($this->tiket->balasan()->count())->toBe(0);
    });

    it('mahasiswa tidak dapat membuka layar staf', function () {
        $this->actingAs($this->saya, 'mahasiswa')
            ->get(route('admin.helpdesk'))
            ->assertRedirect();
    });
});

describe('portal staf', function () {
    it('staf unit lain mendapat 403 pada tiket yang bukan antreannya; baak 200', function () {
        $this->actingAs($this->keuangan, 'staff')
            ->get(route('admin.helpdesk.show', $this->tiket))
            ->assertForbidden();

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.helpdesk.show', $this->tiket))
            ->assertOk()
            ->assertSee('Nilai belum muncul');
    });

    it('antrean staf unit tidak memuat tiket unit lain', function () {
        $this->actingAs($this->keuangan, 'staff')
            ->get(route('admin.helpdesk'))
            ->assertOk()
            ->assertSee('Transfer belum tercatat')
            ->assertDontSee('Nilai belum muncul');
    });

    it('disposisi dari staf tanpa izin tangani ditolak', function () {
        $this->actingAs($this->pimpinan, 'staff')
            ->post(route('admin.helpdesk.disposisi', $this->tiketKeu), ['unit_kerja_id' => $this->baakUnit->id])
            ->assertForbidden();
    });

    it('catatan internal tidak pernah tampil ke pemohon', function () {
        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.helpdesk.balas', $this->tiket), [
                'isi' => 'RAHASIA-INTERNAL: cek dulu ke dosen pengampu.',
                'internal' => 1,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->saya, 'mahasiswa')
            ->get(route('mahasiswa.helpdesk.show', $this->tiket))
            ->assertOk()
            ->assertDontSee('RAHASIA-INTERNAL');

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.helpdesk.show', $this->tiket))
            ->assertOk()
            ->assertSee('RAHASIA-INTERNAL');
    });

    it('pengaturan kategori & FAQ hanya untuk manage', function () {
        $this->actingAs($this->keuangan, 'staff')
            ->get(route('admin.helpdesk.pengaturan'))
            ->assertForbidden();

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.helpdesk.pengaturan'))
            ->assertOk();
    });
});
