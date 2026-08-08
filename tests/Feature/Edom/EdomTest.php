<?php

declare(strict_types=1);

use App\Enums\KategoriEdom;
use App\Enums\SemesterType;
use App\Enums\TipeJawabanEdom;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Edom\EdomJawaban;
use App\Models\Edom\EdomPartisipasi;
use App\Models\Edom\EdomPeriode;
use App\Models\Edom\EdomPertanyaan;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Edom\EdomService;
use App\Services\Edom\HasilEdom;
use App\Services\Edom\KelolaEdom;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create();
    $this->dosen = Dosen::factory()->create(['is_active' => true]);

    $this->kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $this->term->id,
        'prodi_id' => $this->prodi->id,
        'mata_kuliah_id' => MataKuliah::factory()->create(['prodi_id' => $this->prodi->id])->id,
    ]);

    $this->kelas->dosen()->attach($this->dosen->id, ['peran' => 'pengampu']);

    $this->periode = EdomPeriode::create([
        'tahun_akademik_id' => $this->term->id,
        'nama' => 'EDOM 2026/2027 Ganjil',
        'mulai' => now()->subWeek(),
        'selesai' => now()->addWeek(),
        'min_responden' => 3,
        'is_active' => true,
    ]);

    foreach ([
        [KategoriEdom::Materi, TipeJawabanEdom::Skala, 'Dosen menguasai materi.'],
        [KategoriEdom::Penyampaian, TipeJawabanEdom::Skala, 'Penyampaian mudah dipahami.'],
        [KategoriEdom::Sikap, TipeJawabanEdom::Teks, 'Saran untuk dosen ini.'],
    ] as $i => [$kategori, $tipe, $teks]) {
        EdomPertanyaan::create([
            'edom_periode_id' => $this->periode->id,
            'kategori' => $kategori,
            'tipe' => $tipe,
            'teks' => $teks,
            'urutan' => $i,
        ]);
    }

    $this->edom = app(EdomService::class);
    $this->hasil = app(HasilEdom::class);
});

/** Mahasiswa yang benar-benar mengambil kelas uji. */
function pesertaKelas(): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => test()->prodi->id]);

    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 3,
        'batas_sks' => 24,
        'status' => 'disetujui',
    ]);

    KrsDetail::create([
        'krs_id' => $krs->id,
        'kelas_kuliah_id' => test()->kelas->id,
        'sks' => 3,
    ]);

    return $mahasiswa->fresh();
}

/**
 * Token konsumen Bridge dengan scope tertentu.
 *
 * Diberi nama sendiri karena helper Pest bersifat global — tokenUji() di berkas
 * lain akan bentrok tanpa suara sampai seluruh suite dijalankan sekaligus.
 *
 * @param array<int, string> $scopes
 */
function tokenBridge(array $scopes): string
{
    $consumer = BridgeConsumer::create([
        'nama' => 'Open Campus',
        'slug' => 'open-campus-'.Str::random(6),
        'scopes' => $scopes,
        'is_active' => true,
    ]);

    return $consumer->createToken('uji', $scopes)->plainTextToken;
}

/** @return array<int, array<string, mixed>> */
function jawabanLengkap(int $nilai = 4, ?string $komentar = null): array
{
    return test()->periode->pertanyaan()->get()->map(fn (EdomPertanyaan $p): array => [
        'pertanyaan_id' => $p->id,
        'nilai' => $p->tipe === TipeJawabanEdom::Skala ? $nilai : null,
        'teks' => $p->tipe === TipeJawabanEdom::Teks ? $komentar : null,
    ])->all();
}

