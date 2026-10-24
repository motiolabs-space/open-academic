<?php

declare(strict_types=1);

use App\DTOs\Helpdesk\BalasanData;
use App\DTOs\Helpdesk\DisposisiData;
use App\DTOs\Helpdesk\TiketBaruData;
use App\Enums\PrioritasTiket;
use App\Enums\StatusTiket;
use App\Exceptions\AturanAkademikException;
use App\Models\Helpdesk\HelpdeskKategori;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Notifications\Helpdesk\TiketDibalas;
use App\Notifications\Helpdesk\TiketDidisposisi;
use App\Notifications\Helpdesk\TiketSelesai;
use App\Services\Helpdesk\HelpdeskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');

    $this->baakUnit = UnitKerja::create(['kode' => 'BAAK', 'nama' => 'Biro Administrasi Akademik']);
    $this->tikUnit = UnitKerja::create(['kode' => 'TIK', 'nama' => 'UPT TIK']);

    $this->baak = Staff::factory()->create(['unit_kerja_id' => $this->baakUnit->id]);
    $this->baak->assignRole('baak');

    $this->tik = Staff::factory()->create(['unit_kerja_id' => $this->tikUnit->id]);
    $this->tik->assignRole('operator-pddikti');

    $this->kategori = HelpdeskKategori::create([
        'nama' => 'Akademik & KRS',
        'unit_kerja_id' => $this->baakUnit->id,
        'sla_jam' => 48,
    ]);

    $this->mahasiswa = Mahasiswa::factory()->create();
    $this->mahasiswa->assignRole('mahasiswa');

    $this->dosen = Dosen::factory()->create();
    $this->dosen->assignRole('dosen');

    $this->helpdesk = app(HelpdeskService::class);
});

function tiketBaru(array $override = []): TiketBaruData
{
    return new TiketBaruData(
        kategoriId: $override['kategoriId'] ?? test()->kategori->id,
        judul: $override['judul'] ?? 'KRS tidak bisa diajukan',
        isi: $override['isi'] ?? 'Tombol ajukan tidak muncul padahal SKS sudah di bawah batas.',
        prioritas: $override['prioritas'] ?? PrioritasTiket::Normal,
        lampiran: $override['lampiran'] ?? null,
    );
}

describe('membuat tiket', function () {
    it('memberi nomor berpola, unit bawaan kategori, dan tenggat SLA dari jam kategori', function () {
        $this->travelTo(now()->setTime(9, 0));

        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        expect($tiket->nomor)->toMatch('/^HD-\d{4}-\d{5}$/')
            ->and($tiket->status)->toBe(StatusTiket::Baru)
            ->and($tiket->unit_kerja_id)->toBe($this->baakUnit->id)
            ->and($tiket->petugas_staff_id)->toBeNull()
            ->and($tiket->tenggat_sla_at->equalTo(now()->addHours(48)))->toBeTrue()
            ->and($tiket->milik($this->mahasiswa))->toBeTrue();
    });

    it('menomori berurutan dalam satu tahun', function () {
        $a = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $b = $this->helpdesk->buat($this->dosen, tiketBaru());

        expect((int) substr($b->nomor, -5))->toBe((int) substr($a->nomor, -5) + 1);
    });

    it('memakai SLA bawaan konfigurasi bila kategori tidak menetapkan, dan tanpa tenggat bila nol', function () {
        config(['helpdesk.sla_jam_bawaan' => 72]);

        $tanpa = HelpdeskKategori::create(['nama' => 'Lainnya']);
        $nol = HelpdeskKategori::create(['nama' => 'Saran', 'sla_jam' => 0]);

        $t1 = $this->helpdesk->buat($this->mahasiswa, tiketBaru(['kategoriId' => $tanpa->id]));
        $t2 = $this->helpdesk->buat($this->mahasiswa, tiketBaru(['kategoriId' => $nol->id]));

        expect(now()->diffInHours($t1->tenggat_sla_at, true))->toBeGreaterThanOrEqual(71)
            ->and($t1->unit_kerja_id)->toBeNull()
            ->and($t2->tenggat_sla_at)->toBeNull();
    });

    it('menolak kategori nonaktif', function () {
        $this->kategori->update(['is_active' => false]);

        expect(fn () => $this->helpdesk->buat($this->mahasiswa, tiketBaru()))
            ->toThrow(AturanAkademikException::class, 'tidak aktif');
    });

    it('menyimpan lampiran lewat modul berkas, bukan dengan nama aslinya', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru([
            'lampiran' => UploadedFile::fake()->create('Bukti KRS.pdf', 20, 'application/pdf'),
        ]));

        expect($tiket->lampiran_nama)->toBe('Bukti KRS.pdf')
            ->and($tiket->lampiran_path)->toStartWith('helpdesk/tiket/')
            ->and($tiket->lampiran_path)->not->toContain('Bukti')
            ->and(Storage::disk('local')->exists($tiket->lampiran_path))->toBeTrue();
    });
});

