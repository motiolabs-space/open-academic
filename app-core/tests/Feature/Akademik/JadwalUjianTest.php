<?php

declare(strict_types=1);

use App\DTOs\Akademik\BentrokJadwal;
use App\DTOs\Akademik\JadwalUjianData;
use App\DTOs\Akademik\PenyusunanUjianData;
use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JenisUjianKelas;
use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Enums\StatusJadwalUjian;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Gedung;
use App\Models\Akademik\JadwalUjian;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\Presensi;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Notifications\Akademik\JadwalUjianBerubah;
use App\Notifications\Akademik\JadwalUjianTerbit;
use App\Services\Akademik\JadwalUjianService;
use App\Services\Dokumen\CetakService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;

/**
 * Mid-term and final exam sessions (#34).
 *
 * The clash tests pin the *kind* of each clash and whether it blocks, not
 * merely that "something" was reported: a check that returns every clash as
 * blocking would pass a count-based test and still refuse what a registrar is
 * entitled to accept.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create();
    $this->kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id]);
    $this->gedung = Gedung::create(['kode' => 'GU', 'nama' => 'Gedung Ujian']);

    // A Monday well inside the term.
    $this->tanggal = $this->term->tanggal_mulai->copy()->addWeeks(8)->startOfWeek()->toDateString();

    $this->ujian = app(JadwalUjianService::class);
});

function ruangUjian(string $kode, int $kapasitas): Ruang
{
    return Ruang::create([
        'gedung_id' => test()->gedung->id,
        'kode' => $kode,
        'nama' => 'Ruang '.$kode,
        'kapasitas' => $kapasitas,
        'is_active' => true,
    ]);
}

function kelasUjian(?Dosen $dosen = null, ?Prodi $prodi = null, string $kode = 'A'): KelasKuliah
{
    $prodi ??= test()->prodi;
    $mk = MataKuliah::factory()->create(['prodi_id' => $prodi->id, 'sks' => 3]);

    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $prodi->id,
        'kode' => $kode,
        'sks' => 3,
        'kuota' => 60,
    ]);

    if ($dosen !== null) {
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu', 'porsi_sks' => 3]);
    }

    return $kelas->load('mataKuliah');
}

function mahasiswaUjian(string $nim): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create([
        'nim' => $nim,
        'prodi_id' => test()->prodi->id,
        'kurikulum_id' => test()->kurikulum->id,
        'status' => StudentStatus::Aktif,
    ]);

    $mahasiswa->assignRole('mahasiswa');

    return $mahasiswa;
}

/** Puts the student in the class through a study plan of the given status. */
function ikutkanUjian(Mahasiswa $mahasiswa, KelasKuliah $kelas, KrsStatus $status = KrsStatus::Disetujui): void
{
    $krs = Krs::firstOrCreate(
        ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => test()->term->id],
        ['semester_ke' => 1, 'status' => $status, 'total_sks' => 0, 'batas_sks' => 24],
    );

    KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 3]);
}

/** @param array<string, mixed> $ubah */
function dataUjian(array $ubah = []): JadwalUjianData
{
    return JadwalUjianData::fromArray($ubah + [
        'jenis' => 'uts',
        'tanggal' => test()->tanggal,
        'jam_mulai' => '08:00',
        'jam_selesai' => '10:00',
    ]);
}

function dosenUjian(): Dosen
{
    $dosen = Dosen::factory()->create();
    $dosen->assignRole('dosen');

    return $dosen;
}

function stafUjian(string $peran = 'baak'): Staff
{
    $staf = Staff::factory()->create();
    $staf->assignRole($peran);

    return $staf;
}

/** @param Collection<int, BentrokJadwal> $bentrok */
function jenisBentrokUjian($bentrok): array
{
    return $bentrok->map(fn (BentrokJadwal $b): string => $b->jenis.($b->menghalangi ? '!' : '?'))->sort()->values()->all();
}

function parameterUjian(array $ubah = []): PenyusunanUjianData
{
    return PenyusunanUjianData::fromArray($ubah + [
        'tahun_akademik_id' => test()->term->id,
        'jenis' => 'uts',
        'prodi_id' => test()->prodi->id,
        'tanggal_mulai' => test()->tanggal,
        'tanggal_selesai' => test()->tanggal,
        'sesi' => [['mulai' => '08:00', 'selesai' => '10:00'], ['mulai' => '10:30', 'selesai' => '12:30']],
    ]);
}

function hadirkanUjian(KelasKuliah $kelas, Mahasiswa $m, int $hadir, int $dari): void
{
    foreach (range(1, $dari) as $ke) {
        $p = PertemuanKelas::firstOrCreate(
            ['kelas_kuliah_id' => $kelas->id, 'pertemuan_ke' => $ke],
            ['tanggal' => test()->term->tanggal_mulai->copy()->addWeeks($ke), 'is_terlaksana' => true],
        );

        Presensi::create([
            'pertemuan_kelas_id' => $p->id,
            'mahasiswa_id' => $m->id,
            'status' => $ke <= $hadir ? AttendanceStatus::Hadir : AttendanceStatus::Alpa,
        ]);
    }
}

/** @return array<string, mixed> the data the card template was rendered with */
function dataKartuUjian(Krs $krs): array
{
    $data = [];
    View::composer('pdf.cetak.kartu-ujian', function ($view) use (&$data): void {
        $data = $view->getData();
    });

    app(CetakService::class)->kartuUjian($krs);

    return $data;
}

