<?php

declare(strict_types=1);

use App\DTOs\Kemahasiswaan\AjuanCutiData;
use App\Enums\JenisCuti;
use App\Enums\KrsStatus;
use App\Enums\LeaveStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Krs;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Bridge\BridgeWebhookDelivery;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Notifications\Kemahasiswaan\CutiDiputus;
use App\Services\Kemahasiswaan\CutiService;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Yaml\Yaml;

/**
 * Leave applied for from the student portal (ADR-040 §1). Until now only
 * staff could file one, on a student's behalf, by pasting the student's UUID.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->antara = TahunAkademik::factory()->term(2026, SemesterType::Antara)->create();
    $this->berikut = TahunAkademik::factory()->term(2026, SemesterType::Genap)->create();
    $this->jauh = TahunAkademik::factory()->term(2027, SemesterType::Ganjil)->create();
    Portal::lupakanTerm();

    $this->mahasiswa = Mahasiswa::factory()->create(['status' => StudentStatus::Aktif]);
    $this->mahasiswa->assignRole('mahasiswa');

    $this->lain = Mahasiswa::factory()->create(['status' => StudentStatus::Aktif]);
    $this->lain->assignRole('mahasiswa');

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->layanan = app(CutiService::class);
});

function ajuanCutiMandiri(int $termId, ?UploadedFile $dokumen = null): AjuanCutiData
{
    return new AjuanCutiData($termId, JenisCuti::Sakit, 'Rawat inap pascaoperasi selama satu semester.', $dokumen);
}

describe('semester yang dapat diajukan', function () {
    it('menawarkan semester berjalan dan semester reguler berikutnya saja', function () {
        expect($this->layanan->semesterAjuanMandiri()->pluck('id')->all())
            ->toBe([$this->term->id, $this->berikut->id]);
    });

    it('menolak semester antara dan semester yang lebih jauh', function () {
        foreach ([$this->antara, $this->jauh] as $term) {
            expect(fn () => $this->layanan->ajukanMandiri($this->mahasiswa, ajuanCutiMandiri($term->id)))
                ->toThrow(AturanAkademikException::class, 'semester reguler berikutnya');
        }

        expect(CutiMahasiswa::count())->toBe(0);
    });

    it('kosong bila belum ada semester aktif', function () {
        $this->term->update(['is_active' => false]);
        Portal::lupakanTerm();

        expect($this->layanan->semesterAjuanMandiri())->toBeEmpty();
    });
});

describe('ajuan mandiri', function () {
    it('mencatat ajuan beserta dokumen di disk privat', function () {
        $cuti = $this->layanan->ajukanMandiri(
            $this->mahasiswa,
            ajuanCutiMandiri($this->berikut->id, UploadedFile::fake()->create('surat-dokter.pdf', 20, 'application/pdf')),
        );

        expect($cuti->status)->toBe(LeaveStatus::Diajukan)
            ->and($cuti->tahun_akademik_id)->toBe($this->berikut->id)
            ->and($cuti->jenis)->toBe('sakit')
            ->and($cuti->dokumen_path)->toStartWith('cuti/'.$this->mahasiswa->uuid.'/')
            ->and($cuti->dokumen_path)->not->toContain('surat-dokter');

        Storage::disk('local')->assertExists($cuti->dokumen_path);

        // Mengajukan tidak mengubah status — hanya keputusan BAAK yang mengubahnya.
        expect($this->mahasiswa->refresh()->status)->toBe(StudentStatus::Aktif);
    });

    it('tetap tunduk pada aturan KRS aktif dan tidak menyimpan berkas yang ditolak', function () {
        Krs::factory()->create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'status' => KrsStatus::Diajukan,
        ]);

        expect(fn () => $this->layanan->ajukanMandiri(
            $this->mahasiswa,
            ajuanCutiMandiri($this->term->id, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')),
        ))->toThrow(AturanAkademikException::class, 'KRS aktif');

        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('menolak ajuan dari mahasiswa yang tidak aktif dan ajuan ganda', function () {
        $this->layanan->ajukanMandiri($this->mahasiswa, ajuanCutiMandiri($this->berikut->id));

        expect(fn () => $this->layanan->ajukanMandiri($this->mahasiswa, ajuanCutiMandiri($this->berikut->id)))
            ->toThrow(AturanAkademikException::class, 'sudah pernah dibuat');

        $this->lain->update(['status' => StudentStatus::NonAktif]);

        expect(fn () => $this->layanan->ajukanMandiri($this->lain, ajuanCutiMandiri($this->berikut->id)))
            ->toThrow(AturanAkademikException::class, 'berstatus aktif');
    });
});

describe('layar portal', function () {
    it('menampilkan formulir dan riwayat milik sendiri saja', function () {
        $milikLain = $this->layanan->ajukan($this->lain, $this->term, 'akademik', 'Alasan mahasiswa lain yang panjang.');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get('/mahasiswa/cuti')
            ->assertOk()
            ->assertSee($this->berikut->nama)
            ->assertDontSee($milikLain->uuid);
    });

    it('mengirim ajuan lewat formulir', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post('/mahasiswa/cuti', [
                'tahun_akademik_id' => $this->berikut->id,
                'jenis' => 'akademik',
                'alasan' => 'Bekerja penuh waktu untuk membantu keluarga.',
                'dokumen' => UploadedFile::fake()->create('surat.pdf', 10, 'application/pdf'),
                // Diabaikan: pemohon selalu yang sedang masuk.
                'mahasiswa_id' => $this->lain->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect(CutiMahasiswa::where('mahasiswa_id', $this->mahasiswa->id)->count())->toBe(1)
            ->and(CutiMahasiswa::where('mahasiswa_id', $this->lain->id)->count())->toBe(0);
    });

    it('menolak berkas yang bukan PDF atau gambar', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post('/mahasiswa/cuti', [
                'tahun_akademik_id' => $this->berikut->id,
                'jenis' => 'akademik',
                'alasan' => 'Bekerja penuh waktu untuk membantu keluarga.',
                'dokumen' => UploadedFile::fake()->create('skrip.php', 1, 'application/x-php'),
            ])
            ->assertSessionHasErrors('dokumen');

        expect(CutiMahasiswa::count())->toBe(0);
    });

    it('menolak mahasiswa tanpa izin cuti.ajukan', function () {
        $this->mahasiswa->roles()->first()->revokePermissionTo('cuti.ajukan');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->mahasiswa->refresh(), 'mahasiswa')
            ->post('/mahasiswa/cuti', [
                'tahun_akademik_id' => $this->berikut->id,
                'jenis' => 'akademik',
                'alasan' => 'Bekerja penuh waktu untuk membantu keluarga.',
            ])
            ->assertForbidden();
    });

    it('membuka dokumen sendiri tetapi tidak milik mahasiswa lain', function () {
        $cuti = $this->layanan->ajukanMandiri(
            $this->lain,
            ajuanCutiMandiri($this->berikut->id, UploadedFile::fake()->create('rahasia.pdf', 10, 'application/pdf')),
        );

        $this->actingAs($this->mahasiswa, 'mahasiswa')->get(route('berkas.cuti', $cuti))->assertForbidden();
        $this->actingAs($this->lain, 'mahasiswa')->get(route('berkas.cuti', $cuti))->assertOk();
        $this->actingAs($this->baak, 'staff')->get(route('berkas.cuti', $cuti))->assertOk();
    });
});

describe('keputusan staf', function () {
    it('menyetujui lewat layar staf, memberi tahu mahasiswa, dan menerbitkan student.status_changed', function () {
        Notification::fake();
        Queue::fake();
        config(['bridge.enabled' => true]);

        BridgeConsumer::create([
            'nama' => 'Open Campus',
            'slug' => 'open-campus',
            'base_url' => 'https://campus.test',
            'scopes' => ['students.read'],
            'webhook_url' => 'https://campus.test/webhooks/open-academic',
            'webhook_secret' => Str::random(48),
            'webhook_events' => ['student.status_changed'],
            'is_active' => true,
        ]);

        $cuti = $this->layanan->ajukanMandiri($this->mahasiswa, ajuanCutiMandiri($this->term->id));

        // Ajuan belum mengumumkan apa pun.
        expect(BridgeWebhookDelivery::count())->toBe(0);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.cuti.setujui', $cuti), ['catatan' => 'Lengkap.'])
            ->assertRedirect();

        expect($cuti->refresh()->status)->toBe(LeaveStatus::Disetujui)
            ->and($this->mahasiswa->refresh()->status)->toBe(StudentStatus::Cuti);

        Notification::assertSentTo($this->mahasiswa, CutiDiputus::class,
            fn (CutiDiputus $n): bool => $n->tautan($this->mahasiswa) === route('mahasiswa.cuti'));

        $kiriman = BridgeWebhookDelivery::where('event', 'student.status_changed')->sole()->payload['data'];
        $skema = Yaml::parseFile(base_path('../docs/openapi/bridge.yaml'))['components']['x-webhooks']['payloads']['student.status_changed'];

        expect($kiriman)->toBe([
            'mahasiswa_uuid' => $this->mahasiswa->uuid,
            'nim' => $this->mahasiswa->nim,
            'status_lama' => StudentStatus::Aktif->value,
            'status_baru' => StudentStatus::Cuti->value,
        ])->and(array_keys($kiriman))->toEqualCanonicalizing(array_keys($skema['properties']));

        // Aktif kembali dari layar staf — tombol ini dulu melempar galat
        // karena staf tidak diteruskan ke service.
        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.cuti.aktifkan', $cuti))
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($this->mahasiswa->refresh()->status)->toBe(StudentStatus::Aktif)
            ->and(BridgeWebhookDelivery::where('event', 'student.status_changed')->latest('id')->first()->payload['data']['status_baru'])
            ->toBe(StudentStatus::Aktif->value);
    });

    it('menolak dengan alasan yang terbaca mahasiswa di portal', function () {
        Notification::fake();

        $cuti = $this->layanan->ajukanMandiri($this->mahasiswa, ajuanCutiMandiri($this->berikut->id));

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.cuti.tolak', $cuti), ['catatan' => 'Surat dokter belum dilegalisasi.'])
            ->assertRedirect();

        Notification::assertSentTo($this->mahasiswa, CutiDiputus::class);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get('/mahasiswa/cuti')
            ->assertOk()
            ->assertSee('Surat dokter belum dilegalisasi.');
    });

    it('memberi tanda pada layar staf bila semester ajuan belum berjalan', function () {
        $this->layanan->ajukanMandiri($this->mahasiswa, ajuanCutiMandiri($this->berikut->id));

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.cuti'))
            ->assertOk()
            ->assertSee('data-cuti-belum-berjalan', false);
    });

    it('tidak membolehkan mahasiswa memutus ajuannya sendiri', function () {
        $cuti = $this->layanan->ajukanMandiri($this->mahasiswa, ajuanCutiMandiri($this->berikut->id));

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('admin.cuti.setujui', $cuti))
            ->assertRedirect();

        expect($cuti->refresh()->status)->toBe(LeaveStatus::Diajukan);
    });
});
