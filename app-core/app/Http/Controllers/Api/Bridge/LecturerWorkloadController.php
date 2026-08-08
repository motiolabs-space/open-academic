<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Enums\StatusBkd;
use App\Enums\UnsurBkd;
use App\Http\Controllers\Controller;
use App\Models\Sdm\BkdLaporan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use App\Services\Sdm\PortofolioDosen;
use Illuminate\Http\Request;

/**
 * The lecturer side of national reporting, counted.
 *
 * Two shapes from one endpoint, because they answer the same question at two
 * zoom levels: `?nidn=` returns one lecturer's record for an integration script
 * to submit, and the bare call returns the campus tallies a dashboard needs.
 *
 * Consistent with IkuDataController: these are counts of records that exist. No
 * indicator is computed, no threshold applied, no target compared against. The
 * 12–16 SKS range is reported as a campus setting alongside the numbers rather
 * than turned into a pass mark here, because it is a campus interpretation and
 * the consumer may hold a different one.
 */
class LecturerWorkloadController extends Controller
{
    use ResolvesBridgeQuery;

    public function __construct(private readonly PortofolioDosen $portofolio) {}

    public function __invoke(Request $request): array
    {
        $term = $this->term($request, wajib: true);
        $nidn = $request->string('nidn')->toString();

        if ($nidn !== '') {
            $dosen = Dosen::query()
                ->with(['prodi', 'riwayatPendidikan', 'riwayatJabatan', 'sertifikasi'])
                ->where('nidn', $nidn)
                ->first();

            abort_if($dosen === null, 404, "Dosen dengan NIDN {$nidn} tidak ditemukan.");

            return [
                'data' => [
                    'dosen' => $this->portofolio->identitas($dosen),
                    'semester' => $this->portofolio->semester($dosen, $term),
                ],
            ];
        }

        return [
            'data' => [
                'semester' => $term->kode,
                'catatan' => 'Cacahan fakta transaksional. Rentang 12–16 SKS disertakan '
                    .'sebagai pengaturan kampus, bukan sebagai ambang yang sudah diterapkan.',

                'batas_kampus' => [
                    'minimum_sks' => config('bkd.batas.minimum_ratus') / 100,
                    'maksimum_sks' => config('bkd.batas.maksimum_ratus') / 100,
                ],

                'pelaporan_bkd' => $this->pelaporan($term->id),
                'beban_kerja' => $this->beban($term->id),
                'kegiatan_dosen' => $this->kegiatan($term->id),
                'luaran' => $this->luaran($term->id),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function pelaporan(int $termId): array
    {
        $laporan = BkdLaporan::where('tahun_akademik_id', $termId)->get();

        return [
            'total_laporan' => $laporan->count(),
            'per_status' => collect(StatusBkd::cases())
                ->mapWithKeys(fn (StatusBkd $s): array => [
                    $s->value => $laporan->where('status', $s)->count(),
                ])
                ->filter(fn (int $n): bool => $n > 0),

            'per_kesimpulan' => $laporan
                ->whereNotNull('kesimpulan')
                ->groupBy(fn (BkdLaporan $l): string => $l->kesimpulan->value)
                ->map(fn ($g): int => $g->count()),
        ];
    }

    /**
     * Credit totals, only over reports that were actually submitted.
     *
     * Drafts are excluded. A draft is a screen somebody opened, not a claim
     * anybody made, and averaging it in would drag the campus figure towards
     * zero in proportion to how many people started and stopped.
     *
     * @return array<string, mixed>
     */
    private function beban(int $termId): array
    {
        $laporan = BkdLaporan::query()
            ->where('tahun_akademik_id', $termId)
            ->where('status', '!=', StatusBkd::Draft->value)
            ->get();

        if ($laporan->isEmpty()) {
            return ['laporan_diajukan' => 0];
        }

        return [
            'laporan_diajukan' => $laporan->count(),

            'rerata_sks' => collect(UnsurBkd::cases())
                ->mapWithKeys(fn (UnsurBkd $u): array => [
                    $u->value => round($laporan->avg('sks_'.$u->value) / 100, 2),
                ])
                ->all() + ['total' => round($laporan->avg('sks_total') / 100, 2)],

            // Bucketed against the campus range rather than labelled pass/fail:
            // the consumer applies whatever rule it is reporting under.
            'sebaran_total' => [
                'di_bawah_minimum' => $laporan->where('sks_total', '<', config('bkd.batas.minimum_ratus'))->count(),
                'dalam_rentang' => $laporan
                    ->whereBetween('sks_total', [config('bkd.batas.minimum_ratus'), config('bkd.batas.maksimum_ratus')])
                    ->count(),
                'di_atas_maksimum' => $laporan->where('sks_total', '>', config('bkd.batas.maksimum_ratus'))->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function kegiatan(int $termId): array
    {
        $kegiatan = PenugasanDosen::where('tahun_akademik_id', $termId)->get();
        $terverifikasi = $kegiatan->where('is_verified', true);

        return [
            'total_catatan' => $kegiatan->count(),
            'terverifikasi' => $terverifikasi->count(),
            'dosen_terlibat' => $terverifikasi->pluck('dosen_id')->unique()->count(),

            'per_unsur' => $terverifikasi
                ->whereNotNull('unsur')
                ->groupBy(fn (PenugasanDosen $p): string => $p->unsur->value)
                ->map(fn ($g): int => $g->count()),

            'per_tingkat' => $terverifikasi
                ->whereNotNull('tingkat')
                ->groupBy(fn (PenugasanDosen $p): string => $p->tingkat->value)
                ->map(fn ($g): int => $g->count()),
        ];
    }

    /**
     * Outputs, which is the lecturer-side evidence IKU 5 rests on.
     *
     * Whether an output qualifies is a regulation question that changes, so the
     * breakdown is returned per type and per reach and the decision stays with
     * the consumer.
     *
     * @return array<string, mixed>
     */
    private function luaran(int $termId): array
    {
        $luaran = PenugasanDosen::query()
            ->where('tahun_akademik_id', $termId)
            ->whereNotNull('luaran_jenis')
            ->get();

        return [
            'total' => $luaran->count(),
            'per_jenis' => $luaran
                ->groupBy(fn (PenugasanDosen $p): string => $p->luaran_jenis->value)
                ->map(fn ($g): int => $g->count()),
            'per_tingkat' => $luaran
                ->whereNotNull('tingkat')
                ->groupBy(fn (PenugasanDosen $p): string => $p->tingkat->value)
                ->map(fn ($g): int => $g->count()),
            'berpotensi_iku5' => $luaran
                ->filter(fn (PenugasanDosen $p): bool => $p->luaran_jenis->luaranIku5())
                ->count(),
        ];
    }
}
