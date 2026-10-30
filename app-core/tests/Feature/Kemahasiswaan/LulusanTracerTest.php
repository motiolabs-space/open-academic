<?php

declare(strict_types=1);

use App\DTOs\Kemahasiswaan\BarisLulusan;
use App\DTOs\Kemahasiswaan\FilterLulusan;
use App\DTOs\Kemahasiswaan\KontakLulusanData;
use App\Enums\EducationLevel;
use App\Enums\SemesterType;
use App\Enums\StatusPekerjaanAlumni;
use App\Enums\StudentStatus;
use App\Enums\SumberStatusPekerjaan;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Kemahasiswaan\Alumni;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Services\Kemahasiswaan\DataLulusanService;
use App\Services\Kemahasiswaan\ImporStatusPekerjaan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * Graduates for the national tracer study (ADR-037 §2): contact checks, the
 * logged export, and employment status read back into alumni — then visible
 * on the Bridge. No questionnaire is tested because none lives here.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    config(['bkd.ekspor.pemisah_csv' => ',', 'kemahasiswaan.lulusan.verifikasi_berlaku_bulan' => 12]);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => '55201', 'nama' => 'Informatika', 'jenjang' => EducationLevel::S1]);
    $this->lulusan = app(DataLulusanService::class);
    $this->impor = app(ImporStatusPekerjaan::class);

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');
    $this->pimpinan = Staff::factory()->create();
    $this->pimpinan->assignRole('pimpinan');
});

/** A confirmed graduate with an alumni row; contact verified last month unless overridden. */
function tracerLulusan(array $alumni = [], array $mahasiswa = []): Alumni
{
    $m = Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'nim' => '2201'.random_int(100000, 999999),
        'email' => 'akun'.Str::random(6).'@kampus.demo.test',
        'nik' => '9900000000000001',
        'angkatan' => 2022,
        'status' => StudentStatus::Lulus,
    ], $mahasiswa));

    Yudisium::create([
        'mahasiswa_id' => $m->id, 'tahun_akademik_id' => test()->term->id, 'total_sks' => 144, 'ipk' => 3.5,
        'status' => 'ditetapkan', 'tanggal_yudisium' => '2026-08-20', 'tanggal_lulus' => '2026-08-20',
    ]);

    return Alumni::create(array_merge([
        'mahasiswa_id' => $m->id,
        'tahun_lulus' => 2026,
        'email_pribadi' => 'pribadi'.Str::random(6).'@demo.test',
        'telepon' => '0899 0000 1234',
        'status_pekerjaan' => StatusPekerjaanAlumni::BelumDitelusuri,
        'kontak_diverifikasi_at' => now()->subMonth(),
    ], $alumni));
}

function tracerBerkas(string $isi): UploadedFile
{
    return UploadedFile::fake()->createWithContent('tracer.csv', $isi);
}

