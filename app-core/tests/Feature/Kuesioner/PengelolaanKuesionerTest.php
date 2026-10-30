<?php

declare(strict_types=1);

use App\DTOs\Kuesioner\KuesionerData;
use App\DTOs\Kuesioner\PertanyaanData;
use App\Enums\SasaranKuesioner;
use App\Enums\SemesterType;
use App\Enums\StatusKuesioner;
use App\Enums\TipePertanyaan;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kuesioner\Kuesioner;
use App\Models\Kuesioner\KuesionerJawabanAnonim;
use App\Models\Kuesioner\KuesionerPartisipasi;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Kuesioner\KuesionerService;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The questionnaire screens: writing and publishing for staff, filling in from
 * all three portals, and results that keep an anonymous form anonymous.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    Portal::lupakanTerm();

    $this->prodi = Prodi::factory()->create();
    $this->prodiLain = Prodi::factory()->create();

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->layanan = app(KuesionerService::class);
});

function dataKuesioner(array $ubah = []): KuesionerData
{
    return new KuesionerData(...[
        'kode' => 'SURVEI-'.uniqid(),
        'nama' => 'Survei Kepuasan Layanan',
        'deskripsi' => 'Bantu kami memperbaiki layanan.',
        'sasaran' => SasaranKuesioner::Mahasiswa,
        'anonim' => true,
        'minResponden' => 2,
        ...$ubah,
    ]);
}

/** A published questionnaire with a scale, a choice, and a free-text question. */
function kuesionerTerbit(array $ubah = []): Kuesioner
{
    $layanan = test()->layanan;
    $k = $layanan->buat(dataKuesioner($ubah), test()->baak);

    $layanan->tambahPertanyaan($k, new PertanyaanData('Seberapa puas Anda?', TipePertanyaan::Skala));
    $layanan->tambahPertanyaan($k, new PertanyaanData('Layanan yang paling sering dipakai?', TipePertanyaan::Pilihan, ['BAAK', 'Keuangan', 'Perpustakaan']));
    $layanan->tambahPertanyaan($k, new PertanyaanData('Saran Anda?', TipePertanyaan::Teks, wajib: false));

    return $layanan->terbitkan($k)->refresh();
}

function isianUntuk(Kuesioner $k, int $nilai, string $pilihan, ?string $teks): array
{
    $p = $k->pertanyaan()->get();

    return [
        $p[0]->id => ['nilai' => $nilai],
        $p[1]->id => ['teks' => $pilihan],
        $p[2]->id => ['teks' => $teks],
    ];
}

function mahasiswaProdi(?Prodi $prodi = null): Mahasiswa
{
    $m = Mahasiswa::factory()->create(['prodi_id' => ($prodi ?? test()->prodi)->id]);
    $m->assignRole('mahasiswa');

    return $m;
}

