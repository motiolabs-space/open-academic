<?php

declare(strict_types=1);

use App\Enums\SasaranKuesioner;
use App\Enums\TipePertanyaan;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kuesioner\Kuesioner;
use App\Models\Kuesioner\KuesionerJawaban;
use App\Models\Kuesioner\KuesionerJawabanAnonim;
use App\Models\Kuesioner\KuesionerPartisipasi;
use App\Services\Kuesioner\KuesionerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * Kuesioner umum.
 *
 * The property being defended is EDOM's: for an anonymous form, no row anywhere
 * connects an answer to the person who gave it. Several of these tests exist
 * only to keep it that way.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = Prodi::factory()->create();
    $this->kuesioner = app(KuesionerService::class);
});

function kuesionerUji(bool $anonim = true): Kuesioner
{
    $k = Kuesioner::create([
        'kode' => 'K-'.uniqid(),
        'nama' => 'Survei uji',
        'sasaran' => SasaranKuesioner::Mahasiswa,
        'anonim' => $anonim,
        'is_active' => true,
    ]);

    $k->pertanyaan()->createMany([
        ['urutan' => 1, 'teks' => 'Seberapa puas Anda?', 'tipe' => TipePertanyaan::Skala],
        ['urutan' => 2, 'teks' => 'Saran Anda?', 'tipe' => TipePertanyaan::Teks, 'wajib' => false],
    ]);

    return $k->refresh();
}

function respondenUji(): Mahasiswa
{
    return Mahasiswa::factory()->create(['prodi_id' => test()->prodi->id]);
}

/** @return array<int, array<string, mixed>> */
function jawabanUji(Kuesioner $k, int $nilai = 4, ?string $teks = 'Bagus'): array
{
    $p = $k->pertanyaan()->get();

    return [
        $p[0]->id => ['nilai' => $nilai],
        $p[1]->id => ['teks' => $teks],
    ];
}

describe('anonimitas struktural', function () {
    it('tidak punya kolom responden sama sekali pada tabel jawaban anonim', function () {
        /*
         * Jaminannya bukan janji runtime, melainkan ketiadaan tempat menaruh
         * tautannya. Tes ini memeriksa skemanya, bukan perilakunya.
         */
        $kolom = Schema::getColumnListing('kuesioner_jawaban_anonim');

        expect($kolom)->not->toContain('responden_id')
            ->and($kolom)->not->toContain('responden_type')
            ->and($kolom)->not->toContain('mahasiswa_id');
    });

    it('menulis jawaban anonim ke tabel tanpa identitas', function () {
        $k = kuesionerUji(anonim: true);
        $mahasiswa = respondenUji();

        $this->kuesioner->isi($k, $mahasiswa, jawabanUji($k));

        expect(KuesionerJawabanAnonim::count())->toBe(2)
            ->and(KuesionerJawaban::count())->toBe(0);
    });

    it('tetap mencatat siapa yang mengisi, tanpa apa yang diisinya', function () {
        // Partisipasi dibutuhkan untuk gerbang "sudah mengisi" dan angka
        // respons; keduanya tidak butuh isinya.
        $k = kuesionerUji(anonim: true);
        $mahasiswa = respondenUji();

        $this->kuesioner->isi($k, $mahasiswa, jawabanUji($k));

        expect(KuesionerPartisipasi::where('responden_id', $mahasiswa->id)->exists())->toBeTrue()
            ->and($this->kuesioner->jumlahResponden($k))->toBe(1);
    });

    it('menulis ke tabel beridentitas hanya untuk kuesioner yang memang tidak anonim', function () {
        $k = kuesionerUji(anonim: false);
        $mahasiswa = respondenUji();

        $this->kuesioner->isi($k, $mahasiswa, jawabanUji($k));

        expect(KuesionerJawaban::where('responden_id', $mahasiswa->id)->count())->toBe(2)
            ->and(KuesionerJawabanAnonim::count())->toBe(0);
    });
});

describe('pengisian', function () {
    it('menolak pengisian kedua kali', function () {
        $k = kuesionerUji();
        $mahasiswa = respondenUji();

        $this->kuesioner->isi($k, $mahasiswa, jawabanUji($k));

        expect(fn () => $this->kuesioner->isi($k, $mahasiswa, jawabanUji($k)))
            ->toThrow(AturanAkademikException::class, 'sudah mengisi');
    });

    it('menolak ketika jendela belum dibuka', function () {
        $k = kuesionerUji();
        $k->update(['is_active' => false]);

        expect(fn () => $this->kuesioner->isi($k->refresh(), respondenUji(), jawabanUji($k)))
            ->toThrow(AturanAkademikException::class, 'tidak dibuka');
    });

    it('menolak ketika pertanyaan wajib belum dijawab', function () {
        $k = kuesionerUji();
        $p = $k->pertanyaan()->get();

        expect(fn () => $this->kuesioner->isi($k, respondenUji(), [
            $p[1]->id => ['teks' => 'hanya yang opsional'],
        ]))->toThrow(AturanAkademikException::class, 'wajib belum dijawab');
    });

    it('mengizinkan pertanyaan tidak wajib dikosongkan', function () {
        $k = kuesionerUji();
        $p = $k->pertanyaan()->get();

        $this->kuesioner->isi($k, respondenUji(), [$p[0]->id => ['nilai' => 5]]);

        expect(KuesionerJawabanAnonim::count())->toBe(1);
    });
});

describe('hasil', function () {
    it('merata-ratakan skala dan mendaftar teks apa adanya', function () {
        // Rerata dari prosa bukan apa-apa, dan cacahnya tidak memberi tahu
        // pembaca hal yang dapat ditindaklanjuti.
        $k = kuesionerUji();

        $this->kuesioner->isi($k, respondenUji(), jawabanUji($k, 4, 'Ruangnya panas'));
        $this->kuesioner->isi($k, respondenUji(), jawabanUji($k, 2, 'Antrean lama'));

        $hasil = $this->kuesioner->hasil($k);

        expect($hasil[0]['rerata'])->toBe(3.0)
            ->and($hasil[0]['jumlah'])->toBe(2)
            ->and($hasil[1]['teks'])->toContain('Ruangnya panas')
            ->and($hasil[1]['rerata'])->toBeNull();
    });

    it('membaca dari tabel yang benar untuk kuesioner beridentitas', function () {
        $k = kuesionerUji(anonim: false);

        $this->kuesioner->isi($k, respondenUji(), jawabanUji($k, 5, 'Mantap'));

        expect($this->kuesioner->hasil($k)[0]['rerata'])->toBe(5.0);
    });
});
