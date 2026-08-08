<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use Illuminate\Http\Request;

/**
 * Counted transactional facts behind the indicators Open Campus computes.
 *
 * **This is not an IKU calculator.** No score is produced, no threshold is
 * applied, no target is compared against. Every number here is a count of
 * records that exist — the same facts the list endpoints return, tallied so a
 * dashboard does not have to page through ten thousand rows to add them up.
 *
 * Where an indicator depends on a policy figure that changes by ministerial
 * regulation — the 20-credit MBKM threshold, for instance — the breakdown is
 * returned per bucket and the decision is left where it belongs.
 */
class IkuDataController extends Controller
{
    use ResolvesBridgeQuery;

    public function __invoke(Request $request): array
    {
        $term = $this->term($request, wajib: true);
        $prodi = $request->string('prodi')->toString();

        return [
            'data' => [
                'semester' => $term->kode,
                'prodi' => $prodi ?: 'semua',
                'catatan' => 'Cacahan fakta transaksional. Tidak memuat skor, ambang, '
                    .'maupun target — perhitungan indikator dilakukan konsumen.',

                'iku_1_lulusan' => $this->lulusan($term->kode, $prodi),
                'iku_2_pengalaman_luar_kampus' => $this->aktivitasMbkm($term->id, $prodi),
                'iku_3_dosen_luar_kampus' => $this->penugasanDosen($term->id, 3),
                'iku_4_praktisi_mengajar' => $this->praktisiMengajar($term->id, $prodi),
                'iku_7_kelas_kolaboratif' => $this->kelasKolaboratif($term->id, $prodi),
                'iku_11_efisiensi' => $this->efisiensi($prodi),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function lulusan(string $termCode, string $prodi): array
    {
        $query = Yudisium::query()
            ->where('status', 'ditetapkan')
            ->when($prodi, fn ($q) => $q->whereHas('mahasiswa.prodi', fn ($s) => $s->where('kode', $prodi)));

        $lulusan = (clone $query)->with('mahasiswa.alumni')->get();

        return [
            'total_lulusan' => $lulusan->count(),
            'pada_semester_ini' => (clone $query)
                ->whereHas('tahunAkademik', fn ($q) => $q->where('kode', $termCode))
                ->count(),

            // Employment status is what a tracer study fills in; Open Academic
            // only holds the baseline record it starts from.
            'per_status_pekerjaan' => $lulusan
                ->groupBy(fn (Yudisium $y): string => $y->mahasiswa->alumni?->status_pekerjaan ?? 'belum_ditelusuri')
                ->map(fn ($g): int => $g->count()),

            'rata_ipk' => round((float) $lulusan->avg('ipk'), 2),
        ];
    }

    /** @return array<string, mixed> */
    private function aktivitasMbkm(int $termId, string $prodi): array
    {
        $query = AktivitasMahasiswa::query()
            ->where('tahun_akademik_id', $termId)
            ->when($prodi, fn ($q) => $q->whereHas('mahasiswa.prodi', fn ($s) => $s->where('kode', $prodi)));

        $terverifikasi = (clone $query)->terverifikasi()->get();

        return [
            'total_catatan' => (clone $query)->count(),
            'terverifikasi' => $terverifikasi->count(),
            'belum_terverifikasi' => (clone $query)->where('is_verified', false)->count(),
            'mahasiswa_terlibat' => $terverifikasi->pluck('mahasiswa_id')->unique()->count(),
            'per_jenis' => $terverifikasi
                ->groupBy(fn (AktivitasMahasiswa $a): string => $a->jenis->value)
                ->map(fn ($g): int => $g->count()),

            // Bucketed rather than thresholded: the qualifying figure is set by
            // regulation and changes, so the consumer applies it.
            'per_sks_konversi' => [
                '0-5' => $terverifikasi->where('sks_konversi', '<=', 5)->count(),
                '6-19' => $terverifikasi->whereBetween('sks_konversi', [6, 19])->count(),
                '20+' => $terverifikasi->where('sks_konversi', '>=', 20)->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function penugasanDosen(int $termId, int $iku): array
    {
        $penugasan = PenugasanDosen::query()
            ->where('tahun_akademik_id', $termId)
            ->terverifikasi()
            ->get()
            ->filter(fn (PenugasanDosen $p): bool => $iku === 3
                ? $p->jenis->countsForIku3()
                : $p->jenis->countsForIku4());

        return [
            'total_penugasan' => $penugasan->count(),
            'dosen_terlibat' => $penugasan->pluck('dosen_id')->unique()->count(),
            'per_jenis' => $penugasan
                ->groupBy(fn (PenugasanDosen $p): string => $p->jenis->value)
                ->map(fn ($g): int => $g->count()),
            'per_jenis_mitra' => $penugasan
                ->groupBy(fn (PenugasanDosen $p): string => $p->mitra_jenis ?? 'tidak_disebutkan')
                ->map(fn ($g): int => $g->count()),
        ];
    }

    /** @return array<string, mixed> */
    private function praktisiMengajar(int $termId, string $prodi): array
    {
        $kelas = KelasKuliah::query()
            ->with('dosen')
            ->where('tahun_akademik_id', $termId)
            ->when($prodi, fn ($q) => $q->whereHas('prodi', fn ($s) => $s->where('kode', $prodi)))
            ->get();

        $denganPraktisi = $kelas->filter(
            fn (KelasKuliah $k): bool => $k->dosen->contains(fn ($d): bool => $d->pivot->peran === 'praktisi'),
        );

        return [
            'total_kelas' => $kelas->count(),
            'kelas_dengan_praktisi' => $denganPraktisi->count(),
            'praktisi_terlibat' => $denganPraktisi
                ->flatMap(fn (KelasKuliah $k) => $k->dosen->where('pivot.peran', 'praktisi')->pluck('id'))
                ->unique()
                ->count(),
            'penugasan_praktisi_mengajar' => $this->penugasanDosen($termId, 4),
        ];
    }

    /** @return array<string, mixed> */
    private function kelasKolaboratif(int $termId, string $prodi): array
    {
        $kelas = KelasKuliah::query()
            ->where('tahun_akademik_id', $termId)
            ->when($prodi, fn ($q) => $q->whereHas('prodi', fn ($s) => $s->where('kode', $prodi)))
            ->get();

        return [
            'total_kelas' => $kelas->count(),
            'case_method' => $kelas->where('is_case_method', true)->count(),
            'team_based_project' => $kelas->where('is_team_based_project', true)->count(),
            'keduanya' => $kelas->filter(
                fn (KelasKuliah $k): bool => $k->is_case_method && $k->is_team_based_project,
            )->count(),
            'salah_satu' => $kelas->filter(fn (KelasKuliah $k): bool => $k->kelasKolaboratif())->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function efisiensi(string $prodi): array
    {
        $mahasiswaAktif = Mahasiswa::aktif()
            ->when($prodi, fn ($q) => $q->whereHas('prodi', fn ($s) => $s->where('kode', $prodi)))
            ->count();

        $dosenAktif = Dosen::aktif()
            ->whereNotNull('nidn')
            ->when($prodi, fn ($q) => $q->whereHas('prodi', fn ($s) => $s->where('kode', $prodi)))
            ->count();

        return [
            'mahasiswa_aktif' => $mahasiswaAktif,
            'dosen_aktif_ber_nidn' => $dosenAktif,

            // The ratio itself, not a judgement about it.
            'rasio_dosen_mahasiswa' => $dosenAktif > 0
                ? round($mahasiswaAktif / $dosenAktif, 2)
                : null,

            'per_status_mahasiswa' => collect(StudentStatus::cases())
                ->mapWithKeys(fn (StudentStatus $s): array => [
                    $s->value => Mahasiswa::where('status', $s->value)
                        ->when($prodi, fn ($q) => $q->whereHas('prodi', fn ($x) => $x->where('kode', $prodi)))
                        ->count(),
                ])
                ->filter(fn (int $n): bool => $n > 0),

            'jumlah_prodi' => Prodi::aktif()->count(),
        ];
    }
}