describe('disposisi', function () {
    it('meneruskan ke unit dan petugas, mencatat catatan internal, dan memberi tahu pemohon', function () {
        Notification::fake();

        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        $this->helpdesk->disposisi($tiket, $this->baak, new DisposisiData(
            unitKerjaId: $this->tikUnit->id,
            petugasStaffId: $this->tik->id,
            prioritas: PrioritasTiket::Tinggi,
            catatan: 'Sepertinya masalah akun, bukan KRS.',
        ));

        $tiket->refresh();

        expect($tiket->status)->toBe(StatusTiket::Didisposisi)
            ->and($tiket->unit_kerja_id)->toBe($this->tikUnit->id)
            ->and($tiket->petugas_staff_id)->toBe($this->tik->id)
            ->and($tiket->prioritas)->toBe(PrioritasTiket::Tinggi)
            ->and($tiket->didisposisi_at)->not->toBeNull()
            ->and($tiket->balasan()->count())->toBe(1)
            ->and($tiket->balasan()->first()->is_internal)->toBeTrue()
            ->and($tiket->balasan()->first()->isi)->toContain('Sepertinya masalah akun');

        Notification::assertSentTo($this->mahasiswa, TiketDidisposisi::class);
    });

    it('mengikuti unit petugas bila unit tidak disebut', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        $this->helpdesk->disposisi($tiket, $this->baak, new DisposisiData(petugasStaffId: $this->tik->id));

        expect($tiket->refresh()->unit_kerja_id)->toBe($this->tikUnit->id);
    });

    it('menolak petugas yang bukan staf unit tujuan', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        expect(fn () => $this->helpdesk->disposisi($tiket, $this->baak, new DisposisiData(
            unitKerjaId: $this->tikUnit->id,
            petugasStaffId: $this->baak->id,
        )))->toThrow(AturanAkademikException::class, 'bukan staf');
    });

    it('menolak disposisi yang tidak menamai siapa pun', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        expect(fn () => $this->helpdesk->disposisi($tiket, $this->baak, new DisposisiData))
            ->toThrow(AturanAkademikException::class, 'wajib menyebut');
    });
});