describe('menyimpan satu sesi', function () {
    it('menyimpan draf dengan ruang dan pengawas, kursi bawaan dari kapasitas ruang', function () {
        $r1 = ruangUjian('R1', 40);
        $r2 = ruangUjian('R2', 30);
        $dosen = dosenUjian();
        $staf = stafUjian();
        $kelas = kelasUjian();

        $hasil = $this->ujian->simpan($kelas, dataUjian([
            'ruang' => [['ruang_id' => $r1->id], ['ruang_id' => $r2->id, 'kursi' => 20]],
            'pengawas' => [['orang' => 'dosen:'.$dosen->id, 'ruang_id' => $r1->id], ['orang' => 'staff:'.$staf->id]],
        ]));

        $jadwal = $hasil['jadwal']->load('ruang', 'pengawas');

        expect($jadwal->status)->toBe(StatusJadwalUjian::Draf)
            ->and($jadwal->tahun_akademik_id)->toBe($this->term->id)
            ->and($jadwal->ruang->pluck('kursi')->all())->toBe([40, 20])
            ->and($jadwal->totalKursi())->toBe(60)
            ->and($jadwal->pengawas)->toHaveCount(2)
            ->and($jadwal->pengawas->firstWhere('dosen_id', $dosen->id)->jadwal_ujian_ruang_id)->toBe($jadwal->ruang->first()->id)
            ->and($jadwal->logAktivitas()->count())->toBeGreaterThan(0);
    });

    it('menolak sesi kedua dengan jenis yang sama untuk satu kelas', function () {
        $kelas = kelasUjian();
        $this->ujian->simpan($kelas, dataUjian());

        expect(fn () => $this->ujian->simpan($kelas, dataUjian(['jam_mulai' => '13:00', 'jam_selesai' => '15:00'])))
            ->toThrow(AturanAkademikException::class, 'sudah memiliki jadwal UTS');
    });

    it('menerima UTS dan UAS untuk kelas yang sama', function () {
        $kelas = kelasUjian();
        $this->ujian->simpan($kelas, dataUjian());
        $this->ujian->simpan($kelas, dataUjian(['jenis' => 'uas', 'tanggal' => now()->parse($this->tanggal)->addWeeks(8)->toDateString()]));

        expect($kelas->jadwalUjian()->count())->toBe(2);
    });

    it('menolak tanggal di luar masa semester', function () {
        expect(fn () => $this->ujian->simpan(kelasUjian(), dataUjian([
            'tanggal' => $this->term->tanggal_selesai->copy()->addDay()->toDateString(),
        ])))->toThrow(AturanAkademikException::class, 'di luar masa semester');
    });

    it('menolak jam selesai yang tidak sesudah jam mulai', function () {
        expect(fn () => $this->ujian->simpan(kelasUjian(), dataUjian(['jam_mulai' => '10:00', 'jam_selesai' => '10:00'])))
            ->toThrow(AturanAkademikException::class, 'Jam selesai');
    });

    it('menolak pengawas yang ditempatkan di ruang yang tidak dipakai sesi', function () {
        $r1 = ruangUjian('R1', 40);
        $lain = ruangUjian('R9', 40);
        $dosen = dosenUjian();

        expect(fn () => $this->ujian->simpan(kelasUjian(), dataUjian([
            'ruang' => [['ruang_id' => $r1->id]],
            'pengawas' => [['orang' => 'dosen:'.$dosen->id, 'ruang_id' => $lain->id]],
        ])))->toThrow(AturanAkademikException::class, 'tidak dipakai sesi ini');
    });

    it('menghapus draf tetapi tidak jadwal yang sudah terbit', function () {
        $r = ruangUjian('R1', 40);
        $kelas = kelasUjian();
        $jadwal = $this->ujian->simpan($kelas, dataUjian(['ruang' => [['ruang_id' => $r->id]]]))['jadwal'];

        $jadwal->update(['status' => StatusJadwalUjian::Terbit]);

        expect(fn () => $this->ujian->hapus($jadwal))->toThrow(AturanAkademikException::class, 'sudah terbit');

        $jadwal->update(['status' => StatusJadwalUjian::Draf]);
        $this->ujian->hapus($jadwal);

        expect(JadwalUjian::count())->toBe(0)
            ->and(JadwalUjian::withTrashed()->count())->toBe(1);
    });
});

