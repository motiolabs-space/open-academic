<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\BarisNilai;
use App\Enums\GradeLetter;
use App\Exceptions\PenilaianException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\NilaiKomponen;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Notifications\Akademik\NilaiTerbit;
use App\Services\Bridge\BridgeEventPublisher;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Weighted grading, locking, and the audited correction path.
 *
 * The invariant this service exists to protect: a finalised grade is closed.
 * Not to the lecturer who entered it, not to a second lecturer on the same
 * class — closed. Reopening one is a separate, staff-only action that records
 * why, because "the grade changed and nobody knows who or why" is the failure
 * an academic record system must never allow.
 */
class PenilaianService
{
    public function __construct(
        private readonly PresensiService $presensi,
        private readonly IndeksPrestasiCalculator $indeks,
        private readonly BridgeEventPublisher $bridge,
        private readonly Notifier $notifier,
    ) {}

    /**
     * The class's assessment components, seeded from configuration the first
     * time a lecturer opens the sheet.
     *
     * @return Collection<int, KomponenNilai>
     */
    public function komponen(KelasKuliah $kelas): Collection
    {
        $komponen = $kelas->komponenNilai()->get();

        if ($komponen->isNotEmpty()) {
            return $komponen;
        }

        foreach (config('academic.grading.default_components') as $urutan => $bawaan) {
            KomponenNilai::create([
                'kelas_kuliah_id' => $kelas->id,
                'nama' => $bawaan['name'],
                'bobot' => $bawaan['weight'],
                'urutan' => $urutan,
            ]);
        }

        return $kelas->komponenNilai()->get();
    }

    /**
     * Replaces the class's components.
     *
     * @param array<int, array{nama: string, bobot: int}> $komponen
     */
    public function simpanKomponen(KelasKuliah $kelas, array $komponen): Collection
    {
        $this->pastikanBelumFinal($kelas);

        if ($komponen === []) {
            throw PenilaianException::komponenKosong();
        }

        $total = array_sum(array_column($komponen, 'bobot'));

        if ($total !== 100) {
            throw PenilaianException::bobotTidakSeratus((int) $total);
        }

        return DB::transaction(function () use ($kelas, $komponen): Collection {
            $kelas->komponenNilai()->delete();

            foreach (array_values($komponen) as $urutan => $baris) {
                KomponenNilai::create([
                    'kelas_kuliah_id' => $kelas->id,
                    'nama' => $baris['nama'],
                    'bobot' => (int) $baris['bobot'],
                    'urutan' => $urutan,
                ]);
            }

            return $kelas->komponenNilai()->get();
        });
    }

    /**
     * Saves component scores and refreshes each affected student's provisional
     * final grade. Nothing is locked here — that is finalisasi()'s job.
     *
     * @param array<int, array<int, float|string|null>> $isian krs_detail id => [komponen id => score]
     */
    public function simpanNilai(KelasKuliah $kelas, array $isian): int
    {
        $this->pastikanPeriodeTerbuka($kelas);
        $this->pastikanBelumFinal($kelas);

        $komponen = $this->komponen($kelas);

        if ($komponen->isEmpty()) {
            throw PenilaianException::komponenKosong();
        }

        $detailSah = $kelas->krsDetail()->pluck('id')->all();

        return DB::transaction(function () use ($kelas, $isian, $komponen, $detailSah): int {
            $tersimpan = 0;

            foreach ($isian as $krsDetailId => $skor) {
                if (!in_array((int) $krsDetailId, $detailSah, true)) {
                    continue;
                }

                foreach ($skor as $komponenId => $nilai) {
                    if (!$komponen->contains('id', (int) $komponenId)) {
                        continue;
                    }

                    $bersih = $this->bersihkanNilai($nilai);

                    NilaiKomponen::updateOrCreate(
                        ['komponen_nilai_id' => (int) $komponenId, 'krs_detail_id' => (int) $krsDetailId],
                        ['nilai' => $bersih],
                    );
                }

                $this->segarkanNilaiAkhir((int) $krsDetailId, $kelas, $komponen);
                $tersimpan++;
            }

            // "belum" hanya benar ketika sama sekali belum ada isian. Selama
            // belum difinalisasi, sheet yang sudah terisi penuh tetap
            // "sebagian" — final adalah keadaan tersendiri, bukan kelengkapan.
            $kelas->update([
                'status_nilai' => $this->jumlahTerisi($kelas, $komponen) === 0 ? 'belum' : 'sebagian',
            ]);

            return $tersimpan;
        });
    }

