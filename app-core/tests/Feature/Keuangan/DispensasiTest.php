<?php

declare(strict_types=1);

use App\DTOs\Keuangan\AturanDenda;
use App\DTOs\Keuangan\DispensasiData;
use App\Enums\BentukDenda;
use App\Enums\InvoiceStatus;
use App\Enums\JenisItemTagihan;
use App\Enums\PeriodeDenda;
use App\Enums\SemesterType;
use App\Enums\StatusDispensasi;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\DispensasiTagihan;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Notifications\Keuangan\DispensasiDiputus;
use App\Services\Keuangan\DendaService;
use App\Services\Keuangan\DispensasiService;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Payment dispensations. Tagihan::memenuhiSyaratKrs() and DendaService read
 * tagihan.dispensasi_sampai, but nothing in the application wrote it — the
 * "threshold plus time-boxed dispensation" in the roadmap was half a feature.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    config([
        'academic.krs.requires_payment' => true,
        'academic.krs.min_payment_percent' => 50,
    ]);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    Portal::lupakanTerm();

    $this->keuangan = Staff::factory()->create();
    $this->keuangan->assignRole('keuangan');

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => Prodi::factory()->create()->id,
        'status' => StudentStatus::Aktif,
    ]);
    $this->mahasiswa->assignRole('mahasiswa');

    $this->tagihan = Tagihan::create([
        'nomor' => 'INV/DSP/0001',
        'mahasiswa_id' => $this->mahasiswa->id,
        'tahun_akademik_id' => $this->term->id,
        'keterangan' => 'Biaya kuliah',
        'total' => 5_000_000,
        'terbayar' => 0,
        'status' => InvoiceStatus::BelumBayar,
        'jatuh_tempo' => Carbon::today()->subDays(10),
    ]);

    TagihanItem::create([
        'tagihan_id' => $this->tagihan->id,
        'jenis' => JenisItemTagihan::Tagihan,
        'nama' => 'UKT',
        'nominal' => 5_000_000,
    ]);

    $this->layanan = app(DispensasiService::class);
});

function aturanDendaDispensasi(): AturanDenda
{
    return new AturanDenda(
        aktif: true,
        bentuk: BentukDenda::Nominal,
        periode: PeriodeDenda::Hari,
        nominal: 10_000,
        persen: 1,
        maksimum: 0,
        tenggangHari: 0,
    );
}

it('memberi dispensasi dari layar keuangan dan membuka gerbang KRS', function () {
    Notification::fake();
    expect($this->tagihan->memenuhiSyaratKrs())->toBeFalse();

    $sampai = Carbon::today()->addDays(14)->toDateString();

    $this->actingAs($this->keuangan, 'staff')
        ->post('/admin/keuangan/dispensasi', [
            'tagihan' => 'INV/DSP/0001',
            'sampai' => $sampai,
            'alasan' => 'Menunggu pencairan beasiswa daerah',
        ])
        ->assertRedirect('/admin/keuangan/dispensasi')
        ->assertSessionHas('sukses');

    $tagihan = $this->tagihan->fresh();
    expect($tagihan->dispensasi_sampai->toDateString())->toBe($sampai)
        ->and($tagihan->dispensasiAktif())->toBeTrue()
        ->and($tagihan->memenuhiSyaratKrs())->toBeTrue();

    expect(DispensasiTagihan::sole())
        ->status->toBe(StatusDispensasi::Disetujui)
        ->diputus_by_staff_id->toBe($this->keuangan->id)
        ->alasan->toBe('Menunggu pencairan beasiswa daerah');

    expect(LogAktivitas::where('subject_id', $tagihan->id)->where('event', 'dispensation_granted')->exists())->toBeTrue();
    Notification::assertSentTo($this->mahasiswa, DispensasiDiputus::class);

    // The student sees it on the invoice screen.
    $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/tagihan')
        ->assertOk()
        ->assertSee('data-dispensasi="berlaku"', false)
        ->assertSee('Menunggu pencairan beasiswa daerah');
});