describe('deteksi bentrok', function () {
    it('melaporkan ruang yang dipakai dua sesi bersamaan sebagai penghalang', function () {
        $r = ruangUjian('R1', 40);
        $this->ujian->simpan(kelasUjian(), dataUjian(['ruang' => [['ruang_id' => $r->id]]]));

        $bentrok = $this->ujian->periksaBentrok(kelasUjian(kode: 'B'), dataUjian([
            'jam_mulai' => '09:00', 'jam_selesai' => '11:00',
            'ruang' => [['ruang_id' => $r->id]],
        ]));

        expect(jenisBentrokUjian($bentrok))->toBe(['ruang!']);
    });

    it('melaporkan pengawas dosen maupun staf yang berada di dua sesi bersamaan', function () {
        $r1 = ruangUjian('R1', 40);
        $r2 = ruangUjian('R2', 40);
        $dosen = dosenUjian();
        $staf = stafUjian();

        $this->ujian->simpan(kelasUjian(), dataUjian([
            'ruang' => [['ruang_id' => $r1->id]],
            'pengawas' => [['orang' => 'dosen:'.$dosen->id], ['orang' => 'staff:'.$staf->id]],
        ]));

        $bentrok = $this->ujian->periksaBentrok(kelasUjian(kode: 'B'), dataUjian([
            'ruang' => [['ruang_id' => $r2->id]],
            'pengawas' => [['orang' => 'dosen:'.$dosen->id], ['orang' => 'staff:'.$staf->id]],
        ]));

        expect(jenisBentrokUjian($bentrok))->toBe(['pengawas!', 'pengawas!'])
            ->and($bentrok->pluck('pesan')->implode(' '))->toContain($dosen->nama)->toContain($staf->nama);
    });

    it('melaporkan mahasiswa dengan dua ujian bersamaan, dihitung dari KRS yang disetujui saja', function () {
        $r1 = ruangUjian('R1', 40);
        $r2 = ruangUjian('R2', 40);
        $a = kelasUjian();
        $b = kelasUjian(kode: 'B');

        $disetujui = mahasiswaUjian('2026001');
        ikutkanUjian($disetujui, $a);
        ikutkanUjian($disetujui, $b);

        // Masih draf: belum mengikuti kelas apa pun, jadi belum bisa bentrok.
        $draf = mahasiswaUjian('2026002');
        ikutkanUjian($draf, $a, KrsStatus::Draft);
        ikutkanUjian($draf, $b, KrsStatus::Draft);

        $this->ujian->simpan($a, dataUjian(['ruang' => [['ruang_id' => $r1->id]]]));

        $bentrok = $this->ujian->periksaBentrok($b, dataUjian(['ruang' => [['ruang_id' => $r2->id]]]));

        expect(jenisBentrokUjian($bentrok))->toBe(['mahasiswa!'])
            ->and($bentrok->first()->pesan)->toContain('1 mahasiswa')->toContain('2026001')->not->toContain('2026002');
    });

    it('tidak menganggap sesi yang bersambung atau berbeda hari sebagai bentrok', function () {
        $r = ruangUjian('R1', 40);
        $a = kelasUjian();
        $b = kelasUjian(kode: 'B');
        $m = mahasiswaUjian('2026001');
        ikutkanUjian($m, $a);
        ikutkanUjian($m, $b);

        $this->ujian->simpan($a, dataUjian(['ruang' => [['ruang_id' => $r->id]]]));

        $bersambung = $this->ujian->periksaBentrok($b, dataUjian([
            'jam_mulai' => '10:00', 'jam_selesai' => '12:00', 'ruang' => [['ruang_id' => $r->id]],
        ]));
        $besok = $this->ujian->periksaBentrok($b, dataUjian([
            'tanggal' => now()->parse($this->tanggal)->addDay()->toDateString(), 'ruang' => [['ruang_id' => $r->id]],
        ]));

        expect($bersambung)->toBeEmpty()->and($besok)->toBeEmpty();
    });

    it('menghalangi peserta tanpa kursi tetapi hanya memperingatkan kursi di atas kapasitas ruang', function () {
        $kecil = ruangUjian('R1', 2);
        $kelas = kelasUjian();
        foreach (['2026001', '2026002', '2026003'] as $nim) {
            ikutkanUjian(mahasiswaUjian($nim), $kelas);
        }

        $kurang = $this->ujian->periksaBentrok($kelas, dataUjian(['ruang' => [['ruang_id' => $kecil->id]]]));
        $dilebihkan = $this->ujian->periksaBentrok($kelas, dataUjian(['ruang' => [['ruang_id' => $kecil->id, 'kursi' => 3]]]));
        $tanpaRuang = $this->ujian->periksaBentrok($kelas, dataUjian());

        expect(jenisBentrokUjian($kurang))->toBe(['kursi!'])
            ->and($kurang->first()->pesan)->toContain('3 peserta')->toContain('2 kursi')
            ->and(jenisBentrokUjian($dilebihkan))->toBe(['kapasitas?'])
            ->and(jenisBentrokUjian($tanpaRuang))->toBe(['kursi!']);
    });

    it('melaporkan seluruh bentrok satu semester sekaligus, per sesi', function () {
        $r = ruangUjian('R1', 40);
        $dosen = dosenUjian();
        $a = kelasUjian();
        $b = kelasUjian(kode: 'B');
        $c = kelasUjian(kode: 'C');
        $m = mahasiswaUjian('2026001');
        ikutkanUjian($m, $a);
        ikutkanUjian($m, $b);

        $ja = $this->ujian->simpan($a, dataUjian(['ruang' => [['ruang_id' => $r->id]], 'pengawas' => [['orang' => 'dosen:'.$dosen->id]]]))['jadwal'];
        $jb = $this->ujian->simpan($b, dataUjian(['ruang' => [['ruang_id' => $r->id]]]))['jadwal'];
        $jc = $this->ujian->simpan($c, dataUjian(['ruang' => [['ruang_id' => ruangUjian('R2', 40)->id]], 'pengawas' => [['orang' => 'dosen:'.$dosen->id]]]))['jadwal'];

        $laporan = $this->ujian->laporanBentrok($this->term);

        expect(array_keys($laporan))->toEqualCanonicalizing([$ja->id, $jb->id, $jc->id])
            ->and(jenisBentrokUjian($laporan[$ja->id]))->toBe(['mahasiswa!', 'pengawas!', 'ruang!'])
            ->and(jenisBentrokUjian($laporan[$jb->id]))->toBe(['mahasiswa!', 'ruang!'])
            ->and(jenisBentrokUjian($laporan[$jc->id]))->toBe(['pengawas!']);

        // Cakupan per jenis: tidak ada UAS, jadi tidak ada laporan UAS.
        expect($this->ujian->laporanBentrok($this->term, JenisUjianKelas::Uas))->toBe([]);
    });
});