describe('anonimitas ditegakkan skema, bukan kedisiplinan', function () {
    it('tidak punya kolom apa pun yang menghubungkan jawaban ke mahasiswa', function () {
        /*
         * Inti seluruh modul. Janji anonimitas yang bergantung pada tidak ada
         * orang menjalankan kueri yang salah bukanlah anonimitas.
         *
         * Tes ini gagal begitu seseorang menambahkan mahasiswa_id atau kaitan
         * ke tabel partisipasi demi permintaan yang terdengar wajar —
         * "biar mahasiswa bisa mengedit jawabannya".
         */
        $kolom = Schema::getColumnListing('edom_jawaban');

        expect($kolom)->not->toContain('mahasiswa_id')
            ->and($kolom)->not->toContain('edom_partisipasi_id')

            // Tanpa pengenal respons pun: itu akan mengorelasikan pendapat satu
            // orang lintas pertanyaan, cukup untuk merekonstruksi individu pada
            // kelas kecil.
            ->and($kolom)->not->toContain('respons_id');
    });

    it('menyimpan partisipasi dan jawaban tanpa saling menunjuk', function () {
        $mahasiswa = pesertaKelas();

        $this->edom->isi($this->periode, $mahasiswa, $this->kelas, $this->dosen, jawabanLengkap(5, 'Bagus.'));

        expect(EdomPartisipasi::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
            ->and(EdomJawaban::count())->toBe(3);
    });
});

describe('siapa boleh mengisi', function () {
    it('menolak mahasiswa yang tidak mengambil kelas itu', function () {
        // Tanpa ini, EDOM adalah kotak komentar terbuka atas dosen bernama.
        $asing = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        expect(fn () => $this->edom->isi($this->periode, $asing, $this->kelas, $this->dosen, jawabanLengkap()))
            ->toThrow(AturanAkademikException::class, 'tidak terdaftar pada kelas ini');
    });

    it('menolak dosen yang tidak mengampu kelas itu', function () {
        $lain = Dosen::factory()->create();

        expect(fn () => $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $lain, jawabanLengkap()))
            ->toThrow(AturanAkademikException::class, 'tidak mengampu kelas ini');
    });

    it('menolak pengisian kedua', function () {
        $mahasiswa = pesertaKelas();
        $this->edom->isi($this->periode, $mahasiswa, $this->kelas, $this->dosen, jawabanLengkap());

        expect(fn () => $this->edom->isi($this->periode, $mahasiswa->fresh(), $this->kelas, $this->dosen, jawabanLengkap()))
            ->toThrow(AturanAkademikException::class, 'sudah mengisi EDOM');
    });

    it('menolak di luar jendela meski periodenya masih aktif', function () {
        // Aktif adalah keputusan kampus, tanggal adalah jadwalnya. Tanpa
        // keduanya, jendelanya tidak pernah benar-benar tertutup.
        $this->periode->update(['selesai' => now()->subDay()]);

        expect(fn () => $this->edom->isi($this->periode->fresh(), pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap()))
            ->toThrow(AturanAkademikException::class, 'tidak dibuka');
    });

    it('menolak pengisian yang tidak lengkap', function () {
        // Pengisian sebagian tetap membuka gerbang sambil menyumbang rerata yang
        // miring — dan karena tak dapat ditelusuri, tak pernah bisa dicabut.
        $mahasiswa = pesertaKelas();

        expect(fn () => $this->edom->isi($this->periode, $mahasiswa, $this->kelas, $this->dosen, []))
            ->toThrow(AturanAkademikException::class, 'wajib dijawab dengan nilai 1 sampai 5');
    });

    it('membiarkan komentar bebas kosong', function () {
        // Memaksa komentar menghasilkan "-" dan "bagus": kebisingan berbentuk data.
        expect(fn () => $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap(4, null)))
            ->not->toThrow(AturanAkademikException::class);
    });
});

describe('ambang responden', function () {
    it('tidak menampilkan apa pun di bawah ambang', function () {
        /*
         * Tiga jawaban pada kelas berisi empat orang memberi tahu dosennya
         * hampir persis siapa berpendapat apa. Cacahnya sendiri adalah petunjuk,
         * jadi ia pun tidak ditampilkan.
         */
        foreach (range(1, 2) as $i) {
            $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap(5));
        }

        $hasil = $this->hasil->kelas($this->periode, $this->kelas->id, $this->dosen, true);

        expect($hasil['cukup'])->toBeFalse()
            ->and($hasil['responden'])->toBe(0)
            ->and($hasil['rerata'])->toBeNull()
            ->and($hasil['komentar'])->toBe([]);
    });

    it('membuka hasil setelah ambang tercapai', function () {
        foreach (range(1, 3) as $i) {
            $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap(4));
        }

        $hasil = $this->hasil->kelas($this->periode, $this->kelas->id, $this->dosen, true);

        expect($hasil['cukup'])->toBeTrue()
            ->and($hasil['responden'])->toBe(3)
            ->and($hasil['rerata'])->toBe(4.0)
            ->and($hasil['kategori'])->toHaveKey(KategoriEdom::Materi->label());
    });

    it('menyembunyikan komentar dari pembaca yang tidak berhak', function () {
        foreach (range(1, 3) as $i) {
            $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap(4, 'Komentar rahasia.'));
        }

        $denganKomentar = $this->hasil->kelas($this->periode, $this->kelas->id, $this->dosen, true);
        $tanpaKomentar = $this->hasil->kelas($this->periode, $this->kelas->id, $this->dosen, false);

        expect($denganKomentar['komentar'])->toHaveCount(3)
            ->and($tanpaKomentar['komentar'])->toBe([])

            // Skor tetap terbuka: angka dapat dirata-ratakan sampai tidak
            // seorang pun dapat dikenali, kalimat tidak.
            ->and($tanpaKomentar['rerata'])->toBe(4.0);
    });

    it('tidak memasukkan kelas di bawah ambang ke ringkasan dosen', function () {
        foreach (range(1, 2) as $i) {
            $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap(5));
        }

        expect($this->hasil->ringkasanDosen($this->periode))->toBeEmpty();
    });

    it('meringkas dosen setelah ambang tercapai', function () {
        foreach (range(1, 3) as $i) {
            $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap(4));
        }

        $ringkas = $this->hasil->ringkasanDosen($this->periode);

        expect($ringkas)->toHaveCount(1)
            ->and($ringkas[0]['responden'])->toBe(3)
            ->and($ringkas[0]['rerata'])->toBe(4.0)
            ->and($ringkas[0]['dosen']->id)->toBe($this->dosen->id);
    });
});

