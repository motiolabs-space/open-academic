<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Akademik\PemetaanCapaianData;
use App\DTOs\Akademik\RumusanCpmkData;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\KomponenNilaiCpmk;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\ProdiCpl;
use App\Models\Sdm\Staff;
use App\Services\Akademik\CapaianService;
use App\Services\Akademik\CpmkService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * One programme with the outcome hierarchy complete: CPL → CPMK → sub-CPMK
 * for every course of its curriculum, components mapped, and attainment
 * stored for every class already finalised (ADR-038).
 *
 * One programme, not all of them, on purpose: the second programme shows a
 * campus mid-adoption — courses without CPMK, classes that can still be
 * graded, the "belum ada CPMK berlaku" path on the evaluation plan.
 *
 * Every CPMK set has the same shape, chosen so each path of the arithmetic
 * is exercised by something a person can open:
 *
 *   CPMK-1 → CPL A 60%        Sub 1.1 (50) · Sub 1.2 (50)
 *   CPMK-2 → CPL A 40%, B 50% Sub 2.1 (40) · Sub 2.2 (60)
 *   CPMK-3 → CPL B 50%        no sub-CPMK — measured directly
 *
 * and in one running class Sub 2.1 is left unmeasured, so the coverage
 * warning on the evaluation plan is visible rather than assumed.
 */
class CapaianSeeder extends Seeder
{
    public function run(): void
    {
        $prodi = Prodi::orderBy('id')->first();
        $staf = Staff::where('email', 'baak@demo.test')->first() ?? Staff::orderBy('id')->first();

        if ($prodi === null || $staf === null) {
            return;
        }

        $kurikulum = Kurikulum::where('prodi_id', $prodi->id)->where('is_active', true)->first();
        $cpl = ProdiCpl::where('prodi_id', $prodi->id)->orderBy('kode')->get();

        if ($kurikulum === null || $cpl->count() < 2) {
            return;
        }

        $cpmk = app(CpmkService::class);
        $capaian = app(CapaianService::class);

        foreach ($kurikulum->mataKuliah()->orderBy('kode')->get()->values() as $i => $mk) {
            $versi = $cpmk->draf($kurikulum, $mk, $staf);
            $cpmk->simpan($versi, $this->rumusan($mk, $cpl, $i));
            $cpmk->terbitkan($versi, $staf);
        }

        $kelas = KelasKuliah::query()
            ->with('mataKuliah')
            ->where('prodi_id', $prodi->id)
            ->whereIn('mata_kuliah_id', $kurikulum->mataKuliah()->pluck('mata_kuliah.id'))
            ->orderBy('id')
            ->get();

        $berjalanPertama = true;

        foreach ($kelas as $satu) {
            $versi = $cpmk->berlakuUntuk($satu->mataKuliah, $kurikulum);
            $sasaran = $cpmk->sasaran($versi)->keys()->values();
            $komponen = KomponenNilai::where('kelas_kuliah_id', $satu->id)->orderBy('urutan')->get();

            if ($komponen->isEmpty()) {
                continue;
            }

            $porsi = $this->porsi($komponen, $sasaran, $satu->status_nilai !== 'final' && $berjalanPertama);

            if ($satu->status_nilai !== 'final') {
                $berjalanPertama = false;
                $capaian->petakan($satu, new PemetaanCapaianData($porsi));

                continue;
            }

            /*
             * Terms already closed: the mapping a campus adopting this would
             * write for its historical classes, so the recaps have a baseline.
             * Written directly — the service refuses to remap a final class,
             * which is the rule — and then computed exactly as finalisation
             * would have.
             */
            $target = $cpmk->sasaran($versi);

            foreach ($porsi as $komponenId => $baris) {
                foreach ($baris as $kunci => $nilai) {
                    KomponenNilaiCpmk::create([
                        'komponen_nilai_id' => $komponenId,
                        'cpmk_id' => $target->get($kunci)['cpmk']->id,
                        'sub_cpmk_id' => $target->get($kunci)['sub']?->id,
                        'porsi' => $nilai,
                    ]);
                }
            }

            $capaian->hitungKelas($satu);
        }
    }

    /** @param Collection<int, ProdiCpl> $cpl */
    private function rumusan(MataKuliah $mk, Collection $cpl, int $i): RumusanCpmkData
    {
        $a = $cpl[$i % $cpl->count()];
        $b = $cpl[($i + 3) % $cpl->count()];

        $nama = mb_strtolower($mk->nama);

        return RumusanCpmkData::fromArray([
            [
                'kode' => 'CPMK-1',
                'rumusan' => "Mampu menjelaskan konsep dasar {$nama}.",
                'cpl' => [['prodi_cpl_id' => $a->id, 'bobot' => 60]],
                'sub' => [
                    ['kode' => 'Sub-CPMK-1.1', 'rumusan' => 'Mampu mendefinisikan istilah kunci.', 'bobot' => 50],
                    ['kode' => 'Sub-CPMK-1.2', 'rumusan' => 'Mampu menjelaskan hubungan antarkonsep.', 'bobot' => 50],
                ],
            ],
            [
                'kode' => 'CPMK-2',
                'rumusan' => "Mampu menerapkan {$nama} pada persoalan terstruktur.",
                'cpl' => [
                    ['prodi_cpl_id' => $a->id, 'bobot' => 40],
                    ['prodi_cpl_id' => $b->id, 'bobot' => 50],
                ],
                'sub' => [
                    ['kode' => 'Sub-CPMK-2.1', 'rumusan' => 'Mampu memilih metode yang sesuai.', 'bobot' => 40],
                    ['kode' => 'Sub-CPMK-2.2', 'rumusan' => 'Mampu menyelesaikan studi kasus.', 'bobot' => 60],
                ],
            ],
            [
                'kode' => 'CPMK-3',
                'rumusan' => 'Mampu mengomunikasikan hasil kerja secara tertulis.',
                'cpl' => [['prodi_cpl_id' => $b->id, 'bobot' => 50]],
                'sub' => [],
            ],
        ]);
    }

    /**
     * Component shares over the measurable targets, each row closing to 100.
     *
     * Targets are spread across the components in order, so each component
     * measures two or three of them and every target is measured — except
     * Sub-CPMK 2.1 in the one class left with a gap.
     *
     * @param Collection<int, KomponenNilai> $komponen
     * @param Collection<int, string> $sasaran
     * @return array<int, array<string, int>>
     */
    private function porsi(Collection $komponen, Collection $sasaran, bool $sisakanCelah): array
    {
        $daftar = $sisakanCelah ? $sasaran->reject(fn (string $k, int $i): bool => $i === 2)->values() : $sasaran;

        $porsi = [];
        $n = $komponen->count();

        foreach ($daftar as $i => $kunci) {
            $porsi[$komponen[$i % $n]->id][$kunci] = 0;
        }

        // Fewer targets than components: the rest measure the last one.
        foreach ($komponen as $k) {
            $porsi[$k->id] ??= [$daftar->last() => 0];
        }

        foreach ($porsi as $id => $baris) {
            $bagian = intdiv(100, count($baris));
            $kunci = array_keys($baris);

            foreach ($kunci as $j => $satu) {
                $porsi[$id][$satu] = $j === 0 ? 100 - $bagian * (count($kunci) - 1) : $bagian;
            }
        }

        return $porsi;
    }
}