describe('menerbitkan', function () {
    it('tidak menerbitkan apa pun selama satu draf masih bentrok', function () {
        $r = ruangUjian('R1', 40);
        $this->ujian->simpan(kelasUjian(), dataUjian(['ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->simpan(kelasUjian(kode: 'B'), dataUjian(['ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->simpan(kelasUjian(kode: 'C'), dataUjian(['tanggal' => now()->parse($this->tanggal)->addDay()->toDateString(), 'ruang' => [['ruang_id' => $r->id]]]));

        expect(fn () => $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts))
            ->toThrow(AturanAkademikException::class, 'Tidak ada yang diterbitkan');

        expect(JadwalUjian::terbit()->count())->toBe(0);
    });

    it('menerbitkan seluruh draf dan memberi tahu tiap orang sekali dengan jumlah sesinya', function () {
        Notification::fake();

        $dosen = dosenUjian();
        $pengawas = stafUjian();
        $a = kelasUjian($dosen);
        $b = kelasUjian($dosen, kode: 'B');
        $m = mahasiswaUjian('2026001');
        ikutkanUjian($m, $a);
        ikutkanUjian($m, $b);

        $this->ujian->simpan($a, dataUjian(['ruang' => [['ruang_id' => ruangUjian('R1', 40)->id]], 'pengawas' => [['orang' => 'staff:'.$pengawas->id]]]));
        $this->ujian->simpan($b, dataUjian(['jam_mulai' => '13:00', 'jam_selesai' => '15:00', 'ruang' => [['ruang_id' => ruangUjian('R2', 40)->id]]]));

        expect($this->ujian->terbitkan($this->term, JenisUjianKelas::Uts))->toBe(2);

        expect(JadwalUjian::terbit()->whereNotNull('diterbitkan_at')->count())->toBe(2);

        Notification::assertSentToTimes($m, JadwalUjianTerbit::class, 1);
        Notification::assertSentTo($m, JadwalUjianTerbit::class, fn (JadwalUjianTerbit $n): bool => $n->jumlah === 2);
        Notification::assertSentTo($dosen, JadwalUjianTerbit::class, fn (JadwalUjianTerbit $n): bool => $n->jumlah === 2);
        Notification::assertSentTo($pengawas, JadwalUjianTerbit::class, fn (JadwalUjianTerbit $n): bool => $n->jumlah === 1);
    });

    it('tidak terhalang oleh draf di luar cakupan yang diterbitkan', function () {
        $r = ruangUjian('R1', 40);
        $prodiLain = Prodi::factory()->create();

        $this->ujian->simpan(kelasUjian(), dataUjian(['ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->simpan(kelasUjian(prodi: $prodiLain), dataUjian(['ruang' => [['ruang_id' => $r->id]]]));

        expect($this->ujian->terbitkan($this->term, JenisUjianKelas::Uts, $this->prodi->id))->toBe(1);

        // Yang datang kemudian berhadapan dengan yang sudah terbit.
        expect(fn () => $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts, $prodiLain->id))
            ->toThrow(AturanAkademikException::class, 'ruang R1');
    });

    it('menolak bila tidak ada draf dalam cakupan', function () {
        expect(fn () => $this->ujian->terbitkan($this->term, JenisUjianKelas::Uas))
            ->toThrow(AturanAkademikException::class, 'Tidak ada draf');
    });
});

describe('mengubah jadwal yang sudah terbit', function () {
    it('menolak perubahan yang menimbulkan bentrok', function () {
        $r1 = ruangUjian('R1', 40);
        $r2 = ruangUjian('R2', 40);
        $this->ujian->simpan(kelasUjian(), dataUjian(['ruang' => [['ruang_id' => $r1->id]]]));
        $b = $this->ujian->simpan(kelasUjian(kode: 'B'), dataUjian(['ruang' => [['ruang_id' => $r2->id]]]))['jadwal'];
        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);

        expect(fn () => $this->ujian->perbarui($b->refresh(), dataUjian(['ruang' => [['ruang_id' => $r1->id]]])))
            ->toThrow(AturanAkademikException::class, 'tidak dapat diubah menjadi bentrok');
    });

    it('memberi tahu peserta, pengampu, pengawas baru, dan pengawas yang dilepas', function () {
        $r = ruangUjian('R1', 40);
        $pengampu = dosenUjian();
        $lama = dosenUjian();
        $baru = stafUjian();
        $kelas = kelasUjian($pengampu);
        $m = mahasiswaUjian('2026001');
        ikutkanUjian($m, $kelas);

        $jadwal = $this->ujian->simpan($kelas, dataUjian(['ruang' => [['ruang_id' => $r->id]], 'pengawas' => [['orang' => 'dosen:'.$lama->id]]]))['jadwal'];
        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);

        Notification::fake();

        $this->ujian->perbarui($jadwal->refresh(), dataUjian([
            'jam_mulai' => '13:00', 'jam_selesai' => '15:00',
            'ruang' => [['ruang_id' => $r->id]],
            'pengawas' => [['orang' => 'staff:'.$baru->id]],
        ]));

        foreach ([$m, $pengampu, $lama, $baru] as $orang) {
            Notification::assertSentTo($orang, JadwalUjianBerubah::class);
        }

        expect(JadwalUjian::jam($jadwal->refresh()->jam_mulai))->toBe('13:00');
    });

    it('tidak mengirim apa pun bila tidak ada yang berubah', function () {
        $r = ruangUjian('R1', 40);
        $kelas = kelasUjian();
        ikutkanUjian(mahasiswaUjian('2026001'), $kelas);
        $jadwal = $this->ujian->simpan($kelas, dataUjian(['ruang' => [['ruang_id' => $r->id]]]))['jadwal'];
        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);

        Notification::fake();

        $this->ujian->perbarui($jadwal->refresh(), dataUjian(['ruang' => [['ruang_id' => $r->id]], 'catatan' => 'Bawa kalkulator']));

        Notification::assertNothingSent();
    });

    it('tidak mengizinkan jenis ujian diubah', function () {
        $jadwal = $this->ujian->simpan(kelasUjian(), dataUjian())['jadwal'];

        expect(fn () => $this->ujian->perbarui($jadwal, dataUjian(['jenis' => 'uas'])))
            ->toThrow(AturanAkademikException::class, 'Jenis ujian tidak dapat diubah');
    });
});

describe('penyusunan massal', function () {

    it('memisahkan kelas yang berbagi mahasiswa ke sesi berbeda', function () {
        ruangUjian('R1', 40);
        ruangUjian('R2', 40);
        $a = kelasUjian();
        $b = kelasUjian(kode: 'B');
        $m = mahasiswaUjian('2026001');
        ikutkanUjian($m, $a);
        ikutkanUjian($m, $b);

        $rencana = $this->ujian->susun(parameterUjian());

        expect($rencana->usulan)->toHaveCount(2)
            ->and($rencana->usulan->pluck('jamMulai')->sort()->values()->all())->toBe(['08:00', '10:30'])
            ->and(JadwalUjian::count())->toBe(0);
    });

    it('menghormati kapasitas: satu ruang bila cukup, dibagi ke ruang terbesar bila tidak', function () {
        ruangUjian('KECIL', 2);
        ruangUjian('SEDANG', 3);
        ruangUjian('BESAR', 4);

        $kecil = kelasUjian(kode: 'A');
        ikutkanUjian(mahasiswaUjian('2026001'), $kecil);
        ikutkanUjian(mahasiswaUjian('2026002'), $kecil);

        $besar = kelasUjian(kode: 'B');
        foreach (range(3, 8) as $i) {
            ikutkanUjian(mahasiswaUjian('20260'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)), $besar);
        }

        $rencana = $this->ujian->susun(parameterUjian(['sesi' => [['mulai' => '08:00', 'selesai' => '10:00']]]));
        $perKelas = $rencana->usulan->keyBy(fn ($u) => $u->kelas->id);

        // Kelas terbesar memilih lebih dulu: BESAR + SEDANG (7 kursi) untuk 6
        // peserta; kelas kecil mendapat ruang terkecil yang tersisa.
        expect(collect($perKelas[$besar->id]->ruang)->map(fn ($r) => $r['ruang']->kode)->all())->toBe(['BESAR', 'SEDANG'])
            ->and($perKelas[$besar->id]->totalKursi())->toBeGreaterThanOrEqual(6)
            ->and(collect($perKelas[$kecil->id]->ruang)->map(fn ($r) => $r['ruang']->kode)->all())->toBe(['KECIL']);
    });

    it('melaporkan kelas yang tidak dapat ditempatkan beserta alasannya', function () {
        ruangUjian('R1', 40);
        $a = kelasUjian();
        $b = kelasUjian(kode: 'B');
        $tanpaPeserta = kelasUjian(kode: 'C');
        $m = mahasiswaUjian('2026001');
        ikutkanUjian($m, $a);
        ikutkanUjian($m, $b);

        $rencana = $this->ujian->susun(parameterUjian(['sesi' => [['mulai' => '08:00', 'selesai' => '10:00']]]));

        expect($rencana->usulan)->toHaveCount(1)
            ->and($rencana->tidakTerjadwal)->toHaveCount(2)
            ->and($rencana->tidakTerjadwal->firstWhere(fn ($t) => $t['kelas']->id === $tanpaPeserta->id)['alasan'])->toContain('KRS disetujui');
    });

    it('menjadikan pengampu pengawas, dan menyimpan rencana sebagai draf', function () {
        ruangUjian('R1', 40);
        $dosen = dosenUjian();
        $kelas = kelasUjian($dosen);
        ikutkanUjian(mahasiswaUjian('2026001'), $kelas);

        $tersimpan = $this->ujian->simpanRencana(parameterUjian());

        expect($tersimpan)->toHaveCount(1)
            ->and($tersimpan->first()->status)->toBe(StatusJadwalUjian::Draf)
            ->and($tersimpan->first()->pengawas()->value('dosen_id'))->toBe($dosen->id);

        // Kelas yang sudah berjadwal tidak disusun ulang.
        expect($this->ujian->susun(parameterUjian())->usulan)->toBeEmpty();
    });

    it('memperhitungkan sesi yang sudah ada', function () {
        $r = ruangUjian('R1', 40);
        $a = kelasUjian();
        $b = kelasUjian(kode: 'B');
        $m = mahasiswaUjian('2026001');
        ikutkanUjian($m, $a);
        ikutkanUjian($m, $b);
        $this->ujian->simpan($a, dataUjian(['ruang' => [['ruang_id' => $r->id]]]));

        $rencana = $this->ujian->susun(parameterUjian());

        expect($rencana->usulan)->toHaveCount(1)
            ->and($rencana->usulan->first()->jamMulai)->toBe('10:30');
    });
});

