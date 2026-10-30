<?php

declare(strict_types=1);

use App\DTOs\Keuangan\AjuanBeasiswaData;
use App\DTOs\Keuangan\PeriodeBeasiswaData;
use App\Enums\InvoiceStatus;
use App\Enums\JenisBeasiswa;
use App\Enums\JenisItemTagihan;
use App\Enums\SemesterType;
use App\Enums\StatusAjuanBeasiswa;
use App\Enums\StatusPenerima;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\BeasiswaAjuan;
use App\Models\Keuangan\BeasiswaPenerima;
use App\Models\Keuangan\BeasiswaPeriode;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Sdm\Staff;
use App\Notifications\Keuangan\AjuanBeasiswaDiputus;
use App\Services\Keuangan\AjuanBeasiswaService;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Scholarship calls and applications (ADR-040 §2). Until now the finance
 * office could only award a scheme to a student it already had in mind; there
 * was no way for a student to ask.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    Notification::fake();

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->berikut = TahunAkademik::factory()->term(2026, SemesterType::Genap)->create();
    Portal::lupakanTerm();

    $this->keuangan = Staff::factory()->create();
    $this->keuangan->assignRole('keuangan');

    $this->mahasiswa = mahasiswaBerIpk(3.40);
    $this->lain = mahasiswaBerIpk(3.80);

    $this->skema = Beasiswa::create([
        'kode' => 'BS-PRESTASI',
        'nama' => 'Beasiswa Prestasi Akademik',
        'jenis' => JenisBeasiswa::Internal,
        'persen' => 50,
        'kuota' => 1,
        'is_active' => true,
    ]);

    $this->layanan = app(AjuanBeasiswaService::class);

    $this->periode = $this->layanan->bukaPeriode($this->skema, new PeriodeBeasiswaData(
        tahunAkademikId: $this->berikut->id,
        nama: 'Pendaftaran Beasiswa Prestasi Genap 2026',
        dibukaMulai: Carbon::today()->subDay(),
        dibukaSampai: Carbon::today()->addDays(14),
        syarat: 'Aktif dan tidak sedang menerima beasiswa lain.',
        ipkMinimum: 3.25,
        berkasDiminta: ['Transkrip nilai', 'Surat rekomendasi dosen wali'],
    ), $this->keuangan);
});

function mahasiswaBerIpk(float $ipk): Mahasiswa
{
    $m = Mahasiswa::factory()->create(['status' => StudentStatus::Aktif]);
    $m->assignRole('mahasiswa');

    StatusMahasiswa::create([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'status' => StudentStatus::Aktif,
        'semester_ke' => 3,
        'sks_semester' => 20,
        'sks_kumulatif' => 60,
        'ips' => $ipk,
        'ipk' => $ipk,
    ]);

    return $m;
}

/** @return array<int, UploadedFile> */
function berkasBeasiswaLengkap(): array
{
    return [
        UploadedFile::fake()->create('transkrip.pdf', 20, 'application/pdf'),
        UploadedFile::fake()->create('rekomendasi.pdf', 20, 'application/pdf'),
    ];
}

function ajukanBeasiswaUji(?Mahasiswa $mahasiswa = null): BeasiswaAjuan
{
    return test()->layanan->ajukan(
        test()->periode,
        $mahasiswa ?? test()->mahasiswa,
        new AjuanBeasiswaData('Orang tua saya pensiun tahun ini dan IPK saya stabil di atas 3,25.', berkasBeasiswaLengkap()),
    );
}

describe('periode pendaftaran', function () {
    it('membuka periode dan menampilkannya sebagai terbuka', function () {
        expect($this->periode->dibuka())->toBeTrue()
            ->and($this->periode->daftarBerkas())->toBe(['Transkrip nilai', 'Surat rekomendasi dosen wali'])
            ->and($this->layanan->periodeTerbuka()->pluck('id')->all())->toBe([$this->periode->id]);
    });

    it('menolak membuka periode untuk skema nonaktif', function () {
        $this->skema->update(['is_active' => false]);

        expect(fn () => $this->layanan->bukaPeriode($this->skema, new PeriodeBeasiswaData(
            $this->berikut->id, 'Periode', Carbon::today(), Carbon::today()->addDay(),
        ), $this->keuangan))->toThrow(AturanAkademikException::class, 'nonaktif');
    });

    it('menutup periode lebih awal tanpa menghapus ajuan yang sudah masuk', function () {
        $ajuan = ajukanBeasiswaUji();

        $this->layanan->tutupPeriode($this->periode, $this->keuangan);

        expect($this->periode->refresh()->dibuka())->toBeFalse()
            ->and($this->layanan->periodeTerbuka())->toBeEmpty()
            ->and($this->layanan->halangan($this->periode, $this->lain))->toContain('ditutup')
            ->and($ajuan->refresh()->status)->toBe(StatusAjuanBeasiswa::Diajukan);

        expect(fn () => $this->layanan->tutupPeriode($this->periode, $this->keuangan))
            ->toThrow(AturanAkademikException::class);
    });

    it('membuka periode lewat layar keuangan', function () {
        $this->actingAs($this->keuangan, 'staff')
            ->post(route('admin.beasiswa.periode.buka'), [
                'beasiswa' => $this->skema->uuid,
                'nama' => 'Keringanan UKT Genap',
                'tahun_akademik_id' => $this->berikut->id,
                'dibuka_mulai' => Carbon::today()->toDateString(),
                'dibuka_sampai' => Carbon::today()->addWeek()->toDateString(),
                'berkas_diminta' => "Slip gaji orang tua\r\n\r\nKartu keluarga",
            ])
            ->assertRedirect();

        expect(BeasiswaPeriode::where('nama', 'Keringanan UKT Genap')->sole()->daftarBerkas())
            ->toBe(['Slip gaji orang tua', 'Kartu keluarga']);
    });
});

