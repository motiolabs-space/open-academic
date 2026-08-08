<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicantStatus;
use App\Enums\KrsStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\Prodi;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Dosen;
use App\Services\Branding\BrandingService;
use App\Support\Portal;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Institution-wide overview: enrolment, admissions funnel, money collected,
 * and the health of the two integrations that matter (Neo Feeder and Campus
 * Bridge).
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $term = Portal::term();

        $tagihan = Tagihan::where('tahun_akademik_id', $term?->id);

        return view('admin.dashboard', [
            'judul' => 'Dasbor Institusi',
            'konteks' => $term?->nama.' · '.app(BrandingService::class)->institutionName(),
            'term' => $term,

            'mahasiswaAktif' => Mahasiswa::aktif()->count(),
            'mahasiswaTotal' => Mahasiswa::count(),
            'dosenAktif' => Dosen::aktif()->count(),
            'kelasBerjalan' => KelasKuliah::where('tahun_akademik_id', $term?->id)->count(),

            'perProdi' => $this->sebaranProdi(),
            'perStatus' => $this->sebaranStatus(),
            'funnelPmb' => $this->funnelPmb(),

            'tagihanTotal' => (int) (clone $tagihan)->sum('total'),
            'tagihanTerbayar' => (int) (clone $tagihan)->sum('terbayar'),
            'penunggak' => (clone $tagihan)->belumLunas()->count(),

            'krsMenunggu' => Krs::where('tahun_akademik_id', $term?->id)
                ->where('status', KrsStatus::Diajukan->value)->count(),

            // Ratio reported under IKU 11 (educational efficiency).
            'rasioDosenMahasiswa' => $this->rasioDosenMahasiswa(),

            'feederTerakhir' => FeederSyncLog::latest('id')->first(),
            'feederGagal' => FeederSyncLog::gagal()->count(),
            'bridgeConsumers' => BridgeConsumer::aktif()->get(),
        ]);
    }

    /** @return Collection<int, object> */
    private function sebaranProdi(): Collection
    {
        return Prodi::query()
            ->withCount([
                'mahasiswa as jumlah_aktif' => fn ($q) => $q->where('status', StudentStatus::Aktif->value),
                'mahasiswa as jumlah_total',
            ])
            ->orderByDesc('jumlah_aktif')
            ->get();
    }

    /** @return array<string, int> */
    private function sebaranStatus(): array
    {
        $counts = Mahasiswa::query()
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->all();

        $result = [];

        foreach (StudentStatus::cases() as $status) {
            $jumlah = (int) ($counts[$status->value] ?? 0);

            if ($jumlah > 0) {
                $result[$status->value] = $jumlah;
            }
        }

        return $result;
    }

    /** @return array<int, array{label: string, jumlah: int}> */
    private function funnelPmb(): array
    {
        $counts = PmbPendaftar::query()
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->all();

        // The funnel is cumulative: everyone who re-registered also passed
        // selection, so each stage counts itself plus everything downstream.
        $tahapan = [
            'Pendaftar' => [
                ApplicantStatus::Mendaftar, ApplicantStatus::Verifikasi, ApplicantStatus::Seleksi,
                ApplicantStatus::Lulus, ApplicantStatus::DaftarUlang, ApplicantStatus::Mahasiswa,
                ApplicantStatus::TidakLulus, ApplicantStatus::Batal,
            ],
            'Lulus Seleksi' => [
                ApplicantStatus::Lulus, ApplicantStatus::DaftarUlang, ApplicantStatus::Mahasiswa,
            ],
            'Registrasi Ulang' => [ApplicantStatus::DaftarUlang, ApplicantStatus::Mahasiswa],
            'Mahasiswa Baru' => [ApplicantStatus::Mahasiswa],
        ];

        $funnel = [];

        foreach ($tahapan as $label => $statuses) {
            $funnel[] = [
                'label' => $label,
                'jumlah' => (int) collect($statuses)->sum(fn (ApplicantStatus $s): int => (int) ($counts[$s->value] ?? 0)),
            ];
        }

        return $funnel;
    }

    private function rasioDosenMahasiswa(): string
    {
        $dosen = Dosen::aktif()->whereNotNull('nidn')->count();
        $mahasiswa = Mahasiswa::aktif()->count();

        return $dosen === 0 ? '—' : '1 : '.(int) round($mahasiswa / $dosen);
    }
}