describe('peserta dan kelayakan', function () {

    it('membagi peserta ke ruang menurut NIM dan jumlah kursi', function () {
        $r1 = ruangUjian('R1', 2);
        $r2 = ruangUjian('R2', 1);
        $kelas = kelasUjian();
        foreach (['2026004', '2026001', '2026003', '2026002'] as $nim) {
            ikutkanUjian(mahasiswaUjian($nim), $kelas);
        }

        $jadwal = $this->ujian->simpan($kelas, dataUjian(['ruang' => [['ruang_id' => $r1->id], ['ruang_id' => $r2->id]]]))['jadwal'];

        $peserta = $this->ujian->peserta($jadwal)->mapWithKeys(fn ($p) => [$p->mahasiswa->nim => $p->ruang?->kode]);

        expect($peserta->all())->toBe(['2026001' => 'R1', '2026002' => 'R1', '2026003' => 'R2', '2026004' => null]);
    });

    it('menandai peserta UAS di bawah ambang kehadiran sebagai tidak layak bila kampus menahannya', function () {
        config(['dokumen.kartu_ujian.tahan_bila_kehadiran_kurang' => true, 'dokumen.kartu_ujian.tahan_bila_menunggak' => false]);

        $kelas = kelasUjian();
        $rajin = mahasiswaUjian('2026001');
        $absen = mahasiswaUjian('2026002');
        ikutkanUjian($rajin, $kelas);
        ikutkanUjian($absen, $kelas);
        hadirkanUjian($kelas, $rajin, 4, 4);
        hadirkanUjian($kelas, $absen, 1, 4);

        $r = ruangUjian('R1', 40);
        $uas = $this->ujian->simpan($kelas, dataUjian(['jenis' => 'uas', 'ruang' => [['ruang_id' => $r->id]]]))['jadwal'];
        $uts = $this->ujian->simpan($kelas, dataUjian(['jam_mulai' => '13:00', 'jam_selesai' => '15:00', 'ruang' => [['ruang_id' => $r->id]]]))['jadwal'];

        $pesertaUas = $this->ujian->peserta($uas)->keyBy(fn ($p) => $p->mahasiswa->nim);
        $pesertaUts = $this->ujian->peserta($uts)->keyBy(fn ($p) => $p->mahasiswa->nim);

        expect($pesertaUas['2026001']->layak)->toBeTrue()
            ->and($pesertaUas['2026002']->layak)->toBeFalse()
            ->and($pesertaUas['2026002']->persenKehadiran)->toBe(25.0)
            ->and($pesertaUas['2026002']->alasan[0])->toContain('Kehadiran')
            // UTS tidak menimbang kehadiran: belum ada satu semester untuk diukur.
            ->and($pesertaUts['2026002']->layak)->toBeTrue()
            ->and($pesertaUts['2026002']->persenKehadiran)->toBeNull();
    });

    it('menampilkan kehadiran tanpa menahan bila kampus tidak memilih menahan', function () {
        config(['dokumen.kartu_ujian.tahan_bila_kehadiran_kurang' => false, 'dokumen.kartu_ujian.tahan_bila_menunggak' => false]);

        $kelas = kelasUjian();
        $absen = mahasiswaUjian('2026002');
        ikutkanUjian($absen, $kelas);
        hadirkanUjian($kelas, $absen, 1, 4);

        $uas = $this->ujian->simpan($kelas, dataUjian(['jenis' => 'uas', 'ruang' => [['ruang_id' => ruangUjian('R1', 40)->id]]]))['jadwal'];
        $p = $this->ujian->peserta($uas)->first();

        expect($p->memenuhiKehadiran)->toBeFalse()->and($p->layak)->toBeTrue();
    });

    it('menandai penunggak bila kampus menahan kartu karena tunggakan', function () {
        config(['dokumen.kartu_ujian.tahan_bila_menunggak' => true]);

        $kelas = kelasUjian();
        $m = mahasiswaUjian('2026001');
        ikutkanUjian($m, $kelas);

        Tagihan::create([
            'nomor' => 'INV-UJIAN-1', 'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id,
            'keterangan' => 'UKT', 'total' => 1_000_000, 'terbayar' => 0,
            'status' => InvoiceStatus::BelumBayar, 'jatuh_tempo' => now()->addDays(10),
        ]);

        $jadwal = $this->ujian->simpan($kelas, dataUjian(['ruang' => [['ruang_id' => ruangUjian('R1', 40)->id]]]))['jadwal'];
        $p = $this->ujian->peserta($jadwal)->first();

        expect($p->tunggakan)->toBe(1_000_000)->and($p->layak)->toBeFalse();
    });
});