describe('balasan', function () {
    it('balasan publik staf memindahkan ke menunggu pemohon, mengambil tiket, dan memberi tahu', function () {
        Notification::fake();

        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        $this->helpdesk->balas($tiket, $this->baak, new BalasanData('Sudah kami periksa, coba ajukan ulang.'));

        $tiket->refresh();

        expect($tiket->status)->toBe(StatusTiket::MenungguPemohon)
            ->and($tiket->petugas_staff_id)->toBe($this->baak->id)
            ->and($tiket->ditanggapi_at)->not->toBeNull();

        Notification::assertSentTo($this->mahasiswa, TiketDibalas::class);
    });

    it('catatan internal tidak mengubah status dan tidak memberi tahu siapa pun', function () {
        Notification::fake();

        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        $balasan = $this->helpdesk->balas($tiket, $this->baak, new BalasanData('Cek dulu ke prodi.', internal: true));

        expect($balasan->is_internal)->toBeTrue()
            ->and($tiket->refresh()->status)->toBe(StatusTiket::Baru);

        Notification::assertNothingSent();
    });

    it('menolak catatan internal dari pemohon, apa pun flagnya', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        expect(fn () => $this->helpdesk->balas($tiket, $this->mahasiswa, new BalasanData('Rahasia.', internal: true)))
            ->toThrow(AturanAkademikException::class, 'hanya dapat ditulis oleh staf');
    });

    it('balasan pemohon mengembalikan bola ke kampus', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $this->helpdesk->balas($tiket, $this->baak, new BalasanData('Mohon kirim tangkapan layarnya.'));

        $this->helpdesk->balas($tiket->refresh(), $this->mahasiswa, new BalasanData('Terlampir.'));

        expect($tiket->refresh()->status)->toBe(StatusTiket::Diproses);
    });

    it('balasan pemohon pada tiket baru tidak berpura-pura sedang diproses', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        $this->helpdesk->balas($tiket, $this->mahasiswa, new BalasanData('Tambahan: terjadi sejak kemarin.'));

        expect($tiket->refresh()->status)->toBe(StatusTiket::Baru);
    });

    it('balasan pemohon membuka kembali tiket yang sudah selesai', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $this->helpdesk->ubahStatus($tiket, $this->baak, StatusTiket::Diproses);
        $this->helpdesk->selesaikan($tiket->refresh(), $this->baak, 'Sudah dibuka kembali.');

        $this->helpdesk->balas($tiket->refresh(), $this->mahasiswa, new BalasanData('Masih belum bisa.'));

        expect($tiket->refresh()->status)->toBe(StatusTiket::Diproses)
            ->and($tiket->selesai_at)->toBeNull();
    });

    it('menolak balasan siapa pun pada tiket yang ditutup', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $this->helpdesk->tutup($tiket, $this->baak, 'Duplikat.');

        expect(fn () => $this->helpdesk->balas($tiket->refresh(), $this->mahasiswa, new BalasanData('Halo?')))
            ->toThrow(AturanAkademikException::class, 'sudah ditutup');
    });

    it('menolak orang lain membalas tiket yang bukan miliknya', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        expect(fn () => $this->helpdesk->balas($tiket, $this->dosen, new BalasanData('Ikut nimbrung.')))
            ->toThrow(AturanAkademikException::class, 'Hanya pemohon');
    });
});

describe('transisi status', function () {
    it('menolak transisi yang tidak ada di tabel', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        // Baru → Selesai melompati pemrosesan; tabelnya tidak mengizinkan.
        expect(fn () => $this->helpdesk->selesaikan($tiket, $this->baak))
            ->toThrow(AturanAkademikException::class, 'tidak dapat diubah menjadi Selesai');
    });

    it('tiket yang ditutup tidak dapat ke mana-mana', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $this->helpdesk->tutup($tiket, $this->baak, 'Spam.');

        foreach (StatusTiket::cases() as $ke) {
            expect(StatusTiket::Ditutup->dapatKe($ke))->toBeFalse();
        }

        expect(fn () => $this->helpdesk->ubahStatus($tiket->refresh(), $this->baak, StatusTiket::Diproses))
            ->toThrow(AturanAkademikException::class);
    });

    it('menyelesaikan dengan catatan yang terbaca pemohon, dan memberi tahu', function () {
        Notification::fake();

        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $this->helpdesk->ubahStatus($tiket, $this->baak, StatusTiket::Diproses);
        $this->helpdesk->selesaikan($tiket->refresh(), $this->baak, 'Kuota kelas sudah ditambah.');

        $tiket->refresh();

        expect($tiket->status)->toBe(StatusTiket::Selesai)
            ->and($tiket->selesai_at)->not->toBeNull()
            ->and($tiket->balasanPublik()->count())->toBe(1);

        Notification::assertSentTo($this->mahasiswa, TiketSelesai::class);
    });

    it('menutup wajib beralasan', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        expect(fn () => $this->helpdesk->ubahStatus($tiket, $this->baak, StatusTiket::Ditutup))
            ->toThrow(AturanAkademikException::class, 'wajib disertai alasan');
    });

    it('mengambil tiket (diproses) menetapkan petugas bila belum ada', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        $this->helpdesk->ubahStatus($tiket, $this->baak, StatusTiket::Diproses);

        expect($tiket->refresh()->petugas_staff_id)->toBe($this->baak->id);
    });

    it('mencatat jejak audit pada perubahan status', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $this->helpdesk->ubahStatus($tiket, $this->baak, StatusTiket::Diproses);

        $log = $tiket->logAktivitas()->where('event', 'updated')->first();

        expect($log)->not->toBeNull()
            ->and($log->changes['status']['new'] ?? null)->toBe(StatusTiket::Diproses->value);
    });
});