describe('syarat yang diperiksa sistem', function () {
    it('tidak menghalangi mahasiswa yang memenuhi syarat', function () {
        expect($this->layanan->halangan($this->periode, $this->mahasiswa))->toBeNull();
    });

    it('menghalangi IPK di bawah minimum, status bukan aktif, dan penerima aktif skema yang sama', function () {
        $rendah = mahasiswaBerIpk(2.90);
        expect($this->layanan->halangan($this->periode, $rendah))->toContain('IPK minimum 3,25');

        $this->lain->update(['status' => StudentStatus::Cuti]);
        expect($this->layanan->halangan($this->periode, $this->lain))->toContain('berstatus Aktif');

        $penerima = mahasiswaBerIpk(3.90);
        BeasiswaPenerima::create([
            'beasiswa_id' => $this->skema->id,
            'mahasiswa_id' => $penerima->id,
            'tahun_akademik_mulai_id' => $this->term->id,
            'status' => StatusPenerima::Aktif,
            'kunci_aktif' => BeasiswaPenerima::kunci($this->skema->id, $penerima->id),
        ]);
        expect($this->layanan->halangan($this->periode, $penerima))->toContain('masih tercatat');

        expect(fn () => ajukanBeasiswaUji($rendah))->toThrow(AturanAkademikException::class, 'IPK');
    });
});

describe('ajuan', function () {
    it('menyimpan ajuan dengan IPK saat mengajukan dan berkas berlabel', function () {
        $ajuan = ajukanBeasiswaUji();

        expect($ajuan->status)->toBe(StatusAjuanBeasiswa::Diajukan)
            ->and((float) $ajuan->ipk_saat_ajuan)->toBe(3.40)
            ->and(array_column($ajuan->berkas, 'label'))->toBe(['Transkrip nilai', 'Surat rekomendasi dosen wali']);

        foreach ($ajuan->berkas as $b) {
            Storage::disk('local')->assertExists($b['path']);
            expect($b['path'])->toStartWith('beasiswa/'.$this->mahasiswa->uuid.'/');
        }

        // Tidak ada uang yang bergerak karena sebuah ajuan.
        expect(BeasiswaPenerima::count())->toBe(0);

        expect(fn () => ajukanBeasiswaUji())->toThrow(AturanAkademikException::class, 'sudah mengajukan');
    });

    it('menolak ajuan dengan berkas kurang dan tidak meninggalkan berkas yatim', function () {
        expect(fn () => $this->layanan->ajukan($this->periode, $this->mahasiswa, new AjuanBeasiswaData(
            'Alasan yang cukup panjang untuk diperiksa.',
            [UploadedFile::fake()->create('transkrip.pdf', 20, 'application/pdf')],
        )))->toThrow(AturanAkademikException::class, 'setiap berkas');

        expect(BeasiswaAjuan::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('mengajukan lewat portal dan mewajibkan setiap berkas', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.beasiswa.ajukan', $this->periode), [
                'motivasi' => 'Orang tua saya pensiun tahun ini dan IPK saya stabil.',
                'berkas' => [UploadedFile::fake()->create('transkrip.pdf', 20, 'application/pdf')],
            ])
            ->assertSessionHasErrors('berkas.1');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.beasiswa.ajukan', $this->periode), [
                'motivasi' => 'Orang tua saya pensiun tahun ini dan IPK saya stabil.',
                'berkas' => berkasBeasiswaLengkap(),
                'mahasiswa_id' => $this->lain->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect(BeasiswaAjuan::where('mahasiswa_id', $this->mahasiswa->id)->count())->toBe(1)
            ->and(BeasiswaAjuan::where('mahasiswa_id', $this->lain->id)->count())->toBe(0);
    });
});

