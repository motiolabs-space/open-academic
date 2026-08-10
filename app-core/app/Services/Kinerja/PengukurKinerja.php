<?php

declare(strict_types=1);

namespace App\Services\Kinerja;

use App\Enums\StudentStatus;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\UnitKerja;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Turns an indicator key into a number, for one unit and one period.
 *
 * This class is the reason `sumber_realisasi = dihitung` means anything. Every
 * key it answers is counted from records that exist; none of them can be typed,
 * adjusted, or rounded up before a review.
 *
 * **It deliberately does not compute IKU scores.** No threshold is applied and
 * no target is compared here — that boundary is the same one IkuDataController
 * draws, and it belongs to Open Campus. What this returns is a fact: how many
 * students, how many classes, what average.
 */
class PengukurKinerja
{
    /** @return array<string, array<string, mixed>> */
    public function katalog(): array
    {
        return (array) config('kinerja.indikator', []);
    }

    public function dikenal(string $kunci): bool
    {
        return array_key_exists($kunci, $this->katalog());
    }

    /** @return array<string, mixed> */
    public function definisi(string $kunci): array
    {
        if (!$this->dikenal($kunci)) {
            throw new InvalidArgumentException(sprintf(
                'Indikator "%s" tidak terdaftar. Yang tersedia: %s.',
                $kunci,
                implode(', ', array_keys($this->katalog())),
            ));
        }

        return $this->katalog()[$kunci];
    }

    /**
     * The measured value for one unit.
     *
     * Scoped to the unit's programme when it has one, and to the programmes of
     * everything beneath it when it does not — which is what a dean means by
     * "my faculty's number". A unit with no programme anywhere below it gets the
     * campus-wide figure, because that is the only honest answer available.
     */
    public function ukur(string $kunci, UnitKerja $unit, ?TahunAkademik $term = null): float
    {
        $definisi = $this->definisi($kunci);
        $term ??= TahunAkademik::aktif();

        $prodiIds = $definisi['lingkup'] === 'prodi'
            ? $this->prodiDalam($unit)
            : [];

        return match ($kunci) {
            'mahasiswa_aktif' => (float) Mahasiswa::query()
                ->where('status', StudentStatus::Aktif->value)
                ->when($prodiIds !== [], fn ($q) => $q->whereIn('prodi_id', $prodiIds))
                ->count(),

            'lulusan' => (float) Yudisium::query()
                ->where('status', 'ditetapkan')
                ->when($term, fn ($q) => $q->where('tahun_akademik_id', $term->id))
                ->when($prodiIds !== [], fn ($q) => $q->whereHas(
                    'mahasiswa',
                    fn ($s) => $s->whereIn('prodi_id', $prodiIds),
                ))
                ->count(),

            'mbkm_peserta' => (float) AktivitasMahasiswa::query()
                ->where('is_verified', true)
                ->when($term, fn ($q) => $q->where('tahun_akademik_id', $term->id))
                ->when($prodiIds !== [], fn ($q) => $q->whereHas(
                    'mahasiswa',
                    fn ($s) => $s->whereIn('prodi_id', $prodiIds),
                ))
                ->distinct()
                ->count('mahasiswa_id'),

            'praktisi_mengajar' => (float) KelasKuliah::query()
                ->when($term, fn ($q) => $q->where('tahun_akademik_id', $term->id))
                ->when($prodiIds !== [], fn ($q) => $q->whereIn('prodi_id', $prodiIds))
                ->whereHas('dosen', fn ($d) => $d->where('dosen_kelas.peran', 'praktisi'))
                ->count(),

            'kelas_kolaboratif' => (float) KelasKuliah::query()
                ->when($term, fn ($q) => $q->where('tahun_akademik_id', $term->id))
                ->when($prodiIds !== [], fn ($q) => $q->whereIn('prodi_id', $prodiIds))
                ->where(fn ($q) => $q->where('is_case_method', true)
                    ->orWhere('is_team_based_project', true))
                ->count(),

            'dosen_luar_kampus' => (float) PenugasanDosen::query()
                ->where('is_verified', true)
                ->when($term, fn ($q) => $q->where('tahun_akademik_id', $term->id))
                ->distinct()
                ->count('dosen_id'),

            'rerata_ipk' => $this->rerataIpk($prodiIds, $term),

            'keterlaksanaan_jurnal' => $this->keterlaksanaanJurnal($prodiIds, $term),

            default => throw new InvalidArgumentException(
                sprintf('Indikator "%s" terdaftar tetapi belum punya penghitung.', $kunci),
            ),
        };
    }

    /**
     * Programme ids covered by this unit, itself and everything beneath it.
     *
     * @return array<int, int>
     */
    private function prodiDalam(UnitKerja $unit): array
    {
        $semua = UnitKerja::query()->get(['id', 'parent_id', 'prodi_id']);

        return $unit->turunan($semua)
            ->pluck('prodi_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<int, int> $prodiIds */
    private function rerataIpk(array $prodiIds, ?TahunAkademik $term): float
    {
        $nilai = StatusMahasiswa::query()
            ->where('is_final', true)
            ->when($term, fn ($q) => $q->where('tahun_akademik_id', $term->id))
            ->when($prodiIds !== [], fn ($q) => $q->whereHas(
                'mahasiswa',
                fn ($s) => $s->whereIn('prodi_id', $prodiIds),
            ))
            ->pluck('ipk');

        return $nilai->isEmpty() ? 0.0 : round((float) $nilai->avg(), 2);
    }

    /**
     * Share of held meetings that carry a journal entry.
     *
     * Two counts, not one: "held" and "journalled" are different numbers, and
     * the gap between them is the finding. Reporting only the ratio keeps that
     * intact — a class with fourteen held and four journalled scores 29%, which
     * is exactly the documentation problem somebody should be looking at.
     *
     * @param array<int, int> $prodiIds
     */
    private function keterlaksanaanJurnal(array $prodiIds, ?TahunAkademik $term): float
    {
        $pertemuan = PertemuanKelas::query()
            ->whereHas('kelasKuliah', function ($q) use ($prodiIds, $term): void {
                $q->when($term, fn ($s) => $s->where('tahun_akademik_id', $term->id))
                    ->when($prodiIds !== [], fn ($s) => $s->whereIn('prodi_id', $prodiIds));
            })
            ->get(['is_terlaksana', 'jurnal_diisi_at']);

        $terlaksana = $pertemuan->where('is_terlaksana', true)->count();

        if ($terlaksana === 0) {
            return 0.0;
        }

        $berjurnal = $pertemuan->whereNotNull('jurnal_diisi_at')->count();

        return round($berjurnal / $terlaksana * 100, 1);
    }

    /**
     * Measures every computed key at once, for a screen that lists them.
     *
     * @return Collection<string, float>
     */
    public function ukurSemua(UnitKerja $unit, ?TahunAkademik $term = null): Collection
    {
        return collect($this->katalog())
            ->keys()
            ->mapWithKeys(fn (string $kunci): array => [
                $kunci => $this->ukur($kunci, $unit, $term),
            ]);
    }
}