describe('membaca jadwal terbit', function () {
    it('memberi mahasiswa hanya sesi terbit dari KRS-nya yang disetujui', function () {
        $r = ruangUjian('R1', 40);
        $m = mahasiswaUjian('2026001');
        $diikuti = kelasUjian();
        $lain = kelasUjian(kode: 'B');
        ikutkanUjian($m, $diikuti);

        $this->ujian->simpan($diikuti, dataUjian(['ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->simpan($lain, dataUjian(['jam_mulai' => '13:00', 'jam_selesai' => '15:00', 'ruang' => [['ruang_id' => $r->id]]]));

        expect($this->ujian->untukMahasiswa($m, $this->term))->toBeEmpty();

        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);

        expect($this->ujian->untukMahasiswa($m, $this->term)->pluck('kelas_kuliah_id')->all())->toBe([$diikuti->id]);
    });

    it('memberi dosen sesi yang diampu dan yang diawasinya', function () {
        $r = ruangUjian('R1', 40);
        $dosen = dosenUjian();
        $diampu = kelasUjian($dosen);
        $diawasi = kelasUjian(kode: 'B');
        $asing = kelasUjian(kode: 'C');

        $this->ujian->simpan($diampu, dataUjian(['ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->simpan($diawasi, dataUjian(['jam_mulai' => '13:00', 'jam_selesai' => '15:00', 'ruang' => [['ruang_id' => $r->id]], 'pengawas' => [['orang' => 'dosen:'.$dosen->id]]]));
        $this->ujian->simpan($asing, dataUjian(['jam_mulai' => '15:30', 'jam_selesai' => '17:00', 'ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);

        expect($this->ujian->untukDosen($dosen, $this->term)->pluck('kelas_kuliah_id')->sort()->values()->all())
            ->toBe([$diampu->id, $diawasi->id]);
    });

    it('menyebut jenis terbit terakhir untuk kartu: UAS mengalahkan UTS', function () {
        $r = ruangUjian('R1', 40);
        $kelas = kelasUjian();

        expect($this->ujian->jenisTerbit($this->term))->toBeNull();

        $this->ujian->simpan($kelas, dataUjian(['ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);
        expect($this->ujian->jenisTerbit($this->term))->toBe(JenisUjianKelas::Uts);

        // UAS yang masih draf belum mengubah kartu.
        $this->ujian->simpan($kelas, dataUjian(['jenis' => 'uas', 'tanggal' => now()->parse($this->tanggal)->addWeeks(6)->toDateString(), 'ruang' => [['ruang_id' => $r->id]]]));
        expect($this->ujian->jenisTerbit($this->term))->toBe(JenisUjianKelas::Uts);

        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uas);
        expect($this->ujian->jenisTerbit($this->term))->toBe(JenisUjianKelas::Uas);
    });

    it('menyediakan waktu mulai dan selesai sebagai peristiwa bertanggal', function () {
        $jadwal = $this->ujian->simpan(kelasUjian(), dataUjian())['jadwal'];

        expect($jadwal->mulai()->format('Y-m-d H:i'))->toBe($this->tanggal.' 08:00')
            ->and($jadwal->selesai()->format('H:i'))->toBe('10:00')
            ->and(JadwalUjian::dalamRentang($this->tanggal, $this->tanggal)->count())->toBe(1)
            ->and(JadwalUjian::dalamRentang(now()->parse($this->tanggal)->addDay(), now()->parse($this->tanggal)->addDays(2))->count())->toBe(0);
    });
});

describe('kartu ujian', function () {

    beforeEach(function () {
        config(['dokumen.kartu_ujian.tahan_bila_menunggak' => false]);

        $this->m = mahasiswaUjian('2026001');
        $this->kelasKartu = kelasUjian();
        ikutkanUjian($this->m, $this->kelasKartu);
        $this->kelasKartu->jadwal()->create(['hari' => 1, 'jam_mulai' => '07:30', 'jam_selesai' => '09:10']);
        $this->krsKartu = Krs::where('mahasiswa_id', $this->m->id)->firstOrFail();
    });

    it('memakai jadwal kuliah selama belum ada jadwal ujian yang terbit', function () {
        // Draf tidak dihitung: kartu tidak boleh mencetak waktu yang belum diumumkan.
        $this->ujian->simpan($this->kelasKartu, dataUjian(['ruang' => [['ruang_id' => ruangUjian('R1', 40)->id]]]));

        $data = dataKartuUjian($this->krsKartu);

        expect($data['jenis'])->toBeNull()
            ->and($data['baris']->first()['jadwal']->jam_mulai)->toStartWith('07:30');
    });

    it('memakai jadwal ujian terbit beserta ruang duduk mahasiswa', function () {
        $r = ruangUjian('R1', 40);
        $this->ujian->simpan($this->kelasKartu, dataUjian(['ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);

        $data = dataKartuUjian($this->krsKartu);
        $baris = $data['baris']->first();

        expect($data['jenis'])->toBe(JenisUjianKelas::Uts)
            ->and($data['subjudul'])->toContain('Ujian Tengah Semester')
            ->and($baris['ujian']->tanggal->toDateString())->toBe($this->tanggal)
            ->and($baris['ruang']->id)->toBe($r->id);

        $this->actingAs($this->m, 'mahasiswa')
            ->get('/mahasiswa/kartu-ujian')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('menahan baris UAS karena kehadiran, tetapi tidak baris UTS', function () {
        config(['dokumen.kartu_ujian.tahan_bila_kehadiran_kurang' => true]);
        hadirkanUjian($this->kelasKartu, $this->m, 1, 4);

        $r = ruangUjian('R1', 40);
        $this->ujian->simpan($this->kelasKartu, dataUjian(['ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);

        expect(dataKartuUjian($this->krsKartu)['baris']->first()['layak'])->toBeTrue();

        $this->ujian->simpan($this->kelasKartu, dataUjian(['jenis' => 'uas', 'tanggal' => now()->parse($this->tanggal)->addWeeks(6)->toDateString(), 'ruang' => [['ruang_id' => $r->id]]]));
        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uas);

        expect(dataKartuUjian($this->krsKartu)['baris']->first()['layak'])->toBeFalse();
    });
});

describe('portal dan otorisasi', function () {
    it('membuka layar admin, menyimpan lewat formulir, dan menerbitkan', function () {
        Notification::fake();

        $staf = stafUjian();
        $r = ruangUjian('R1', 40);
        $kelas = kelasUjian();

        $this->actingAs($staf, 'staff')->get('/admin/jadwal-ujian')->assertOk();
        $this->actingAs($staf, 'staff')->get('/admin/jadwal-ujian/baru')->assertOk();

        $this->actingAs($staf, 'staff')->post('/admin/jadwal-ujian', [
            'kelas_kuliah_id' => $kelas->uuid,
            'jenis' => 'uts',
            'tanggal' => $this->tanggal,
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruang' => [['ruang_id' => $r->id, 'kursi' => ''], ['ruang_id' => '', 'kursi' => '']],
            'pengawas' => [['orang' => 'staff:'.$staf->id, 'ruang_id' => $r->id]],
        ])->assertRedirect();

        $jadwal = JadwalUjian::firstOrFail();

        $this->actingAs($staf, 'staff')->get('/admin/jadwal-ujian/'.$jadwal->uuid)->assertOk()->assertSee($kelas->mataKuliah->nama);
        $this->actingAs($staf, 'staff')->get('/admin/jadwal-ujian/'.$jadwal->uuid.'/ubah')->assertOk();

        $this->actingAs($staf, 'staff')->post('/admin/jadwal-ujian/terbitkan', [
            'tahun_akademik_id' => $this->term->id,
            'jenis' => 'uts',
        ])->assertRedirect()->assertSessionHas('sukses');

        expect($jadwal->refresh()->sudahTerbit())->toBeTrue();
    });

    it('menyusun lewat pratinjau lalu menyimpan sebagai draf', function () {
        $staf = stafUjian();
        ruangUjian('R1', 40);
        ikutkanUjian(mahasiswaUjian('2026001'), kelasUjian());

        $parameter = [
            'tahun_akademik_id' => $this->term->id,
            'jenis' => 'uts',
            'prodi_id' => $this->prodi->id,
            'tanggal_mulai' => $this->tanggal,
            'tanggal_selesai' => $this->tanggal,
            'sesi' => [['mulai' => '08:00', 'selesai' => '10:00']],
            'sertakan_sabtu' => 0,
            'pengampu_mengawas' => 1,
        ];

        $this->actingAs($staf, 'staff')->get('/admin/jadwal-ujian/susun')->assertOk();
        $this->actingAs($staf, 'staff')->get('/admin/jadwal-ujian/susun/pratinjau?'.http_build_query($parameter))
            ->assertOk()
            ->assertSee(__('jadwal_ujian.aksi.simpan_draf'));

        expect(JadwalUjian::count())->toBe(0);

        $this->actingAs($staf, 'staff')->post('/admin/jadwal-ujian/susun', $parameter)->assertRedirect();

        expect(JadwalUjian::count())->toBe(1);
    });

    it('menolak staf tanpa izin kelas', function () {
        $staf = stafUjian('keuangan');
        $jadwal = $this->ujian->simpan(kelasUjian(), dataUjian())['jadwal'];

        $this->actingAs($staf, 'staff')->get('/admin/jadwal-ujian')->assertForbidden();
        $this->actingAs($staf, 'staff')->get('/admin/jadwal-ujian/'.$jadwal->uuid)->assertForbidden();
        $this->actingAs($staf, 'staff')->delete('/admin/jadwal-ujian/'.$jadwal->uuid)->assertForbidden();
    });

    it('memperlihatkan jadwal terbit kepada mahasiswa pesertanya saja', function () {
        $r = ruangUjian('R1', 40);
        $peserta = mahasiswaUjian('2026001');
        $bukan = mahasiswaUjian('2026002');
        $kelas = kelasUjian();
        ikutkanUjian($peserta, $kelas);

        $jadwal = $this->ujian->simpan($kelas, dataUjian(['ruang' => [['ruang_id' => $r->id]]]))['jadwal'];

        // Draf: tidak terlihat oleh siapa pun di luar BAAK.
        expect($peserta->can('view', $jadwal))->toBeFalse();
        $this->actingAs($peserta, 'mahasiswa')->get('/mahasiswa/jadwal-ujian')->assertOk()->assertDontSee($kelas->mataKuliah->nama);

        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);
        $jadwal->refresh();

        expect($peserta->can('view', $jadwal))->toBeTrue()
            ->and($bukan->can('view', $jadwal))->toBeFalse();

        $this->actingAs($peserta, 'mahasiswa')->get('/mahasiswa/jadwal-ujian')->assertOk()
            ->assertSee($kelas->mataKuliah->nama)
            ->assertSee('R1');

        $this->actingAs($peserta, 'mahasiswa')->get('/admin/jadwal-ujian')->assertRedirect();
    });

    it('memperlihatkan kepada dosen sesi yang diawasinya, bukan sesi asing', function () {
        $r = ruangUjian('R1', 40);
        $dosen = dosenUjian();
        $asing = dosenUjian();
        $kelas = kelasUjian();

        $jadwal = $this->ujian->simpan($kelas, dataUjian(['ruang' => [['ruang_id' => $r->id]], 'pengawas' => [['orang' => 'dosen:'.$dosen->id, 'ruang_id' => $r->id]]]))['jadwal'];

        expect($dosen->can('view', $jadwal))->toBeFalse();

        $this->ujian->terbitkan($this->term, JenisUjianKelas::Uts);
        $jadwal->refresh();

        expect($dosen->can('view', $jadwal))->toBeTrue()
            ->and($asing->can('view', $jadwal))->toBeFalse()
            ->and($dosen->can('update', $jadwal))->toBeFalse();

        $this->actingAs($dosen, 'dosen')->get('/dosen/jadwal-ujian')->assertOk()->assertSee($kelas->mataKuliah->nama);
        $this->actingAs($asing, 'dosen')->get('/dosen/jadwal-ujian')->assertOk()->assertDontSee($kelas->mataKuliah->nama);
    });
});