describe('kelengkapan kontak', function () {
    it('membedakan kontak lengkap, perlu verifikasi, dan kosong — termasuk surel kampus yang mati setelah lulus', function () {
        $lengkap = $this->lulusan->periksa(tracerLulusan());
        $kampus = tracerLulusan(['email_pribadi' => null]);
        $kampus->update(['email_pribadi' => $kampus->mahasiswa->email]);
        $salah = $this->lulusan->periksa(tracerLulusan(['telepon' => '12345', 'kontak_diverifikasi_at' => now()->subMonths(13)]));
        $kosong = $this->lulusan->periksa(tracerLulusan(['email_pribadi' => null, 'telepon' => null, 'kontak_diverifikasi_at' => null]));

        expect($lengkap->statusKontak)->toBe(BarisLulusan::LENGKAP)
            ->and($lengkap->tanda)->toBe([])
            ->and($this->lulusan->periksa($kampus->fresh())->tanda)->toBe(['surel_kampus'])
            ->and($salah->statusKontak)->toBe(BarisLulusan::PERLU_VERIFIKASI)
            ->and($salah->tanda)->toBe(['telepon_tidak_sah', 'verifikasi_lama'])
            ->and($kosong->statusKontak)->toBe(BarisLulusan::KOSONG)
            ->and($kosong->tanda)->toBe(['surel_kosong', 'telepon_kosong', 'belum_diverifikasi']);
    });

    it('mengenali nomor telepon Indonesia dalam penulisan yang lazim', function (string $nomor, bool $sah) {
        expect(DataLulusanService::teleponSah($nomor))->toBe($sah);
    })->with([
        ['0812-3456-7890', true],
        ['+62 812 3456 7890', true],
        ['6281234567890', true],
        ['(021) 555 1234', true],
        ['0021 5551234', false],
        ['12345', false],
        ['08123', false],
    ]);

    it('menyaring per tahun lulus, prodi, dan yang perlu diverifikasi', function () {
        $a = tracerLulusan();
        $b = tracerLulusan(['telepon' => null]);
        tracerLulusan(['tahun_lulus' => 2025]);
        $lain = Prodi::factory()->create(['kode' => '57401']);
        tracerLulusan([], ['prodi_id' => $lain->id]);

        $tahun = $this->lulusan->daftar(new FilterLulusan(2026, $this->prodi->id));
        $perlu = $this->lulusan->daftar(new FilterLulusan(2026, $this->prodi->id, true));

        expect($tahun->map(fn ($x) => $x->alumni->id)->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all())
            ->and($perlu->map(fn ($x) => $x->alumni->id)->all())->toBe([$b->id])
            ->and($this->lulusan->tahunLulus())->toBe([2026, 2025]);
    });
});