it('mewajibkan tanggal di masa depan dan alasan', function () {
    $this->actingAs($this->keuangan, 'staff');

    $this->post('/admin/keuangan/dispensasi', [
        'tagihan' => 'INV/DSP/0001', 'sampai' => Carbon::today()->toDateString(), 'alasan' => 'Alasan cukup panjang',
    ])->assertSessionHasErrors('sampai');

    $this->post('/admin/keuangan/dispensasi', [
        'tagihan' => 'INV/DSP/0001', 'sampai' => Carbon::today()->addWeek()->toDateString(), 'alasan' => '',
    ])->assertSessionHasErrors('alasan');

    expect(fn () => $this->layanan->beri(
        $this->tagihan,
        new DispensasiData(Carbon::yesterday(), 'Alasan cukup panjang'),
        $this->keuangan,
    ))->toThrow(AturanAkademikException::class);

    expect(DispensasiTagihan::count())->toBe(0)
        ->and($this->tagihan->fresh()->dispensasi_sampai)->toBeNull();
});

it('menolak dispensasi pada tagihan lunas dan staf tanpa izin kelola keuangan', function () {
    $baak = Staff::factory()->create();
    $baak->assignRole('baak');

    $this->actingAs($baak, 'staff')->post('/admin/keuangan/dispensasi', [
        'tagihan' => 'INV/DSP/0001', 'sampai' => Carbon::today()->addWeek()->toDateString(), 'alasan' => 'Alasan cukup panjang',
    ])->assertForbidden();

    $this->tagihan->update(['terbayar' => 5_000_000, 'status' => InvoiceStatus::Lunas]);

    $this->actingAs($this->keuangan, 'staff')->post('/admin/keuangan/dispensasi', [
        'tagihan' => 'INV/DSP/0001', 'sampai' => Carbon::today()->addWeek()->toDateString(), 'alasan' => 'Alasan cukup panjang',
    ])->assertSessionHas('galat');

    expect(DispensasiTagihan::count())->toBe(0);
});

it('menunda denda selama dispensasi dan memulai hitungannya sesudah batasnya', function () {
    $denda = app(DendaService::class);
    $this->layanan->beri($this->tagihan, new DispensasiData(Carbon::today()->addDays(5), 'Menunggu kiriman orang tua'), $this->keuangan);

    $tagihan = $this->tagihan->fresh();
    expect($denda->hitung($tagihan, aturanDendaDispensasi()))->toBe(0)
        ->and($denda->kandidat(aturanDendaDispensasi())->count())->toBe(0);

    // Two days after it lapses: two days of fee, not seventeen.
    Carbon::setTestNow(Carbon::today()->addDays(7));
    expect($tagihan->fresh()->memenuhiSyaratKrs())->toBeFalse()
        ->and($denda->hitung($tagihan->fresh(), aturanDendaDispensasi()))->toBe(20_000);
    Carbon::setTestNow();
});

it('mencabut dispensasi: gerbang tertutup hari ini dan denda tidak dihitung mundur', function () {
    Notification::fake();
    $dispensasi = $this->layanan->beri($this->tagihan, new DispensasiData(Carbon::today()->addDays(20), 'Menunggu kiriman orang tua'), $this->keuangan);

    $this->actingAs($this->keuangan, 'staff')
        ->post("/admin/keuangan/dispensasi/{$dispensasi->uuid}/cabut", ['alasan' => 'Diberikan karena salah input'])
        ->assertSessionHas('sukses');

    $tagihan = $this->tagihan->fresh();
    expect($dispensasi->fresh())
        ->status->toBe(StatusDispensasi::Dicabut)
        ->dicabut_by_staff_id->toBe($this->keuangan->id)
        ->alasan_cabut->toBe('Diberikan karena salah input');

    expect($tagihan->dispensasiAktif())->toBeFalse()
        ->and($tagihan->memenuhiSyaratKrs())->toBeFalse()
        ->and($tagihan->dispensasi_sampai->toDateString())->toBe(Carbon::yesterday()->toDateString());

    // The fee clock starts at the revocation: one day today, not the ten days
    // since the original due date that the dispensation covered.
    expect(app(DendaService::class)->hitung($tagihan, aturanDendaDispensasi()))->toBe(10_000);

    expect(LogAktivitas::where('subject_id', $tagihan->id)->where('event', 'dispensation_revoked')->exists())->toBeTrue();
    Notification::assertSentTimes(DispensasiDiputus::class, 2);

    // Only a dispensation in force can be revoked.
    $this->post("/admin/keuangan/dispensasi/{$dispensasi->uuid}/cabut", ['alasan' => 'Sekali lagi'])->assertSessionHas('galat');
});