describe('SLA', function () {
    it('menandai tiket lewat tenggat yang masih menunggu kampus, sekali saja, tanpa menutup', function () {
        $terlambat = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $menungguPemohon = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $this->helpdesk->balas($menungguPemohon, $this->baak, new BalasanData('Mohon konfirmasi.'));
        $tepatWaktu = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        HelpdeskTiket::whereKey([$terlambat->id, $menungguPemohon->id])
            ->update(['tenggat_sla_at' => now()->subHour()]);

        expect($this->helpdesk->tandaiLewatSla())->toBe(1)
            ->and($terlambat->refresh()->lewat_sla_at)->not->toBeNull()
            ->and($terlambat->status)->toBe(StatusTiket::Baru)
            ->and($menungguPemohon->refresh()->lewat_sla_at)->toBeNull()
            ->and($tepatWaktu->refresh()->lewat_sla_at)->toBeNull();

        // Running again stamps nothing twice.
        expect($this->helpdesk->tandaiLewatSla())->toBe(0);
    });

    it('perintah artisan menandai, dan mode kering tidak menyentuh apa pun', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $tiket->forceFill(['tenggat_sla_at' => now()->subHour()])->saveQuietly();

        $this->artisan('openacademic:helpdesk-sla', ['--kering' => true])
            ->expectsOutputToContain('1 tiket')
            ->assertSuccessful();

        expect($tiket->refresh()->lewat_sla_at)->toBeNull();

        $this->artisan('openacademic:helpdesk-sla')
            ->expectsOutputToContain('1 tiket ditandai')
            ->assertSuccessful();

        expect($tiket->refresh()->lewat_sla_at)->not->toBeNull();
    });

    it('lewatSla() membaca jam juga sebelum perintah berjalan', function () {
        $tiket = $this->helpdesk->buat($this->mahasiswa, tiketBaru());

        expect($tiket->lewatSla())->toBeFalse();

        $tiket->forceFill(['tenggat_sla_at' => now()->subMinute()])->saveQuietly();

        expect($tiket->refresh()->lewatSla())->toBeTrue();
    });
});

describe('antrean per unit', function () {
    it('staf dengan manage melihat semua; staf unit hanya unitnya dan yang ditugaskan padanya', function () {
        $diBaak = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $diTik = $this->helpdesk->buat($this->dosen, tiketBaru());
        $this->helpdesk->disposisi($diTik, $this->baak, new DisposisiData(unitKerjaId: $this->tikUnit->id));

        $ditugaskan = $this->helpdesk->buat($this->mahasiswa, tiketBaru());
        $this->helpdesk->disposisi($ditugaskan, $this->baak, new DisposisiData(
            unitKerjaId: $this->baakUnit->id,
            petugasStaffId: $this->baak->id,
        ));

        // Named officer from another unit: visible to them by name.
        $ditugaskan->forceFill(['petugas_staff_id' => $this->tik->id])->saveQuietly();

        expect($this->helpdesk->antreanUntuk($this->baak)->count())->toBe(3)
            ->and($this->helpdesk->antreanUntuk($this->tik)->pluck('id')->sort()->values()->all())
            ->toBe(collect([$diTik->id, $ditugaskan->id])->sort()->values()->all());
    });
});
