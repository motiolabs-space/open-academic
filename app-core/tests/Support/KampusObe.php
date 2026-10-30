<?php

declare(strict_types=1);

namespace Tests\Support;

use App\DTOs\Akademik\RumusanCpmkData;
use App\Enums\SemesterType;
use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\NilaiKomponen;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\ProdiCpl;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Akademik\CpmkService;

/**
 * A small campus for the OBE tests (ADR-038): one programme, one curriculum,
 * one course with a published CPMK set, one class with three components.
 *
 * The set has the shape the arithmetic tests reason about:
 *
 *   CPMK-1 → CPL-A 60          Sub 1.1 (50) · Sub 1.2 (50)
 *   CPMK-2 → CPL-A 40, CPL-B 50 Sub 2.1 (40) · Sub 2.2 (60)
 *   CPMK-3 → CPL-B 50          no sub-CPMK
 *
 * Programme code 57201 sits outside ProdiFactory's 80000–89999 range, so a
 * factory-made second programme can never collide with it.
 */
final class KampusObe
{
    public TahunAkademik $term;

    public Prodi $prodi;

    public Kurikulum $kurikulum;

    public MataKuliah $mk;

    public ProdiCpl $cplA;

    public ProdiCpl $cplB;

    public Dosen $dosen;

    public Staff $staf;

    public KelasKuliah $kelas;

    public CpmkVersi $versi;

    /** @var array<string, KomponenNilai> Tugas, UTS, UAS */
    public array $komponen = [];