describe('gerbang', function () {
    it('menahan KRS selama masih ada EDOM tertunda', function () {
        config(['edom.gerbang' => 'krs']);
        $mahasiswa = pesertaKelas();

        expect($this->edom->penghalang($mahasiswa, 'krs'))->toContain('belum Anda isi');
    });

    it('melepas gerbang setelah lengkap', function () {
        config(['edom.gerbang' => 'krs']);
        $mahasiswa = pesertaKelas();

        $this->edom->isi($this->periode, $mahasiswa, $this->kelas, $this->dosen, jawabanLengkap());

        expect($this->edom->penghalang($mahasiswa->fresh(), 'krs'))->toBeNull();
    });

    it('tidak menahan apa pun saat gerbang dimatikan', function () {
        config(['edom.gerbang' => 'nonaktif']);

        expect($this->edom->penghalang(pesertaKelas(), 'krs'))->toBeNull();
    });

    it('tidak menahan gerbang yang tidak dipilih kampus', function () {
        config(['edom.gerbang' => 'krs']);

        expect($this->edom->penghalang(pesertaKelas(), 'khs'))->toBeNull();
    });

    it('mendaftar yang tertunda per pasangan kelas dan dosen', function () {
        $kedua = Dosen::factory()->create();
        $this->kelas->dosen()->attach($kedua->id, ['peran' => 'pendamping']);

        $mahasiswa = pesertaKelas();

        expect($this->edom->tertunda($this->periode, $mahasiswa))->toHaveCount(2);

        $this->edom->isi($this->periode, $mahasiswa, $this->kelas, $this->dosen, jawabanLengkap());

        expect($this->edom->tertunda($this->periode, $mahasiswa->fresh()))->toHaveCount(1);
    });
});

describe('mengelola instrumen', function () {
    it('mengunci pertanyaan begitu satu jawaban masuk', function () {
        /*
         * Mengubah rumusan setelah ada jawaban diam-diam menulis ulang arti
         * angka yang sudah tersimpan, dan menghapus pertanyaan membuang jawaban
         * yang tak akan pernah bisa dikumpulkan lagi — penulisnya memang tidak
         * dapat dikenali, itu rancangannya.
         */
        $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap());

        expect(fn () => app(KelolaEdom::class)->tambahPertanyaan(
            $this->periode,
            KategoriEdom::Disiplin,
            'Pertanyaan susulan.',
            TipeJawabanEdom::Skala,
        ))->toThrow(AturanAkademikException::class, 'sudah ada jawaban yang masuk');
    });

    it('menolak membuka periode tanpa pertanyaan', function () {
        // Periode terbuka tanpa pertanyaan memperlihatkan formulir kosong yang
        // tidak bisa dikirim — dan bila gerbang menyala, menahan KRS di balik
        // sesuatu yang mustahil diselesaikan mahasiswa.
        $term = TahunAkademik::factory()->term(2027, SemesterType::Genap)->create();

        $kosong = EdomPeriode::create([
            'tahun_akademik_id' => $term->id,
            'nama' => 'EDOM tanpa isi',
            'mulai' => now()->subDay(),
            'selesai' => now()->addWeek(),
            'min_responden' => 3,
        ]);

        expect(fn () => app(KelolaEdom::class)->aktifkan($kosong))
            ->toThrow(AturanAkademikException::class, 'belum memiliki pertanyaan');
    });

    it('menyalin pertanyaan sebagai salinan, bukan pemakaian bersama', function () {
        $term = TahunAkademik::factory()->term(2027, SemesterType::Genap)->create();

        $baru = EdomPeriode::create([
            'tahun_akademik_id' => $term->id,
            'nama' => 'EDOM 2027/2028 Genap',
            'mulai' => now()->addMonth(),
            'selesai' => now()->addMonths(2),
            'min_responden' => 3,
        ]);

        $jumlah = app(KelolaEdom::class)->salinPertanyaan($this->periode, $baru);

        expect($jumlah)->toBe(3)
            ->and($baru->pertanyaan()->count())->toBe(3)

            // Baris baru, bukan baris yang sama: hasil periode lama tetap
            // terikat pada rumusan lamanya.
            ->and($baru->pertanyaan()->pluck('id')->intersect($this->periode->pertanyaan()->pluck('id')))
            ->toBeEmpty();
    });

    it('menolak dua periode pada satu semester', function () {
        expect(fn () => app(KelolaEdom::class)->buatPeriode(
            $this->term, 'EDOM kedua', now()->toDateString(), now()->addWeek()->toDateString(), 3,
        ))->toThrow(AturanAkademikException::class, 'sudah memiliki periode EDOM');
    });
});

