<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\ProdiCpl;
use App\Models\Akademik\Rps;
use App\Models\Sdm\Dosen;
use App\Services\Akademik\JurnalService;
use App\Services\Akademik\RpsService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Writing the plan, and recording what was actually delivered against it.
 *
 * One controller for both because they are two halves of one thing: the plan
 * says what should happen, the journal says what did, and the only useful screen
 * shows them beside each other.
 */
class RpsController extends Controller
{
    public function __construct(
        private readonly RpsService $rps,
        private readonly JurnalService $jurnal,
    ) {}

    public function index(): View
    {
        $dosen = $this->dosen();
        $term = Portal::term();

        $kelas = KelasKuliah::query()
            ->with('mataKuliah')
            ->where('tahun_akademik_id', $term->id)
            ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosen->id))
            ->get();

        return view('dosen.rps', [
            'judul' => 'RPS & Jurnal Perkuliahan',
            'konteks' => $term->nama,
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), 'RPS'],

            'daftar' => $kelas->map(fn (KelasKuliah $k): array => [
                'kelas' => $k,
                'rps' => Rps::untuk($k->mata_kuliah_id, $k->tahun_akademik_id),
                'keterlaksanaan' => $this->jurnal->keterlaksanaan($k),
            ]),
        ]);
    }

    public function susun(MataKuliah $mataKuliah): View
    {
        $dosen = $this->dosen();
        $term = Portal::term();

        $this->pastikanMengampu($dosen, $mataKuliah, $term->id);

        $rps = $this->rps->mulai($mataKuliah, $term, $dosen);

        return view('dosen.rps-susun', [
            'judul' => 'Susun RPS',
            'konteks' => $mataKuliah->nama,
            'breadcrumb' => [
                'Dasbor' => route('dosen.dashboard'),
                'RPS' => route('dosen.rps'),
                'Susun',
            ],

            'rps' => $rps->load(['pertemuan', 'cpl']),
            'mataKuliah' => $mataKuliah,
            'cplPilihan' => ProdiCpl::where('prodi_id', $mataKuliah->prodi_id)->orderBy('kode')->get(),
            'jumlahPertemuan' => (int) config('academic.attendance.meetings_per_term', 16),
            'kekurangan' => $this->rps->kekurangan($rps->load(['pertemuan', 'cpl'])),
        ]);
    }

    public function simpan(Request $request, Rps $rps): RedirectResponse
    {
        $this->pastikanPenyusun($rps);

        $data = $request->validate([
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'pustaka' => ['nullable', 'string', 'max:2000'],
            'cpl' => ['array'],
            'cpl.*' => ['integer'],
            'pertemuan' => ['required', 'array', 'min:1'],
            'pertemuan.*.pertemuan_ke' => ['required', 'integer', 'min:1', 'max:32'],
            'pertemuan.*.kemampuan_akhir' => ['nullable', 'string', 'max:500'],
            'pertemuan.*.bahan_kajian' => ['nullable', 'string', 'max:500'],
            'pertemuan.*.metode' => ['nullable', 'string', 'max:64'],
            'pertemuan.*.indikator' => ['nullable', 'string', 'max:500'],
            'pertemuan.*.bobot' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $rps->update([
            'deskripsi' => $data['deskripsi'] ?? null,
            'pustaka' => $data['pustaka'] ?? null,
        ]);

        // Blank rows are dropped rather than stored: sixteen empty weeks would
        // satisfy the count check while saying nothing.
        $this->rps->simpanPertemuan($rps, collect($data['pertemuan'])
            ->filter(fn (array $p): bool => filled($p['kemampuan_akhir'] ?? null))
            ->values()
            ->all());

        $this->rps->simpanCpl($rps, collect($data['cpl'] ?? [])
            ->map(fn (int $id): array => ['prodi_cpl_id' => $id])
            ->all());

        return back()->with('sukses', 'RPS tersimpan sebagai draf.');
    }

    public function terbitkan(Rps $rps): RedirectResponse
    {
        $this->pastikanPenyusun($rps);

        $this->rps->terbitkan($rps->load(['pertemuan', 'cpl']), $this->dosen());

        return redirect()->route('dosen.rps')->with('sukses',
            'RPS diterbitkan dan berlaku. Rumusannya dibekukan — revisi berarti versi baru, '
            .'sehingga nilai yang sudah diukur tidak berubah artinya.');
    }

    /** The journal screen for one class. */
    public function jurnal(KelasKuliah $kelas): View
    {
        $dosen = $this->dosen();

        abort_unless($kelas->dosen->contains('id', $dosen->id), 403);

        $rps = Rps::untuk($kelas->mata_kuliah_id, $kelas->tahun_akademik_id);

        return view('dosen.jurnal', [
            'judul' => 'Jurnal Perkuliahan',
            'konteks' => $kelas->mataKuliah->nama.' · Kelas '.$kelas->nama,
            'breadcrumb' => [
                'Dasbor' => route('dosen.dashboard'),
                'RPS' => route('dosen.rps'),
                'Jurnal',
            ],

            'kelas' => $kelas,
            'pertemuan' => $kelas->pertemuan()->orderBy('pertemuan_ke')->get(),
            'rps' => $rps,
            'rencanaPilihan' => $rps === null
                ? []
                : $rps->pertemuan->mapWithKeys(fn ($p): array => [
                    $p->id => 'Pekan '.$p->pertemuan_ke.' — '.Str::limit($p->kemampuan_akhir, 60),
                ])->all(),
            'keterlaksanaan' => $this->jurnal->keterlaksanaan($kelas),
        ]);
    }

    public function simpanJurnal(Request $request, PertemuanKelas $pertemuan): RedirectResponse
    {
        $data = $request->validate([
            'materi' => ['required', 'string', 'max:2000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'rps_pertemuan_id' => ['nullable', 'integer'],
        ]);

        $this->jurnal->isi(
            $pertemuan,
            $this->dosen(),
            $data['materi'],
            $data['rps_pertemuan_id'] ?? null,
            $data['catatan'] ?? null,
        );

        return back()->with('sukses', 'Jurnal tersimpan. Cacah kehadiran ikut dibekukan pada keadaan hari ini.');
    }

    private function pastikanMengampu(Dosen $dosen, MataKuliah $mataKuliah, int $termId): void
    {
        $mengampu = KelasKuliah::query()
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->where('tahun_akademik_id', $termId)
            ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosen->id))
            ->exists();

        abort_unless($mengampu, 403);
    }

    private function pastikanPenyusun(Rps $rps): void
    {
        $this->pastikanMengampu($this->dosen(), $rps->mataKuliah, $rps->tahun_akademik_id);
    }

    private function dosen(): Dosen
    {
        $dosen = Portal::user();

        abort_unless($dosen instanceof Dosen, 403);

        return $dosen;
    }
}