it('menggantikan dispensasi yang masih berlaku saat diperpanjang', function () {
    $lama = $this->layanan->beri($this->tagihan, new DispensasiData(Carbon::today()->addDays(5), 'Alasan pertama'), $this->keuangan);
    $baru = $this->layanan->beri($this->tagihan, new DispensasiData(Carbon::today()->addDays(30), 'Perpanjangan'), $this->keuangan);

    expect($lama->fresh()->status)->toBe(StatusDispensasi::Dicabut)
        ->and($baru->fresh()->masihBerlaku())->toBeTrue()
        ->and($this->tagihan->fresh()->dispensasi_sampai->toDateString())->toBe(Carbon::today()->addDays(30)->toDateString())
        ->and(DispensasiTagihan::query()->berlaku()->count())->toBe(1);
});

it('menerima permohonan mahasiswa dan staf memutuskannya', function () {
    Notification::fake();
    $sampai = Carbon::today()->addDays(10)->toDateString();

    $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->post("/mahasiswa/tagihan/{$this->tagihan->uuid}/dispensasi", ['sampai' => $sampai, 'alasan' => 'Orang tua baru gajian tanggal 10'])
        ->assertSessionHas('sukses');

    $permohonan = DispensasiTagihan::sole();
    expect($permohonan->status)->toBe(StatusDispensasi::Diajukan)
        ->and($permohonan->diajukan_by_mahasiswa_id)->toBe($this->mahasiswa->id)
        ->and($this->tagihan->fresh()->dispensasi_sampai)->toBeNull();

    // A second request while the first waits is refused.
    $this->post("/mahasiswa/tagihan/{$this->tagihan->uuid}/dispensasi", ['sampai' => $sampai, 'alasan' => 'Permohonan kedua'])
        ->assertSessionHas('galat');

    $this->get('/mahasiswa/tagihan')->assertSee('data-dispensasi="menunggu"', false);

    $this->actingAs($this->keuangan, 'staff')
        ->get('/admin/keuangan/dispensasi')
        ->assertOk()
        ->assertSee('Orang tua baru gajian tanggal 10');

    // Refusing needs a note.
    $this->post("/admin/keuangan/dispensasi/{$permohonan->uuid}/putuskan", ['setujui' => '0'])->assertSessionHasErrors('catatan');

    $this->post("/admin/keuangan/dispensasi/{$permohonan->uuid}/putuskan", [
        'setujui' => '1',
        'sampai' => Carbon::today()->addDays(7)->toDateString(),
    ])->assertSessionHas('sukses');

    expect($permohonan->fresh())
        ->status->toBe(StatusDispensasi::Disetujui)
        ->diputus_by_staff_id->toBe($this->keuangan->id);
    expect($this->tagihan->fresh()->dispensasi_sampai->toDateString())->toBe(Carbon::today()->addDays(7)->toDateString())
        ->and($this->tagihan->fresh()->memenuhiSyaratKrs())->toBeTrue();

    Notification::assertSentTo($this->mahasiswa, DispensasiDiputus::class);
});

it('menolak permohonan dengan catatan yang terlihat mahasiswa', function () {
    $permohonan = $this->layanan->ajukan($this->tagihan, $this->mahasiswa, new DispensasiData(Carbon::today()->addDays(10), 'Alasan mahasiswa'));

    $this->actingAs($this->keuangan, 'staff')
        ->post("/admin/keuangan/dispensasi/{$permohonan->uuid}/putuskan", ['setujui' => '0', 'catatan' => 'Silakan cicil minimal setengah'])
        ->assertSessionHas('sukses');

    expect($permohonan->fresh()->status)->toBe(StatusDispensasi::Ditolak)
        ->and($this->tagihan->fresh()->memenuhiSyaratKrs())->toBeFalse();

    $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/tagihan')
        ->assertSee('data-dispensasi="ditolak"', false)
        ->assertSee('Silakan cicil minimal setengah');
});

it('tidak mengizinkan mahasiswa memohon dispensasi atas tagihan orang lain', function () {
    $lain = Mahasiswa::factory()->create();
    $lain->assignRole('mahasiswa');

    $this->actingAs($lain, 'mahasiswa')
        ->post("/mahasiswa/tagihan/{$this->tagihan->uuid}/dispensasi", [
            'sampai' => Carbon::today()->addDays(10)->toDateString(), 'alasan' => 'Bukan tagihan saya',
        ])
        ->assertForbidden();

    expect(DispensasiTagihan::count())->toBe(0);
});