describe('KuesionerService — menulis', function () {
    it('membuat kuesioner sebagai draf dengan ambang dan pembuatnya', function () {
        $k = $this->layanan->buat(dataKuesioner(['minResponden' => null]), $this->baak);

        expect($k->status())->toBe(StatusKuesioner::Draf)
            ->and($k->min_responden)->toBe((int) config('kuesioner.min_responden'))
            ->and($k->dibuat_oleh_staff_id)->toBe($this->baak->id);
    });

    it('menolak prodi pada kuesioner untuk staf', function () {
        expect(fn () => $this->layanan->buat(dataKuesioner([
            'sasaran' => SasaranKuesioner::Staf,
            'prodiId' => $this->prodi->id,
        ]), $this->baak))->toThrow(AturanAkademikException::class, 'program studi');
    });

    it('tidak pernah mengubah sifat anonim lewat perbarui', function () {
        $k = $this->layanan->buat(dataKuesioner(['anonim' => true]), $this->baak);

        $this->layanan->perbarui($k, dataKuesioner(['kode' => $k->kode, 'anonim' => false, 'nama' => 'Nama baru']));

        expect($k->refresh()->anonim)->toBeTrue()
            ->and($k->nama)->toBe('Nama baru');
    });

    it('mengunci sasaran dan ambang sesudah terbit, tetapi nama tetap dapat diubah', function () {
        $k = kuesionerTerbit();

        $this->layanan->perbarui($k, dataKuesioner([
            'kode' => $k->kode,
            'nama' => 'Judul diperjelas',
            'sasaran' => SasaranKuesioner::Dosen,
            'minResponden' => 1,
        ]));

        expect($k->refresh()->sasaran)->toBe(SasaranKuesioner::Mahasiswa)
            ->and($k->min_responden)->toBe(2)
            ->and($k->nama)->toBe('Judul diperjelas');
    });

    it('menolak pertanyaan pilihan dengan kurang dari dua opsi', function () {
        $k = $this->layanan->buat(dataKuesioner(), $this->baak);

        expect(fn () => $this->layanan->tambahPertanyaan($k, new PertanyaanData('Pilih', TipePertanyaan::Pilihan, ['Satu'])))
            ->toThrow(AturanAkademikException::class, 'dua pilihan');
    });

    it('membekukan pertanyaan sejak terbit pertama', function () {
        $k = kuesionerTerbit();

        expect(fn () => $this->layanan->tambahPertanyaan($k, new PertanyaanData('Baru', TipePertanyaan::Skala)))
            ->toThrow(AturanAkademikException::class, 'sudah pernah diterbitkan')
            ->and(fn () => $this->layanan->hapusPertanyaan($k->pertanyaan()->first()))
            ->toThrow(AturanAkademikException::class, 'sudah pernah diterbitkan');

        // Ditutup lalu dibuka kembali tidak mencairkannya.
        $this->layanan->tutup($k);
        $terbitPertama = $k->refresh()->diterbitkan_at;
        $this->travel(2)->days();
        $this->layanan->terbitkan($k);

        expect($k->refresh()->diterbitkan_at->equalTo($terbitPertama))->toBeTrue()
            ->and($k->pertanyaanTerkunci())->toBeTrue();
    });

    it('menolak menerbitkan kuesioner tanpa pertanyaan atau dengan periode yang sudah lewat', function () {
        $kosong = $this->layanan->buat(dataKuesioner(), $this->baak);

        expect(fn () => $this->layanan->terbitkan($kosong))->toThrow(AturanAkademikException::class, 'tanpa pertanyaan');

        $lewat = $this->layanan->buat(dataKuesioner(['selesai' => now()->subDay()]), $this->baak);
        $this->layanan->tambahPertanyaan($lewat, new PertanyaanData('Puas?', TipePertanyaan::Skala));

        expect(fn () => $this->layanan->terbitkan($lewat))->toThrow(AturanAkademikException::class, 'sudah lewat');
    });

    it('menutup kuesioner sehingga tidak dapat diisi lagi', function () {
        $k = kuesionerTerbit();
        $this->layanan->tutup($k);

        expect($k->refresh()->status())->toBe(StatusKuesioner::Ditutup)
            ->and(fn () => $this->layanan->isi($k, mahasiswaProdi(), isianUntuk($k, 4, 'BAAK', null)))
            ->toThrow(AturanAkademikException::class, 'tidak dibuka');
    });
});

describe('KuesionerService — sasaran dan pengisian', function () {
    it('hanya menawarkan kuesioner kepada peran dan prodi sasarannya', function () {
        $umum = kuesionerTerbit();
        $prodiSaja = kuesionerTerbit(['prodiId' => $this->prodi->id]);
        $untukDosen = kuesionerTerbit(['sasaran' => SasaranKuesioner::Dosen]);

        $sendiri = mahasiswaProdi();
        $lain = mahasiswaProdi($this->prodiLain);

        expect($this->layanan->untukResponden($sendiri)->pluck('kuesioner.id')->sort()->values()->all())
            ->toBe(collect([$umum->id, $prodiSaja->id])->sort()->values()->all())
            ->and($this->layanan->untukResponden($lain)->pluck('kuesioner.id')->all())->toBe([$umum->id])
            ->and($this->layanan->untukResponden(Dosen::factory()->create())->pluck('kuesioner.id')->all())->toBe([$untukDosen->id]);
    });

    it('menandai kuesioner yang sudah diisi', function () {
        $k = kuesionerTerbit();
        $m = mahasiswaProdi();

        $this->layanan->isi($k, $m, isianUntuk($k, 5, 'BAAK', null));

        expect($this->layanan->untukResponden($m)->first()['sudah'])->toBeTrue();
    });

    it('menolak responden di luar sasaran, langsung di service', function () {
        $k = kuesionerTerbit(['prodiId' => $this->prodi->id]);

        expect(fn () => $this->layanan->isi($k, mahasiswaProdi($this->prodiLain), isianUntuk($k, 4, 'BAAK', null)))
            ->toThrow(AturanAkademikException::class, 'tidak ditujukan')
            ->and(fn () => $this->layanan->isi($k, Staff::factory()->create(), isianUntuk($k, 4, 'BAAK', null)))
            ->toThrow(AturanAkademikException::class, 'tidak ditujukan');
    });

    it('menolak nilai di luar skala dan pilihan yang tidak ada', function () {
        $k = kuesionerTerbit();

        expect(fn () => $this->layanan->isi($k, mahasiswaProdi(), isianUntuk($k, 9, 'BAAK', null)))
            ->toThrow(AturanAkademikException::class, 'nomor 1 tidak sah')
            ->and(fn () => $this->layanan->isi($k, mahasiswaProdi(), isianUntuk($k, 3, 'Kantin', null)))
            ->toThrow(AturanAkademikException::class, 'nomor 2 tidak sah')
            ->and(KuesionerPartisipasi::count())->toBe(0);
    });

    it('tidak menyimpan isian opsional yang dikosongkan sebagai jawaban', function () {
        $k = kuesionerTerbit();

        $this->layanan->isi($k, mahasiswaProdi(), isianUntuk($k, 3, 'BAAK', ''));

        expect(KuesionerJawabanAnonim::count())->toBe(2);
    });
});