describe('keputusan keuangan', function () {
    it('menetapkan lewat jalur penetapan yang sudah ada dan memotong tagihan yang dicakup', function () {
        $tagihan = Tagihan::create([
            'nomor' => 'INV/BSA/0001',
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->berikut->id,
            'keterangan' => 'Biaya kuliah',
            'total' => 4_000_000,
            'terbayar' => 0,
            'status' => InvoiceStatus::BelumBayar,
            'jatuh_tempo' => now()->addMonth(),
        ]);
        TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis' => JenisItemTagihan::Tagihan, 'nama' => 'UKT', 'nominal' => 4_000_000]);

        $ajuan = ajukanBeasiswaUji();

        $this->actingAs($this->keuangan, 'staff')
            ->post(route('admin.beasiswa.ajuan.putuskan', $ajuan), ['tetapkan' => 1, 'nomor_sk' => 'SK/BS/2026/01'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $ajuan->refresh();
        $penerima = $ajuan->penerima;

        expect($ajuan->status)->toBe(StatusAjuanBeasiswa::Ditetapkan)
            ->and($penerima->status)->toBe(StatusPenerima::Aktif)
            ->and($penerima->tahun_akademik_mulai_id)->toBe($this->berikut->id)
            ->and($penerima->nomor_sk)->toBe('SK/BS/2026/01')
            ->and($penerima->diputus_by_staff_id)->toBe($this->keuangan->id)
            ->and((int) $tagihan->fresh()->total)->toBe(2_000_000);

        Notification::assertSentTo($this->mahasiswa, AjuanBeasiswaDiputus::class,
            fn (AjuanBeasiswaDiputus $n): bool => $n->tone() === 'success');
    });

    it('membiarkan ajuan menunggu bila kuota skema sudah penuh', function () {
        $ajuanA = ajukanBeasiswaUji();
        $ajuanB = ajukanBeasiswaUji($this->lain);

        $this->layanan->tetapkan($ajuanA, $this->keuangan);

        expect(fn () => $this->layanan->tetapkan($ajuanB, $this->keuangan))
            ->toThrow(AturanAkademikException::class, 'Kuota');

        expect($ajuanB->refresh()->status)->toBe(StatusAjuanBeasiswa::Diajukan)
            ->and($ajuanB->beasiswa_penerima_id)->toBeNull()
            ->and(BeasiswaPenerima::count())->toBe(1);
    });

    it('mewajibkan alasan penolakan dan memberi tahu mahasiswa', function () {
        $ajuan = ajukanBeasiswaUji();

        $this->actingAs($this->keuangan, 'staff')
            ->post(route('admin.beasiswa.ajuan.putuskan', $ajuan), ['tetapkan' => 0])
            ->assertSessionHasErrors('catatan');

        $this->layanan->tolak($ajuan, $this->keuangan, 'Surat rekomendasi tidak ditandatangani.');

        expect($ajuan->refresh()->status)->toBe(StatusAjuanBeasiswa::Ditolak)
            ->and(BeasiswaPenerima::count())->toBe(0);

        Notification::assertSentTo($this->mahasiswa, AjuanBeasiswaDiputus::class);

        expect(fn () => $this->layanan->tetapkan($ajuan, $this->keuangan))
            ->toThrow(AturanAkademikException::class, 'sudah diputus');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.beasiswa'))
            ->assertOk()
            ->assertSee('Surat rekomendasi tidak ditandatangani.');
    });
});

describe('batas otorisasi', function () {
    it('menyembunyikan ajuan dan berkas mahasiswa lain', function () {
        $milikLain = ajukanBeasiswaUji($this->lain);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.beasiswa'))
            ->assertOk()
            ->assertDontSee($milikLain->uuid);

        $this->actingAs($this->mahasiswa, 'mahasiswa')->get(route('berkas.beasiswa', [$milikLain, 0]))->assertForbidden();
        $this->actingAs($this->lain, 'mahasiswa')->get(route('berkas.beasiswa', [$milikLain, 0]))->assertOk();
        $this->actingAs($this->keuangan, 'staff')->get(route('berkas.beasiswa', [$milikLain, 1]))->assertOk();
        $this->actingAs($this->keuangan, 'staff')->get(route('berkas.beasiswa', [$milikLain, 5]))->assertNotFound();
    });

    it('hanya staf keuangan.manage yang memutus', function () {
        $ajuan = ajukanBeasiswaUji();

        $baak = Staff::factory()->create();
        $baak->assignRole('baak');

        $this->actingAs($baak, 'staff')
            ->post(route('admin.beasiswa.ajuan.putuskan', $ajuan), ['tetapkan' => 1])
            ->assertForbidden();

        $this->actingAs($this->keuangan, 'staff')
            ->get(route('admin.beasiswa.periode', $this->periode))
            ->assertOk()
            ->assertSee($ajuan->uuid);

        $this->actingAs($this->keuangan, 'staff')
            ->get(route('admin.beasiswa'))
            ->assertOk()
            ->assertSee($this->periode->uuid);

        expect($ajuan->refresh()->status)->toBe(StatusAjuanBeasiswa::Diajukan);
    });
});