describe('verifikasi kontak', function () {
    it('menyimpan kontak, menandainya terverifikasi oleh staf, dan mencatatnya di log', function () {
        $a = tracerLulusan(['telepon' => null, 'kontak_diverifikasi_at' => null]);

        $this->actingAs($this->baak, 'staff')
            ->put(route('admin.lulusan.kontak', $a), ['email_pribadi' => 'Baru@Demo.test', 'telepon' => '0899 1111 2222', 'kontak_tidak_dibagikan' => '0'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $a->refresh();

        expect($a->email_pribadi)->toBe('baru@demo.test')
            ->and($a->telepon)->toBe('0899 1111 2222')
            ->and($a->kontak_diverifikasi_by_staff_id)->toBe($this->baak->id)
            ->and($a->kontak_diverifikasi_at->isToday())->toBeTrue()
            ->and($this->lulusan->periksa($a)->statusKontak)->toBe(BarisLulusan::LENGKAP)
            ->and(LogAktivitas::query()->forSubject($a)->where('event', 'updated')->exists())->toBeTrue();
    });

    it('menolak nomor telepon yang tidak berbentuk nomor Indonesia', function () {
        $this->actingAs($this->baak, 'staff')
            ->put(route('admin.lulusan.kontak', tracerLulusan()), ['telepon' => '12345'])
            ->assertSessionHasErrors('telepon');
    });

    it('memperlihatkan daftar kepada pimpinan tanpa isi kontak, dan tidak membiarkannya mengubah', function () {
        $a = tracerLulusan(['email_pribadi' => 'rahasia@demo.test']);

        $this->actingAs($this->pimpinan, 'staff')->get(route('admin.lulusan'))
            ->assertOk()->assertSee($a->mahasiswa->nim)->assertDontSee('rahasia@demo.test');
        $this->actingAs($this->baak, 'staff')->get(route('admin.lulusan'))
            ->assertOk()->assertSee('rahasia@demo.test');

        $this->actingAs($this->pimpinan, 'staff')
            ->put(route('admin.lulusan.kontak', $a), ['email_pribadi' => 'x@demo.test'])
            ->assertForbidden();

        expect(app(DataLulusanService::class)->verifikasiKontak($a, new KontakLulusanData(null, null, true), $this->baak)->kontak_tidak_dibagikan)->toBeTrue();
    });
});

describe('ekspor tracer', function () {
    it('menulis kolom yang dibutuhkan tracer — tanpa NIK — mengosongkan kontak yang tidak boleh dibagikan, dan mencatat unduhannya', function () {
        $a = tracerLulusan(['email_pribadi' => 'ayu@demo.test', 'telepon' => '0899 0000 1111'], ['nim' => '220101', 'nama' => '=Ayu Lestari']);
        tracerLulusan(['email_pribadi' => 'tolak@demo.test', 'kontak_tidak_dibagikan' => true], ['nim' => '220102', 'nama' => 'Budi Santoso']);

        $isi = $this->actingAs($this->baak, 'staff')
            ->post(route('admin.lulusan.ekspor'), ['tahun_lulus' => 2026, 'format' => 'csv', 'keperluan' => 'Unggah data lulusan ke tracer study nasional'])
            ->assertOk()
            ->streamedContent();

        $baris = explode("\n", trim(ltrim($isi, "\xEF\xBB\xBF")));
        $tanggal = $a->kontak_diverifikasi_at->toDateString();

        expect($baris)->toBe([
            'NIM,Nama,"Kode Prodi","Program Studi",Jenjang,"Tahun Masuk","Tahun Lulus","Tanggal Lulus",Surel,Telepon,"Kontak Diverifikasi",Catatan',
            "220101,\"'=Ayu Lestari\",55201,Informatika,S1,2022,2026,2026-08-20,ayu@demo.test,\"0899 0000 1111\",{$tanggal},",
            "220102,\"Budi Santoso\",55201,Informatika,S1,2022,2026,2026-08-20,,,{$tanggal},\"Lulusan menolak kontaknya dibagikan\"",
        ])->and($isi)->not->toContain('9900000000000001');

        $log = LogAktivitas::query()->where('subject_type', 'EksporTracer')->sole();

        expect($log->event)->toBe('exported')
            ->and($log->causer_id)->toBe($this->baak->id)
            ->and($log->changes)->toMatchArray(['tahun_lulus' => 2026, 'jumlah' => 2, 'format' => 'csv', 'keperluan' => 'Unggah data lulusan ke tracer study nasional']);
    });

    it('mewajibkan keperluan, dan menolak staf tanpa izin ekspor lulusan', function () {
        tracerLulusan();

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.lulusan.ekspor'), ['format' => 'csv'])
            ->assertSessionHasErrors('keperluan');

        $this->actingAs($this->pimpinan, 'staff')
            ->post(route('admin.lulusan.ekspor'), ['format' => 'csv', 'keperluan' => 'Unggah ke tracer nasional'])
            ->assertForbidden();

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');
        $this->actingAs($keuangan, 'staff')->get(route('admin.lulusan'))->assertForbidden();

        expect(LogAktivitas::query()->where('subject_type', 'EksporTracer')->exists())->toBeFalse();
    });
});

describe('impor status pekerjaan', function () {
    it('membaca CSV titik koma ber-BOM dengan label kuesioner, dan menerapkannya dengan sumber dan tanggal survei', function () {
        $a = tracerLulusan([], ['nim' => '220201']);
        $b = tracerLulusan([], ['nim' => '220202']);
        $c = tracerLulusan([], ['nim' => '220203']);

        $isi = "\xEF\xBB\xBFNIM;Status Pekerjaan;Nama Instansi;Jabatan;Mulai Bekerja;Masa Tunggu;Tanggal Survei\n"
            ."220201;Bekerja (full time / part time);PT Nusantara Demo;Analis Data;2026-10-01;2;2026-10-20\n"
            ."220202;Wiraswasta;;;;;20/10/2026\n"
            ."220203;Tidak kerja tetapi sedang mencari kerja;;;;;2026-10-20\n";

        $hasil = $this->impor->terapkan($isi, SumberStatusPekerjaan::TracerNasional);

        expect($hasil->diterapkan)->toBe(3)
            ->and($hasil->ditolak())->toBe([]);

        $a->refresh();

        expect($a->status_pekerjaan)->toBe(StatusPekerjaanAlumni::Bekerja)
            ->and($a->instansi)->toBe('PT Nusantara Demo')
            ->and($a->pekerjaan)->toBe('Analis Data')
            ->and($a->mulai_bekerja->toDateString())->toBe('2026-10-01')
            ->and($a->masa_tunggu_bulan)->toBe(2)
            ->and($a->status_pekerjaan_sumber)->toBe(SumberStatusPekerjaan::TracerNasional)
            ->and($a->status_pekerjaan_per->toDateString())->toBe('2026-10-20')
            ->and($b->fresh()->status_pekerjaan)->toBe(StatusPekerjaanAlumni::Wirausaha)
            ->and($c->fresh()->status_pekerjaan)->toBe(StatusPekerjaanAlumni::MencariKerja)
            ->and(LogAktivitas::query()->forSubject($a)->where('event', 'updated')->exists())->toBeTrue();

        // The same file again changes nothing and is safe to apply.
        $ulang = $this->impor->periksa($isi, SumberStatusPekerjaan::TracerNasional);

        expect($ulang->tidakBerubah())->toHaveCount(3)
            ->and($ulang->dapatDiterapkan())->toBeFalse();
    });

    it('melaporkan setiap baris yang ditolak dengan nomor baris dan alasannya, tanpa menahan baris yang sah', function () {
        tracerLulusan([], ['nim' => '220301']);
        tracerLulusan(['status_pekerjaan_per' => '2026-09-01', 'status_pekerjaan' => StatusPekerjaanAlumni::Bekerja], ['nim' => '220302']);
        Mahasiswa::factory()->create(['nim' => '220399', 'prodi_id' => $this->prodi->id]);

        $besok = now()->addDay()->toDateString();
        $isi = "nim,status,mulai_bekerja,masa_tunggu_bulan,tanggal_survei\n"
            ."220301,bekerja,,3,2026-10-20\n"
            .",bekerja,,,\n"
            ."999999,bekerja,,,\n"
            ."220399,bekerja,,,\n"
            ."220301,wirausaha,,,\n"
            ."220302,menganggur sejati,31/02/2026,abc,2026-08-01\n"
            ."220302,studi lanjut,,,{$besok}\n";

        $hasil = $this->impor->periksa($isi, SumberStatusPekerjaan::OpenCampus);
        $ditolak = collect($hasil->ditolak())->mapWithKeys(fn ($b) => [$b->baris => $b->alasan])->all();

        expect(count($hasil->akanDiterapkan()))->toBe(1)
            ->and($hasil->akanDiterapkan()[0]->nim)->toBe('220301')
            ->and($ditolak)->toBe([
                3 => ['NIM kosong.'],
                4 => ['NIM tidak dikenal.'],
                5 => ['Mahasiswa ini belum tercatat sebagai lulusan.'],
                6 => ['NIM sudah muncul di baris 2.'],
                7 => [
                    'Status "menganggur sejati" tidak dikenali.',
                    'mulai_bekerja bukan tanggal yang sah.',
                    'Masa tunggu harus bilangan bulat 0–120 bulan.',
                    'Data yang ada lebih baru (2026-09-01).',
                ],
                8 => ['NIM sudah muncul di baris 7.', 'Tanggal survei di masa depan.'],
            ]);
    });

    it('menolak berkas tanpa kolom wajib secara utuh', function () {
        $hasil = $this->impor->periksa("nim,instansi\n220401,PT Demo\n", SumberStatusPekerjaan::SurveiKampus);

        expect($hasil->galatBerkas)->toBe(['Kolom wajib tidak ditemukan: status.'])
            ->and($hasil->dapatDiterapkan())->toBeFalse()
            ->and($this->impor->terapkan("nim,instansi\n220401,PT Demo\n", SumberStatusPekerjaan::SurveiKampus)->diterapkan)->toBe(0);
    });

    it('memeriksa unggahan lebih dulu, lalu menerapkannya dari layar, dan membuang berkas sementaranya', function () {
        Storage::fake('local');
        $a = tracerLulusan([], ['nim' => '220501']);

        $this->actingAs($this->baak, 'staff')
            ->post(route('admin.lulusan.impor'), [
                'berkas' => tracerBerkas("nim,status,tanggal_survei\n220501,studi_lanjut,2026-10-01\n999999,bekerja,2026-10-01\n"),
                'sumber' => 'open_campus',
            ])
            ->assertRedirect();

        expect($a->fresh()->status_pekerjaan)->toBe(StatusPekerjaanAlumni::BelumDitelusuri)
            ->and(Storage::disk('local')->allFiles('impor-status-pekerjaan'))->toHaveCount(1);

        $this->actingAs($this->baak, 'staff')->get(route('admin.lulusan'))
            ->assertOk()
            ->assertSee('1 baris akan diterapkan')
            ->assertSee('NIM tidak dikenal.');

        $this->actingAs($this->baak, 'staff')->post(route('admin.lulusan.impor.terapkan'))
            ->assertSessionHas('sukses', '1 status pekerjaan diperbarui dari Open Campus.');

        expect($a->fresh()->status_pekerjaan)->toBe(StatusPekerjaanAlumni::StudiLanjut)
            ->and(Storage::disk('local')->allFiles('impor-status-pekerjaan'))->toBe([]);

        $this->actingAs($this->pimpinan, 'staff')
            ->post(route('admin.lulusan.impor'), ['berkas' => tracerBerkas("nim,status\n"), 'sumber' => 'open_campus'])
            ->assertForbidden();
    });
});

describe('Campus Bridge sesudah status terisi', function () {
    beforeEach(function () {
        $scopes = ['students.read', 'lecturers.read', 'classes.read', 'activities.read', 'graduates.read'];
        $konsumen = BridgeConsumer::create(['nama' => 'Open Campus', 'slug' => 'tracer-'.Str::random(6), 'scopes' => $scopes, 'is_active' => true]);
        $this->header = ['Authorization' => 'Bearer '.$konsumen->createToken('uji', $scopes)->plainTextToken, 'Accept' => 'application/json'];
    });

    it('menghitung lulusan per status, yang sudah ditelusuri, sumbernya, dan masa tunggunya di iku_2026', function () {
        foreach (['220601', '220602', '220603', '220604'] as $nim) {
            tracerLulusan([], ['nim' => $nim]);
        }

        $this->impor->terapkan(
            "nim,status,masa_tunggu_bulan,tanggal_survei\n220601,bekerja,2,2026-10-01\n220602,wirausaha,9,2026-10-01\n220603,mencari kerja,,2026-10-01\n",
            SumberStatusPekerjaan::TracerNasional,
        );

        $iku2 = $this->getJson('/api/bridge/v1/iku-data', $this->header)->assertOk()->json('data.iku_2026.iku_2_lulusan');

        expect($iku2['total_lulusan'])->toBe(4)
            ->and($iku2['per_status_pekerjaan'])->toEqual(['bekerja' => 1, 'wirausaha' => 1, 'mencari_kerja' => 1, 'belum_ditelusuri' => 1])
            ->and($iku2['ditelusuri'])->toBe(3)
            ->and($iku2['per_sumber_status'])->toEqual(['tracer_nasional' => 3])
            ->and($iku2['masa_tunggu_bulan'])->toBe(['0-6' => 1, '7-12' => 1, '13+' => 0, 'tidak_diketahui' => 0]);

        // The frozen legacy key still reads the stored value as it always has.
        $lama = $this->getJson('/api/bridge/v1/iku-data', $this->header)->json('data.iku_1_lulusan.per_status_pekerjaan');
        expect($lama)->toEqual(['bekerja' => 1, 'wirausaha' => 1, 'mencari_kerja' => 1, 'belum' => 1]);
    });

    it('menyertakan status, sumber, dan tanggal survei pada GET /graduates', function () {
        tracerLulusan([], ['nim' => '220701']);
        $this->impor->terapkan("nim,status,instansi,tanggal_survei\n220701,bekerja,PT Demo,2026-10-05\n", SumberStatusPekerjaan::OpenCampus);

        $alumni = $this->getJson('/api/bridge/v1/graduates', $this->header)->assertOk()->json('data.0.alumni');

        expect($alumni)->toMatchArray([
            'status_pekerjaan' => 'bekerja',
            'instansi' => 'PT Demo',
            'status_pekerjaan_sumber' => 'open_campus',
            'status_pekerjaan_per' => '2026-10-05',
        ]);
    });
});

it('menyimpan surel pribadi dari profil mahasiswa, yang sebelumnya tidak punya kolom', function () {
    $m = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
    $m->assignRole('mahasiswa');

    $this->actingAs($m, 'mahasiswa')
        ->put(route('mahasiswa.profil.perbarui'), ['email_pribadi' => 'pribadi@demo.test'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($m->fresh()->email_pribadi)->toBe('pribadi@demo.test');
});
