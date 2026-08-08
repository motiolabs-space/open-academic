<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\KategoriNotifikasi;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Pengingat\TagihanJatuhTempo;
use App\Services\Notifikasi\Contracts\WhatsAppGatewayInterface;
use App\Services\Notifikasi\Notifier;
use App\Services\Notifikasi\Preferensi;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Jendela KRS sengaja jauh, supaya tes tagihan tidak ikut memicu pengingat
    // KRS dan menghitungnya sebagai kelebihan satu.
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'krs_mulai' => now()->subDays(3),
        'krs_selesai' => now()->addDays(60),
    ]);

    $this->prodi = Prodi::factory()->create();

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);
});

/** Tagihan belum lunas dengan jatuh tempo $hari hari dari sekarang. */
function tagihanJatuhTempo(int $hari, ?Mahasiswa $mahasiswa = null): Tagihan
{
    return Tagihan::create([
        'nomor' => 'INV/'.uniqid(),
        'mahasiswa_id' => ($mahasiswa ?? test()->mahasiswa)->id,
        'tahun_akademik_id' => test()->term->id,
        'keterangan' => 'UKT Semester Ganjil',
        'total' => 5_000_000,
        'terbayar' => 0,
        'status' => InvoiceStatus::BelumBayar,
        'jatuh_tempo' => now()->addDays($hari),
    ]);
}

describe('pengingat tagihan', function () {
    it('mengirim tepat pada hari yang dikonfigurasi', function () {
        config(['notifikasi.pengingat.tagihan' => [7]]);
        tagihanJatuhTempo(7);

        $this->artisan('openacademic:kirim-pengingat')->assertSuccessful();

        expect(DB::table('notifications')->count())->toBe(1);
    });

    it('diam pada hari yang tidak dikonfigurasi', function () {
        config(['notifikasi.pengingat.tagihan' => [7]]);
        tagihanJatuhTempo(5);

        $this->artisan('openacademic:kirim-pengingat')->assertSuccessful();

        expect(DB::table('notifications')->count())->toBe(0);
    });

    it('tidak mengirim ulang pengingat yang sama pada hari yang sama', function () {
        // Inti dari tabel notifikasi_kunci. Penjadwal berjalan tiap hari dan
        // melihat tagihan menunggak yang sama setiap malam; mengirimnya tiap
        // malam melatih orang mengabaikan kanalnya.
        config(['notifikasi.pengingat.tagihan' => [7]]);
        tagihanJatuhTempo(7);

        $this->artisan('openacademic:kirim-pengingat');
        $this->artisan('openacademic:kirim-pengingat');
        $this->artisan('openacademic:kirim-pengingat');

        expect(DB::table('notifications')->count())->toBe(1);
    });

    it('mengirim lagi pada ambang berikutnya', function () {
        // Kuncinya memuat offset, jadi H-7 dan H-1 adalah dua pengingat yang
        // berbeda untuk tagihan yang sama.
        config(['notifikasi.pengingat.tagihan' => [7, 1]]);
        $tagihan = tagihanJatuhTempo(7);

        $this->artisan('openacademic:kirim-pengingat');

        $tagihan->update(['jatuh_tempo' => now()->addDay()]);
        $this->artisan('openacademic:kirim-pengingat');

        expect(DB::table('notifications')->count())->toBe(2);
    });

    it('melewati tagihan yang sudah lunas', function () {
        config(['notifikasi.pengingat.tagihan' => [7]]);
        tagihanJatuhTempo(7)->update([
            'terbayar' => 5_000_000,
            'status' => InvoiceStatus::Lunas,
        ]);

        $this->artisan('openacademic:kirim-pengingat');

        expect(DB::table('notifications')->count())->toBe(0);
    });

    it('mengirim juga setelah lewat jatuh tempo', function () {
        config(['notifikasi.pengingat.tagihan' => [-7]]);
        tagihanJatuhTempo(-7);

        $this->artisan('openacademic:kirim-pengingat');

        $data = json_decode((string) DB::table('notifications')->value('data'), true);

        expect(DB::table('notifications')->count())->toBe(1)
            ->and($data['judul'])->toContain('melewati jatuh tempo');
    });
});