    /**
     * Locks the class's grades and rewrites every affected student's IPS/IPK.
     *
     * Refuses while any cell is still empty: a half-filled sheet finalised by
     * accident produces grades nobody can distinguish from deliberate zeroes.
     */
    public function finalisasi(KelasKuliah $kelas, Dosen $dosen): int
    {
        $this->pastikanPeriodeTerbuka($kelas);
        $this->pastikanBelumFinal($kelas);

        $komponen = $this->komponen($kelas);

        if ($kelas->krsDetail()->count() === 0) {
            throw PenilaianException::tanpaPeserta();
        }

        if (($kosong = $this->adaNilaiKosong($kelas, $komponen)) > 0) {
            throw PenilaianException::adaNilaiKosong($kosong);
        }

        DB::transaction(function () use ($kelas, $dosen, $komponen): void {
            foreach ($kelas->krsDetail()->with('krs')->get() as $detail) {
                $this->segarkanNilaiAkhir($detail->id, $kelas, $komponen);

                Nilai::where('krs_detail_id', $detail->id)->update([
                    'is_final' => true,
                    'finalized_at' => now(),
                    'finalized_by_dosen_id' => $dosen->id,
                ]);
            }

            $kelas->update([
                'status_nilai' => 'final',
                'finalized_at' => now(),
                'finalized_by_dosen_id' => $dosen->id,
            ]);
        });

        $diperbarui = $this->indeks->hitungUlangSeluruhTerm($kelas->tahunAkademik);

        // Published after the recalculation, so a consumer that reacts by
        // fetching the student never reads a half-updated IPK.
        $this->bridge->publish('grade.finalized', [
            'kelas_uuid' => $kelas->uuid,
            'semester' => $kelas->tahunAkademik->kode,
            'mata_kuliah' => $kelas->mataKuliah->kode,
            'jumlah_mahasiswa' => $kelas->krsDetail()->count(),
            'difinalisasi_oleh' => $dosen->uuid,
            'difinalisasi_pada' => now()->toIso8601String(),
        ]);

        /*
         * One message per class, not one per student-grade.
         *
         * A student taking six courses should hear six times over a fortnight as
         * each lecturer finalises, not once per grade component. Finalisation is
         * the event; everything below it is bookkeeping.
         */
        $this->notifier->kirimBanyak(
            Mahasiswa::query()
                ->whereIn('id', $kelas->krsDetail()->join(
                    'krs',
                    'krs.id',
                    '=',
                    'krs_detail.krs_id',
                )->pluck('krs.mahasiswa_id'))
                ->get(),
            new NilaiTerbit($kelas),
        );

        return $diperbarui;
    }

    /**
     * The audited correction path for a grade that is already locked.
     *
     * Staff-only by policy: a lecturer able to silently reopen their own
     * finalised grades would make the lock decorative.
     */
    public function koreksi(Nilai $nilai, float $nilaiAngka, string $alasan, Staff $staff): Nilai
    {
        if (blank($alasan)) {
            throw PenilaianException::alasanKoreksiWajib();
        }

        if ($nilaiAngka < 0 || $nilaiAngka > 100) {
            throw PenilaianException::nilaiDiLuarRentang();
        }

        $huruf = GradeLetter::fromScore($nilaiAngka);
        $sebelum = $nilai->nilai_huruf?->value;

        $nilai->update([
            'nilai_angka' => $nilaiAngka,
            'nilai_huruf' => $huruf,
            'bobot' => $huruf->weight(),
            'catatan_koreksi' => $alasan,
        ]);

        // An explicit trail entry: the attribute diff alone does not say why.
        $nilai->recordActivity('corrected', sprintf(
            'Koreksi nilai %s → %s oleh %s. Alasan: %s',
            $sebelum ?? '-',
            $huruf->value,
            $staff->nama,
            $alasan,
        ));

        $this->indeks->hitungUlang($nilai->mahasiswa, $nilai->krsDetail->krs->tahunAkademik);

        return $nilai->refresh();
    }