    public static function bangun(bool $terbitkan = true): self
    {
        $k = new self;

        $k->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->create([
            'is_active' => true,
            'nilai_mulai' => now()->subMonth(),
            'nilai_selesai' => now()->addMonth(),
        ]);

        $k->prodi = Prodi::factory()->create(['kode' => '57201']);
        $k->kurikulum = Kurikulum::factory()->create(['prodi_id' => $k->prodi->id, 'kode' => 'KUR2024', 'tahun_mulai' => 2024]);
        $k->mk = MataKuliah::factory()->create(['prodi_id' => $k->prodi->id, 'sks' => 3, 'kode' => 'OBE101']);
        $k->kurikulum->mataKuliah()->attach($k->mk->id, ['semester' => 1, 'jenis' => 'wajib']);

        $k->cplA = ProdiCpl::create(['prodi_id' => $k->prodi->id, 'kode' => 'CPL-A', 'kategori' => 'pengetahuan', 'deskripsi' => 'Menguasai konsep.']);
        $k->cplB = ProdiCpl::create(['prodi_id' => $k->prodi->id, 'kode' => 'CPL-B', 'kategori' => 'keterampilan_khusus', 'deskripsi' => 'Mampu merancang.']);

        $k->dosen = Dosen::factory()->create(['prodi_id' => $k->prodi->id, 'is_active' => true]);
        $k->dosen->assignRole('dosen');

        $k->staf = Staff::factory()->create();
        $k->staf->assignRole('baak');

        $k->kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $k->term->id,
            'mata_kuliah_id' => $k->mk->id,
            'prodi_id' => $k->prodi->id,
            'sks' => 3,
        ]);
        $k->kelas->dosen()->attach($k->dosen->id, ['peran' => 'pengampu']);

        foreach ([['Tugas', 30, 'tugas'], ['UTS', 30, 'uts'], ['UAS', 40, 'uas']] as $i => [$nama, $bobot, $jenis]) {
            $k->komponen[$nama] = KomponenNilai::create([
                'kelas_kuliah_id' => $k->kelas->id,
                'nama' => $nama,
                'bobot' => $bobot,
                'jenis_evaluasi' => $jenis,
                'urutan' => $i,
            ]);
        }

        $service = app(CpmkService::class);
        $k->versi = $service->draf($k->kurikulum, $k->mk, $k->staf);
        $service->simpan($k->versi, self::rumusanBaku($k->cplA, $k->cplB));

        if ($terbitkan) {
            $k->versi = $service->terbitkan($k->versi->fresh(), $k->staf);
        }

        $k->versi->load(CpmkVersi::MUAT);

        return $k;
    }

    public static function rumusanBaku(ProdiCpl $a, ProdiCpl $b): RumusanCpmkData
    {
        return RumusanCpmkData::fromArray([
            [
                'kode' => 'CPMK-1', 'rumusan' => 'Menjelaskan konsep.',
                'cpl' => [['prodi_cpl_id' => $a->id, 'bobot' => 60]],
                'sub' => [
                    ['kode' => 'Sub-1.1', 'rumusan' => 'Mendefinisikan istilah.', 'bobot' => 50],
                    ['kode' => 'Sub-1.2', 'rumusan' => 'Menghubungkan konsep.', 'bobot' => 50],
                ],
            ],
            [
                'kode' => 'CPMK-2', 'rumusan' => 'Menerapkan konsep.',
                'cpl' => [['prodi_cpl_id' => $a->id, 'bobot' => 40], ['prodi_cpl_id' => $b->id, 'bobot' => 50]],
                'sub' => [
                    ['kode' => 'Sub-2.1', 'rumusan' => 'Memilih metode.', 'bobot' => 40],
                    ['kode' => 'Sub-2.2', 'rumusan' => 'Menyelesaikan kasus.', 'bobot' => 60],
                ],
            ],
            [
                'kode' => 'CPMK-3', 'rumusan' => 'Mengomunikasikan hasil.',
                'cpl' => [['prodi_cpl_id' => $b->id, 'bobot' => 50]],
                'sub' => [],
            ],
        ]);
    }

    /** "s:{id}" of a sub-CPMK, or "c:{id}" of a CPMK, by code. */
    public function kunci(string $kode): string
    {
        foreach ($this->versi->cpmk as $c) {
            if ($c->kode === $kode) {
                return 'c:'.$c->id;
            }

            foreach ($c->sub as $s) {
                if ($s->kode === $kode) {
                    return 's:'.$s->id;
                }
            }
        }

        throw new \InvalidArgumentException($kode);
    }

    public function cpmkId(string $kode): int
    {
        return $this->versi->cpmk->firstWhere('kode', $kode)->id;
    }

    /**
     * The mapping most tests use, every target measured:
     *   Tugas → Sub 1.1 100 · UTS → Sub 1.2 50, Sub 2.1 50 · UAS → Sub 2.2 50, CPMK-3 50
     *
     * @return array<int, array<string, int>>
     */
    public function pemetaanBaku(): array
    {
        return [
            $this->komponen['Tugas']->id => [$this->kunci('Sub-1.1') => 100],
            $this->komponen['UTS']->id => [$this->kunci('Sub-1.2') => 50, $this->kunci('Sub-2.1') => 50],
            $this->komponen['UAS']->id => [$this->kunci('Sub-2.2') => 50, $this->kunci('CPMK-3') => 50],
        ];
    }

    /** Enrols a student through an approved KRS and records their scores. */
    public function peserta(array $skor, ?Mahasiswa $mahasiswa = null, ?KelasKuliah $kelas = null): KrsDetail
    {
        $kelas ??= $this->kelas;
        $mahasiswa ??= Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'angkatan' => 2024]);

        $krs = Krs::firstOrCreate(
            ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $kelas->tahun_akademik_id],
            ['semester_ke' => 1, 'batas_sks' => 24, 'status' => 'disetujui'],
        );

        StatusMahasiswa::firstOrCreate(
            ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $kelas->tahun_akademik_id],
            ['status' => 'A', 'semester_ke' => 1],
        );

        $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => $kelas->sks]);

        foreach ($skor as $nama => $nilai) {
            NilaiKomponen::create([
                'komponen_nilai_id' => $kelas->komponenNilai()->where('nama', $nama)->value('id'),
                'krs_detail_id' => $detail->id,
                'nilai' => $nilai,
            ]);
        }

        return $detail;
    }
}