describe('KuesionerService — hasil dan ekspor', function () {
    it('menahan hasil kuesioner anonim di bawah ambang, tetapi tidak yang beridentitas', function () {
        $anonim = kuesionerTerbit();
        $this->layanan->isi($anonim, mahasiswaProdi(), isianUntuk($anonim, 4, 'BAAK', 'Satu-satunya komentar'));

        $beridentitas = kuesionerTerbit(['anonim' => false]);
        $this->layanan->isi($beridentitas, mahasiswaProdi(), isianUntuk($beridentitas, 4, 'BAAK', null));

        expect($this->layanan->hasilTerbuka($anonim))->toBeFalse()
            ->and(fn () => $this->layanan->barisEkspor($anonim))->toThrow(AturanAkademikException::class, 'ambang')
            ->and($this->layanan->hasilTerbuka($beridentitas))->toBeTrue();
    });

    it('menghitung sebaran skala dan pilihan, termasuk opsi yang tidak dipilih', function () {
        $k = kuesionerTerbit();
        $this->layanan->isi($k, mahasiswaProdi(), isianUntuk($k, 5, 'BAAK', null));
        $this->layanan->isi($k, mahasiswaProdi(), isianUntuk($k, 3, 'BAAK', null));

        $hasil = $this->layanan->hasil($k);

        expect($hasil[0]['sebaran'])->toBe([1 => 0, 2 => 0, 3 => 1, 4 => 0, 5 => 1])
            ->and($hasil[0]['rerata'])->toBe(4.0)
            ->and($hasil[1]['sebaran'])->toBe(['BAAK' => 2, 'Keuangan' => 0, 'Perpustakaan' => 0]);
    });

    it('mengekspor kuesioner anonim tanpa identitas dan tanpa urutan pengisian', function () {
        $k = kuesionerTerbit();
        $pertama = mahasiswaProdi();
        $kedua = mahasiswaProdi();

        $this->layanan->isi($k, $pertama, isianUntuk($k, 5, 'BAAK', 'Zebra cross di depan kampus'));
        $this->layanan->isi($k, $kedua, isianUntuk($k, 2, 'Keuangan', 'Antrean keuangan lama'));

        $baris = collect($this->layanan->barisEkspor($k));
        $teks = json_encode($baris->all(), JSON_UNESCAPED_UNICODE);

        // Tidak ada nama, nomor induk, atau tanggal pengisian.
        foreach ([$pertama, $kedua] as $m) {
            expect($teks)->not->toContain($m->nama)->not->toContain($m->nim);
        }
        expect($teks)->not->toContain(now()->format('Y-m-d'));

        // Isian bebas diurutkan abjad, bukan urutan pengisian (yang pertama
        // mengisi justru menulis "Zebra…").
        $komentar = $baris->filter(fn (array $b): bool => ($b[0] ?? null) === '' && count($b) === 2)->pluck(1)->values()->all();
        expect($komentar)->toBe(['Antrean keuangan lama', 'Zebra cross di depan kampus']);
    });

    it('mengekspor kuesioner beridentitas satu baris per responden, bernama', function () {
        $k = kuesionerTerbit(['anonim' => false]);
        $m = mahasiswaProdi();

        $this->layanan->isi($k, $m, isianUntuk($k, 4, 'Perpustakaan', 'Tambah jam buka'));

        $baris = collect($this->layanan->barisEkspor($k))->last();

        expect($baris[0])->toBe($m->nama)
            ->and($baris[1])->toBe($m->nim)
            ->and(array_slice($baris, 3))->toBe([4, 'Perpustakaan', 'Tambah jam buka']);
    });
});