describe('layar', function () {
    it('memperlihatkan yang tertunda kepada mahasiswa dan menerima pengisiannya', function () {
        $mahasiswa = pesertaKelas();

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->get('/mahasiswa/edom')
            ->assertOk()
            ->assertSee($this->dosen->nama);

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->post('/mahasiswa/edom', [
                'kelas_kuliah_id' => $this->kelas->id,
                'dosen_id' => $this->dosen->id,
                'jawaban' => jawabanLengkap(5, 'Terima kasih.'),
            ])
            ->assertRedirect();

        expect(EdomPartisipasi::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);
    });

    it('menolak pengisian atas kelas yang tidak diambil mahasiswa itu', function () {
        // Pengenal di formulir dapat disunting; yang menentukan adalah siapa
        // yang sedang masuk, bukan angka yang dikirim.
        $asing = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        $this->actingAs($asing, 'mahasiswa')
            ->post('/mahasiswa/edom', [
                'kelas_kuliah_id' => $this->kelas->id,
                'dosen_id' => $this->dosen->id,
                'jawaban' => jawabanLengkap(),
            ])
            ->assertSessionHas('galat');

        expect(EdomPartisipasi::count())->toBe(0);
    });

    it('hanya memperlihatkan hasil dosen yang sedang masuk', function () {
        foreach (range(1, 3) as $i) {
            $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap(4));
        }

        $lain = Dosen::factory()->create(['is_active' => true]);
        $lain->assignRole('dosen');
        $this->dosen->assignRole('dosen');

        // Tidak ada pengenal dosen di URL, jadi tidak ada bentuk permintaan yang
        // mengembalikan nilai kolega.
        $this->actingAs($lain, 'dosen')
            ->get('/dosen/edom')
            ->assertOk()
            ->assertDontSee($this->kelas->mataKuliah->nama);

        $this->actingAs($this->dosen, 'dosen')
            ->get('/dosen/edom')
            ->assertOk()
            ->assertSee($this->kelas->mataKuliah->nama);
    });
});

describe('Campus Bridge', function () {
    it('menerbitkan agregat tanpa jawaban maupun komentar', function () {
        config(['edom.komentar' => 'prodi']);

        foreach (range(1, 3) as $i) {
            $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap(4, 'Rahasia sekali.'));
        }

        $token = tokenBridge(array_keys(config('bridge.scopes')));

        $respons = $this->getJson('/api/bridge/v1/teaching-evaluations', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $respons->assertJsonPath('data.dosen.0.rerata', 4)
            ->assertJsonPath('data.dosen.0.jumlah_responden', 3);

        // Bukan disaring — memang tidak ada jalur kode yang menghasilkannya.
        expect($respons->getContent())->not->toContain('Rahasia sekali');
    });

    it('menahan kelas di bawah ambang, termasuk cacahnya', function () {
        foreach (range(1, 2) as $i) {
            $this->edom->isi($this->periode, pesertaKelas(), $this->kelas, $this->dosen, jawabanLengkap(4));
        }

        $token = tokenBridge(array_keys(config('bridge.scopes')));

        $this->getJson('/api/bridge/v1/teaching-evaluations', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()->assertJsonPath('data.dosen', []);
    });

    it('menolak token tanpa scope evaluasi', function () {
        $token = tokenBridge(['lecturers.read']);

        $this->getJson('/api/bridge/v1/teaching-evaluations', [
            'Authorization' => 'Bearer '.$token,
        ])->assertForbidden();
    });
});