    /**
     * The grade sheet as a screen needs it.
     *
     * @return Collection<int, BarisNilai>
     */
    public function lembarNilai(KelasKuliah $kelas): Collection
    {
        $komponen = $this->komponen($kelas);
        $rekapHadir = $this->presensi->rekapKelas($kelas);
        $minimum = (float) config('academic.attendance.min_percent_for_final_exam');

        return $kelas->krsDetail()
            ->with(['krs.mahasiswa', 'nilai', 'nilaiKomponen'])
            ->get()
            ->sortBy(fn (KrsDetail $detail): string => $detail->krs->mahasiswa->nim)
            ->map(function (KrsDetail $detail) use ($komponen, $rekapHadir, $minimum): BarisNilai {
                $mahasiswa = $detail->krs->mahasiswa;

                $skor = $komponen->mapWithKeys(fn (KomponenNilai $k): array => [
                    $k->id => $detail->nilaiKomponen->firstWhere('komponen_nilai_id', $k->id)?->nilai,
                ])->all();

                $persen = $rekapHadir[$mahasiswa->id] ?? null;

                return new BarisNilai(
                    krsDetailId: $detail->id,
                    nim: $mahasiswa->nim,
                    nama: $mahasiswa->nama,
                    komponen: array_map(
                        fn ($nilai): ?float => $nilai === null ? null : (float) $nilai,
                        $skor,
                    ),
                    nilaiAkhir: $detail->nilai?->nilai_angka === null ? null : (float) $detail->nilai->nilai_angka,
                    huruf: $detail->nilai?->nilai_huruf,
                    final: (bool) ($detail->nilai?->is_final ?? false),
                    lengkap: !in_array(null, $skor, true),
                    persenKehadiran: $persen,
                    layakUas: $persen === null || $persen >= $minimum,
                );
            })
            ->values();
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    /** Weighted average of the component scores, stored as a provisional grade. */
    private function segarkanNilaiAkhir(int $krsDetailId, KelasKuliah $kelas, Collection $komponen): void
    {
        $skor = NilaiKomponen::where('krs_detail_id', $krsDetailId)
            ->pluck('nilai', 'komponen_nilai_id');

        $akhir = 0.0;

        foreach ($komponen as $k) {
            $akhir += (float) ($skor[$k->id] ?? 0) * ($k->bobot / 100);
        }

        $akhir = round($akhir, 2);
        $huruf = GradeLetter::fromScore($akhir);

        $detail = KrsDetail::with('krs')->find($krsDetailId);

        Nilai::updateOrCreate(
            ['krs_detail_id' => $krsDetailId],
            [
                'kelas_kuliah_id' => $kelas->id,
                'mahasiswa_id' => $detail->krs->mahasiswa_id,
                'nilai_angka' => $akhir,
                'nilai_huruf' => $huruf,
                'bobot' => $huruf->weight(),
            ],
        );
    }

    /** How many component cells are still empty across the class. */
    private function adaNilaiKosong(KelasKuliah $kelas, Collection $komponen): int
    {
        $harusnya = $kelas->krsDetail()->count() * $komponen->count();

        return max(0, $harusnya - $this->jumlahTerisi($kelas, $komponen));
    }

    private function jumlahTerisi(KelasKuliah $kelas, Collection $komponen): int
    {
        return NilaiKomponen::whereIn('krs_detail_id', $kelas->krsDetail()->pluck('id'))
            ->whereIn('komponen_nilai_id', $komponen->pluck('id'))
            ->whereNotNull('nilai')
            ->count();
    }

    private function bersihkanNilai(float|string|null $nilai): ?float
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        $angka = (float) $nilai;

        if ($angka < 0 || $angka > 100) {
            throw PenilaianException::nilaiDiLuarRentang();
        }

        return round($angka, 2);
    }

    private function pastikanBelumFinal(KelasKuliah $kelas): void
    {
        if ($kelas->status_nilai === 'final') {
            throw PenilaianException::kelasSudahFinal();
        }
    }

    private function pastikanPeriodeTerbuka(KelasKuliah $kelas): void
    {
        if (!$kelas->tahunAkademik->penilaianDibuka()) {
            throw PenilaianException::periodeTertutup();
        }
    }
}