describe('layar staf', function () {
    it('membuat, menambah pertanyaan, dan menerbitkan lewat layar', function () {
        $this->actingAs($this->baak, 'staff');

        $this->post(route('admin.kuesioner.store'), [
            'kode' => 'PUAS-2026', 'nama' => 'Kepuasan 2026', 'sasaran' => 'mahasiswa', 'anonim' => '1', 'min_responden' => 3,
        ])->assertRedirect();

        $k = Kuesioner::where('kode', 'PUAS-2026')->firstOrFail();
        expect($k->anonim)->toBeTrue()->and($k->min_responden)->toBe(3);

        $this->post(route('admin.kuesioner.pertanyaan', $k), ['teks' => 'Pilih layanan', 'tipe' => 'pilihan', 'opsi' => "BAAK\nKeuangan\n\nBAAK", 'wajib' => '1'])
            ->assertSessionHas('sukses');
        expect($k->pertanyaan()->first()->opsi)->toBe(['BAAK', 'Keuangan']);

        $this->post(route('admin.kuesioner.terbitkan', $k))->assertSessionHas('sukses');
        expect($k->refresh()->is_active)->toBeTrue();

        $this->get(route('admin.kuesioner.show', $k))->assertOk()->assertSee('Pilih layanan')->assertSee(__('kuesioner.pertanyaan.terkunci'));
        $this->get(route('admin.kuesioner.index'))->assertOk()->assertSee('Kepuasan 2026');
    });

    it('menampilkan pesan tertahan tanpa memuat jawaban di bawah ambang', function () {
        $k = kuesionerTerbit();
        $this->layanan->isi($k, mahasiswaProdi(), isianUntuk($k, 1, 'BAAK', 'Komentar yang bisa dikenali'));

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.kuesioner.hasil', $k))
            ->assertOk()
            ->assertSee(__('kuesioner.hasil.tertahan_judul'))
            ->assertDontSee('Komentar yang bisa dikenali')
            ->assertDontSee(__('kuesioner.aksi.ekspor'));

        $this->get(route('admin.kuesioner.ekspor', $k))->assertRedirect()->assertSessionHas('galat');
    });

    it('menampilkan hasil dan mengunduh CSV setelah ambang tercapai', function () {
        $k = kuesionerTerbit();
        $this->layanan->isi($k, mahasiswaProdi(), isianUntuk($k, 5, 'BAAK', 'Bagus'));
        $this->layanan->isi($k, mahasiswaProdi(), isianUntuk($k, 3, 'Keuangan', '=HYPERLINK("x")'));

        $this->actingAs($this->baak, 'staff')
            ->get(route('admin.kuesioner.hasil', $k))
            ->assertOk()
            ->assertSee('4,00')
            ->assertSee('Bagus');

        $csv = $this->get(route('admin.kuesioner.ekspor', $k))->assertOk()->streamedContent();

        expect($csv)->toContain('Bagus')
            // Formula injection is neutralised by App\Support\Csv.
            ->toContain("'=HYPERLINK");

        $this->get(route('admin.kuesioner.ekspor', [$k, 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });

    it('membatasi layar menurut izin: pimpinan membaca, tidak menulis; keuangan tidak keduanya', function () {
        $k = kuesionerTerbit();

        $pimpinan = Staff::factory()->create();
        $pimpinan->assignRole('pimpinan');

        $this->actingAs($pimpinan, 'staff')->get(route('admin.kuesioner.hasil', $k))->assertOk();
        $this->actingAs($pimpinan, 'staff')->post(route('admin.kuesioner.tutup', $k))->assertForbidden();
        $this->actingAs($pimpinan, 'staff')->post(route('admin.kuesioner.store'), ['kode' => 'X', 'nama' => 'X', 'sasaran' => 'staf'])
            ->assertForbidden();

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get(route('admin.kuesioner.index'))->assertForbidden();
        $this->actingAs($keuangan, 'staff')->get(route('admin.kuesioner.ekspor', $k))->assertForbidden();
    });

    it('menolak responden membuka layar kelola', function () {
        $this->actingAs(mahasiswaProdi(), 'mahasiswa')->get('/admin/kuesioner')->assertRedirect();
    });
});

describe('layar responden', function () {
    it('mahasiswa mengisi dari portalnya dan jawabannya masuk ke tabel anonim', function () {
        $k = kuesionerTerbit();
        $m = mahasiswaProdi();
        $p = $k->pertanyaan()->get();

        $this->actingAs($m, 'mahasiswa')->get(route('mahasiswa.kuesioner.index'))->assertOk()->assertSee($k->nama);
        $this->actingAs($m, 'mahasiswa')->get(route('mahasiswa.kuesioner.show', $k))->assertOk()->assertSee(__('kuesioner.isi.anonim'));

        $this->actingAs($m, 'mahasiswa')->post(route('mahasiswa.kuesioner.kirim', $k), ['jawaban' => [
            $p[0]->id => ['nilai' => 4],
            $p[1]->id => ['teks' => 'Keuangan'],
        ]])->assertRedirect(route('mahasiswa.kuesioner.index'));

        expect(KuesionerJawabanAnonim::count())->toBe(2)
            ->and($this->layanan->sudahMengisi($k, $m))->toBeTrue();

        $this->actingAs($m, 'mahasiswa')->get(route('mahasiswa.kuesioner.show', $k))->assertOk()->assertSee(__('kuesioner.isi.sudah_isi'));
    });

    it('dosen dan staf mengisi kuesioner yang ditujukan kepada mereka', function () {
        $untukDosen = kuesionerTerbit(['sasaran' => SasaranKuesioner::Dosen, 'prodiId' => $this->prodi->id]);
        $untukStaf = kuesionerTerbit(['sasaran' => SasaranKuesioner::Staf]);

        $dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);
        $dosen->assignRole('dosen');

        $this->actingAs($dosen, 'dosen')->get(route('dosen.kuesioner.index'))->assertOk()->assertSee($untukDosen->nama);
        $this->actingAs($dosen, 'dosen')->post(route('dosen.kuesioner.kirim', $untukDosen), ['jawaban' => isianUntuk($untukDosen, 5, 'BAAK', null)])
            ->assertRedirect(route('dosen.kuesioner.index'));

        $this->actingAs($this->baak, 'staff')->get(route('admin.kuesioner-saya.index'))->assertOk()->assertSee($untukStaf->nama);
        $this->actingAs($this->baak, 'staff')->post(route('admin.kuesioner-saya.kirim', $untukStaf), ['jawaban' => isianUntuk($untukStaf, 2, 'BAAK', null)])
            ->assertRedirect(route('admin.kuesioner-saya.index'));

        expect(KuesionerPartisipasi::count())->toBe(2);
    });

    it('menolak membuka atau mengirim kuesioner prodi lain dan kuesioner draf', function () {
        $k = kuesionerTerbit(['prodiId' => $this->prodi->id]);
        $lain = mahasiswaProdi($this->prodiLain);

        $this->actingAs($lain, 'mahasiswa')->get(route('mahasiswa.kuesioner.show', $k))->assertForbidden();
        $this->actingAs($lain, 'mahasiswa')->post(route('mahasiswa.kuesioner.kirim', $k), ['jawaban' => isianUntuk($k, 4, 'BAAK', null)])
            ->assertForbidden();

        $draf = $this->layanan->buat(dataKuesioner(), $this->baak);
        $this->actingAs(mahasiswaProdi(), 'mahasiswa')->get(route('mahasiswa.kuesioner.show', $draf))->assertForbidden();

        expect(KuesionerPartisipasi::count())->toBe(0);
    });

    it('menjaga daftar responden datar berapa pun banyaknya kuesioner', function () {
        $m = mahasiswaProdi();
        kuesionerTerbit();

        $hitung = function () use ($m): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($m, 'mahasiswa')->get(route('mahasiswa.kuesioner.index'))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $satu = $hitung();
        foreach (range(1, 8) as $_) {
            $k = kuesionerTerbit();
            $this->layanan->isi($k, $m, isianUntuk($k, 4, 'BAAK', null));
        }

        expect($hitung())->toBeLessThanOrEqual($satu);
    });
});
