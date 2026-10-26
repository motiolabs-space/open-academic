<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPeristiwa;
use App\Enums\SasaranPeristiwa;
use App\Enums\StatusPeristiwa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Akademik\PeristiwaKalenderRequest;
use App\Models\Akademik\KalenderPeristiwa;
use App\Models\Akademik\Prodi;
use App\Services\Akademik\KalenderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Holidays, campus activities and deadlines — the calendar entries no other
 * module owns.
 *
 * KRS windows, exams, graduation and the rest are not edited here; the
 * calendar screen shows where each of those lives.
 */
class PeristiwaKalenderController extends Controller
{
    public function __construct(private readonly KalenderService $kalender) {}

    public function index(Request $request): View
    {
        $this->authorize('create', KalenderPeristiwa::class);

        $tahun = $request->integer('tahun') ?: Carbon::today()->year;
        $jenis = JenisPeristiwa::tryFrom($request->string('jenis')->toString());
        $status = StatusPeristiwa::tryFrom($request->string('status')->toString());

        $daftar = $this->kalender->daftarManual($tahun, $jenis?->manual() ? $jenis : null, $status);

        return view('admin.kalender.peristiwa', [
            'judul' => __('kalender.judul_kelola'),
            'konteks' => __('kalender.konteks_kelola', ['tahun' => $tahun, 'jumlah' => $daftar->count()]),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('kalender.judul') => route('admin.kalender'),
                __('kalender.judul_kelola'),
            ],
            'daftar' => $daftar,
            'tahun' => $tahun,
            'jenis' => $jenis,
            'status' => $status,
            'tahunPilihan' => collect(range(Carbon::today()->year + 1, Carbon::today()->year - 3))
                ->mapWithKeys(fn (int $t): array => [$t => (string) $t]),
            'jenisPilihan' => JenisPeristiwa::options(JenisPeristiwa::kasusManual()),
            'statusPilihan' => StatusPeristiwa::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', KalenderPeristiwa::class);

        return $this->formulir(null, $request->string('tanggal')->toString() ?: null);
    }

    public function store(PeristiwaKalenderRequest $request): RedirectResponse
    {
        $peristiwa = $this->kalender->simpan($request->isian());

        return $this->kembali($peristiwa, __('kalender.sukses.simpan', ['judul' => $peristiwa->judul]));
    }

    public function edit(KalenderPeristiwa $peristiwa): View
    {
        $this->authorize('update', $peristiwa);

        return $this->formulir($peristiwa, null);
    }

    public function update(PeristiwaKalenderRequest $request, KalenderPeristiwa $peristiwa): RedirectResponse
    {
        $peristiwa = $this->kalender->perbarui($peristiwa, $request->isian());

        return $this->kembali($peristiwa, __('kalender.sukses.ubah', ['judul' => $peristiwa->judul]));
    }

    public function destroy(KalenderPeristiwa $peristiwa): RedirectResponse
    {
        $this->authorize('delete', $peristiwa);

        $tahun = $peristiwa->tanggal_mulai->year;
        $this->kalender->hapus($peristiwa);

        return redirect()
            ->route('admin.kalender.peristiwa.index', ['tahun' => $tahun])
            ->with('sukses', __('kalender.sukses.hapus', ['judul' => $peristiwa->judul]));
    }

    private function formulir(?KalenderPeristiwa $peristiwa, ?string $tanggal): View
    {
        $baru = $peristiwa === null;

        return view('admin.kalender.form', [
            'judul' => $baru ? __('kalender.judul_baru') : __('kalender.judul_ubah'),
            'konteks' => $baru ? null : $peristiwa->judul,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('kalender.judul') => route('admin.kalender'),
                __('kalender.judul_kelola') => route('admin.kalender.peristiwa.index'),
                $baru ? __('kalender.judul_baru') : __('kalender.judul_ubah'),
            ],
            'peristiwa' => $peristiwa,
            'tanggalAwal' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tanggal) === 1 ? $tanggal : null,
            'jenisPilihan' => JenisPeristiwa::options(JenisPeristiwa::kasusManual()),
            'sasaranPilihan' => SasaranPeristiwa::options(),
            'statusPilihan' => StatusPeristiwa::options(),
            'prodiPilihan' => Prodi::orderBy('nama')->pluck('nama', 'id'),
        ]);
    }

    private function kembali(KalenderPeristiwa $peristiwa, string $pesan): RedirectResponse
    {
        return redirect()
            ->route('admin.kalender', ['bulan' => $peristiwa->tanggal_mulai->format('Y-m')])
            ->with('sukses', $pesan);
    }
}
