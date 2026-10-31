<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Krs;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\RiwayatRegistrasiMahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Services\Akademik\KhsService;
use App\Services\Akademik\TranskripService;
use App\Services\Feeder\FeederValidator;
use App\Services\Kemahasiswaan\DosenWaliService;
use App\Support\Portal;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * One student, as the registrar sees them.
 *
 * Read-only on purpose. The identity fields PDDIKTI locks once reported are
 * changed through the PDM/PJK path in the Feeder console (ADR-017), the
 * advisor through DosenWaliService, the entry record through the transfer
 * intake — each from its own screen, each audited there. This page gathers
 * them and links out.
 *
 * Grades and money are behind their own permissions: a finance clerk holds
 * mahasiswa.view but not nilai.view, and a registrar the reverse, so each
 * section asks its own policy rather than inheriting the page's.
 */
class MahasiswaDetailController extends Controller
{
    public function __construct(
        private readonly KhsService $khs,
        private readonly TranskripService $transkrip,
    ) {}

    public function show(Mahasiswa $mahasiswa, FeederValidator $validator, DosenWaliService $wali): View
    {
        $this->authorize('view', $mahasiswa);

        $aktor = Portal::user();
        $bolehNilai = $aktor->can('viewAny', Nilai::class);
        $bolehTagihan = $aktor->can('viewAny', Tagihan::class);
        $bolehWali = $aktor->can('assignAdvisor', $mahasiswa);

        $mahasiswa->load([
            'prodi.fakultas',
            'kurikulum',
            'konsentrasi',
            'dosenWali',
            'cuti' => fn ($q) => $q->with('tahunAkademik')->orderByDesc('id'),
            'riwayatDosenWali' => fn ($q) => $q->with(['dosenLama', 'dosenBaru', 'penetap'])->limit(10),
            'perubahanFeeder' => fn ($q) => $q->terbuka(),
        ]);

        $this->muatRiwayatRegistrasi($mahasiswa);

        $riwayat = $this->khs->riwayat($mahasiswa);
        $terakhir = $riwayat->where('is_final', true)->last();

        return view('admin.mahasiswa-detail', [
            'judul' => $mahasiswa->nama,
            'konteks' => $mahasiswa->nim.' · '.$mahasiswa->prodi->namaLengkap(),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Data Mahasiswa' => route('admin.mahasiswa'), $mahasiswa->nim],
            'mahasiswa' => $mahasiswa,
            'riwayat' => $riwayat,
            'terakhir' => $terakhir,
            'krs' => Krs::query()
                ->with(['tahunAkademik', 'detail.kelasKuliah.mataKuliah'])
                ->where('mahasiswa_id', $mahasiswa->id)
                ->orderByDesc('tahun_akademik_id')
                ->get(),
            'bolehNilai' => $bolehNilai,
            'nilaiPerTerm' => $bolehNilai ? $this->khs->nilaiPerTerm($mahasiswa) : collect(),
            'bolehTagihan' => $bolehTagihan,
            'tagihan' => $bolehTagihan
                ? Tagihan::query()->with('tahunAkademik')->where('mahasiswa_id', $mahasiswa->id)->orderByDesc('tahun_akademik_id')->get()
                : collect(),
            'bolehWali' => $bolehWali,
            'bolehRegistrasiUlang' => $aktor->can('registrasiUlang', $mahasiswa),
            'bolehFeeder' => $aktor->hasPermissionTo('feeder.view', 'staff'),
            'calonWali' => $bolehWali ? $wali->calon($mahasiswa->prodi_id) : collect(),
            'isuFeeder' => $validator->isuUntuk('mahasiswa', $mahasiswa)
                ->merge($validator->isuUntuk('riwayat_pendidikan', $mahasiswa))
                // Order of sync, not a fault in the data.
                ->reject(fn (array $i): bool => $i['rule'] === 'biodata_belum_sinkron')
                ->values(),
        ]);
    }

    /**
     * Both directions of the registration history (ADR-042 §3) in one query:
     * the event that opened this registration and those that started from it.
     * Two relations would cost a query each on every visit, almost always to
     * find nothing.
     */
    private function muatRiwayatRegistrasi(Mahasiswa $mahasiswa): void
    {
        $riwayat = RiwayatRegistrasiMahasiswa::query()
            ->with(['mahasiswaLama:id,uuid,nim', 'mahasiswaBaru:id,uuid,nim'])
            ->where(fn ($q) => $q->where('mahasiswa_lama_id', $mahasiswa->id)->orWhere('mahasiswa_baru_id', $mahasiswa->id))
            ->orderByDesc('id')
            ->get();

        $mahasiswa->setRelation('peristiwaRegistrasi', $riwayat->where('mahasiswa_lama_id', $mahasiswa->id)->values());
        $mahasiswa->setRelation('registrasiSebelumnya', $riwayat->firstWhere('mahasiswa_baru_id', $mahasiswa->id));
    }

    /** One term's KHS, from the same service the student prints from. */
    public function khs(Mahasiswa $mahasiswa, TahunAkademik $term): Response
    {
        $this->authorize('view', $mahasiswa);
        $this->authorize('viewAny', Nilai::class);

        return $this->khs->pdf($mahasiswa, $term)->download($this->khs->namaBerkas($mahasiswa, $term));
    }

    public function transkrip(Mahasiswa $mahasiswa): Response
    {
        $this->authorize('view', $mahasiswa);
        $this->authorize('viewAny', Nilai::class);

        return $this->transkrip->pdf($mahasiswa)->download($this->transkrip->namaBerkas($mahasiswa));
    }
}