describe('pengingat KRS', function () {
    it('hanya menyasar mahasiswa yang belum mengajukan apa pun', function () {
        // Pengingat yang juga menjangkau mereka yang sudah patuh adalah cara
        // sebuah kanal berubah menjadi kebisingan.
        config([
            'notifikasi.pengingat.tagihan' => [],
            'notifikasi.pengingat.krs' => [60],
        ]);

        $sudah = Mahasiswa::factory()->create([
            'prodi_id' => $this->prodi->id,
            'status' => StudentStatus::Aktif,
        ]);

        $sudah->krs()->create([
            'tahun_akademik_id' => $this->term->id,
            'semester_ke' => 3,
            'batas_sks' => 24,
            'status' => 'diajukan',
        ]);

        $this->artisan('openacademic:kirim-pengingat');

        expect(DB::table('notifications')->where('notifiable_id', $this->mahasiswa->id)->count())->toBe(1)
            ->and(DB::table('notifications')->where('notifiable_id', $sudah->id)->count())->toBe(0);
    });
});

describe('mode kering', function () {
    it('tidak mengirim dan tidak mengklaim kunci', function () {
        // Gladi bersih yang menghabiskan kuncinya akan membungkam jalannya yang
        // sungguhan sesudahnya — kegagalan yang tidak akan pernah terlihat.
        config(['notifikasi.pengingat.tagihan' => [7]]);
        tagihanJatuhTempo(7);

        $this->artisan('openacademic:kirim-pengingat', ['--kering' => true]);

        expect(DB::table('notifications')->count())->toBe(0)
            ->and(DB::table('notifikasi_kunci')->count())->toBe(0);

        $this->artisan('openacademic:kirim-pengingat');

        expect(DB::table('notifications')->count())->toBe(1);
    });
});

describe('kunci dedupe', function () {
    it('menolak klaim kedua atas kunci yang sama', function () {
        $notifier = app(Notifier::class);
        $kunci = 'uji:1:h7';

        // Satu tagihan saja: tabelnya unik per (mahasiswa, tahun akademik).
        $tagihan = tagihanJatuhTempo(7);
        $notifikasi = fn () => new TagihanJatuhTempo($tagihan, 7);

        $pertama = $notifier->kirimSekali($this->mahasiswa, $kunci, $notifikasi());
        $kedua = $notifier->kirimSekali($this->mahasiswa, $kunci, $notifikasi());

        expect($pertama)->toBeTrue()
            ->and($kedua)->toBeFalse()
            ->and(DB::table('notifications')->count())->toBe(1);
    });

    it('memisahkan kunci antar orang', function () {
        $notifier = app(Notifier::class);
        $lain = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        $tagihan = tagihanJatuhTempo(7);

        expect($notifier->kirimSekali($this->mahasiswa, 'sama', new TagihanJatuhTempo($tagihan, 7)))->toBeTrue()
            ->and($notifier->kirimSekali($lain, 'sama', new TagihanJatuhTempo($tagihan, 7)))->toBeTrue()
            ->and(DB::table('notifications')->count())->toBe(2);
    });
});

describe('kanal WhatsApp', function () {
    it('mati secara bawaan meski penerimanya punya nomor', function () {
        $this->mahasiswa->update(['telepon' => '081234567890']);

        expect(app(Preferensi::class)
            ->kanalUntuk($this->mahasiswa->fresh(), KategoriNotifikasi::Pengingat))
            ->toBe(['database', 'mail']);
    });

    it('menyala hanya bila driver dan kategorinya sama-sama disetel', function () {
        config([
            'notifikasi.whatsapp.driver' => 'log',
            'notifikasi.whatsapp.kategori' => ['pengingat'],
        ]);

        $this->mahasiswa->update(['telepon' => '081234567890']);

        $kanal = app(Preferensi::class)
            ->kanalUntuk($this->mahasiswa->fresh(), KategoriNotifikasi::Pengingat);

        expect($kanal)->toContain(WhatsAppChannel::class)

            // Kategori lain tetap mati: driver yang terpasang bukan berarti
            // setiap pesan boleh sampai ke ponsel.
            ->and(app(Preferensi::class)
                ->kanalUntuk($this->mahasiswa->fresh(), KategoriNotifikasi::Akademik))
            ->not->toContain(WhatsAppChannel::class);
    });

    it('menolak driver yang tidak dikenal alih-alih diam-diam mencatat ke log', function () {
        config(['notifikasi.whatsapp.driver' => 'penyedia-yang-belum-ditulis']);

        expect(fn () => app(WhatsAppGatewayInterface::class))
            ->toThrow(InvalidArgumentException::class, 'tidak dikenal');
    });
});
