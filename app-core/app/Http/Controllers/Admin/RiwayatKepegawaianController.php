<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Sdm\HasilValidasiRiwayat;
use App\Enums\JenisRiwayat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sdm\RiwayatKepegawaianRequest;
use App\Http\Requests\Sdm\TolakRiwayatRequest;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PangkatDosen;
use App\Models\Sdm\Staff;
use App\Services\Sdm\RiwayatKepegawaianService;
use App\Support\Portal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The personnel office's side: one lecturer's whole file, and the queue of
 * entries lecturers have filed.
 *
 * What the office records here is approved as it is written — it is the
 * reviewer. What lecturers file waits in the queue for a decision.
 */
class RiwayatKepegawaianController extends Controller
{
    public function __construct(private readonly RiwayatKepegawaianService $riwayat) {}

    public function antrean(Request $request): View
    {
        // Any of the fourteen models resolves to the one shared policy.
        $this->authorize('viewAny', PangkatDosen::class);

        $jenis = JenisRiwayat::tryFrom($request->string('jenis')->toString());
        $antrean = $this->riwayat->antrean($jenis);

        return view('admin.kepegawaian.antrean', [
            'judul' => __('kepegawaian.judul_antrean'),
            'konteks' => __('kepegawaian.konteks_antrean', ['jumlah' => $antrean->count()]),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Kepegawaian Dosen' => route('admin.dosen'),
                __('kepegawaian.judul_antrean'),
            ],
            'antrean' => $antrean,
            'jenis' => $jenis,
        ]);
    }

    public function show(Request $request, Dosen $dosen): View
    {
        $this->authorize('viewAny', PangkatDosen::class);

        return view('admin.kepegawaian.show', [
            'judul' => __('kepegawaian.judul'),
            'konteks' => $dosen->namaLengkap(),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Kepegawaian Dosen' => route('admin.dosen'),
                $dosen->nama,
            ],
            'dosen' => $dosen->loadMissing(['prodi', 'unitKerja']),
            'riwayat' => $this->riwayat->riwayat($dosen),
            'sunting' => $this->sunting($request, $dosen),
        ]);
    }

    public function store(RiwayatKepegawaianRequest $request, Dosen $dosen, JenisRiwayat $jenis): RedirectResponse
    {
        $hasil = $this->riwayat->catat($dosen, $this->staf(), $request->toDto());

        return $this->kembali($dosen, $jenis, __('kepegawaian.pesan.dicatat', ['jenis' => $jenis->label()]), $hasil);
    }

    public function update(RiwayatKepegawaianRequest $request, JenisRiwayat $jenis, Model $riwayat): RedirectResponse
    {
        $hasil = $this->riwayat->ubah($riwayat, $request->toDto(), $this->staf());

        return $this->kembali($riwayat->dosen, $jenis, __('kepegawaian.pesan.dicatat', ['jenis' => $jenis->label()]), $hasil);
    }

    public function destroy(JenisRiwayat $jenis, Model $riwayat): RedirectResponse
    {
        $this->authorize('delete', $riwayat);

        $this->riwayat->hapus($riwayat);

        return $this->kembali($riwayat->dosen, $jenis, __('kepegawaian.pesan.dihapus'));
    }

    public function setujui(JenisRiwayat $jenis, Model $riwayat): RedirectResponse
    {
        $this->authorize('validasi', $riwayat);

        $hasil = $this->riwayat->setujui($riwayat, $this->staf());

        return back()
            ->with('sukses', __('kepegawaian.pesan.disetujui'))
            ->with('catatan_validasi', $hasil->catatan);
    }

    public function tolak(TolakRiwayatRequest $request, JenisRiwayat $jenis, Model $riwayat): RedirectResponse
    {
        $this->riwayat->tolak($riwayat, $this->staf(), (string) $request->validated('alasan'));

        return back()->with('sukses', __('kepegawaian.pesan.ditolak'));
    }

    /** @return array{jenis: JenisRiwayat, baris: Model}|null */
    private function sunting(Request $request, Dosen $dosen): ?array
    {
        [$jenis, $uuid] = array_pad(explode(':', (string) $request->query('ubah'), 2), 2, null);

        $jenis = JenisRiwayat::tryFrom((string) $jenis);
        $baris = $jenis?->model()::query()
            ->where('dosen_id', $dosen->id)
            ->where('uuid', (string) $uuid)
            ->first();

        return $baris !== null && $request->user()?->can('update', $baris)
            ? ['jenis' => $jenis, 'baris' => $baris]
            : null;
    }

    private function kembali(Dosen $dosen, JenisRiwayat $jenis, string $pesan, ?HasilValidasiRiwayat $hasil = null): RedirectResponse
    {
        return redirect()
            ->to(route('admin.dosen.kepegawaian', $dosen).'#'.$jenis->value)
            ->with('sukses', $pesan)
            ->with('catatan_validasi', $hasil?->catatan ?? []);
    }

    private function staf(): Staff
    {
        $staf = Portal::user();

        abort_unless($staf instanceof Staff, 403);

        return $staf;
    }
}
