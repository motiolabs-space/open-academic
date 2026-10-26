<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Enums\JenisRiwayat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sdm\RiwayatKepegawaianRequest;
use App\Models\Sdm\Dosen;
use App\Services\Sdm\RiwayatKepegawaianService;
use App\Support\Portal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A lecturer's own personnel file: what SISTER's "Profil Dosen" asks for.
 *
 * Every entry filed here waits for the personnel office. Scoped to the
 * signed-in lecturer with no owner identifier in any URL — the only
 * identifiers are of rows, and the policy checks each one is theirs.
 */
class KepegawaianController extends Controller
{
    public function __construct(private readonly RiwayatKepegawaianService $riwayat) {}

    public function index(Request $request): View
    {
        $dosen = $this->dosen();

        return view('dosen.kepegawaian', [
            'judul' => __('kepegawaian.judul'),
            'konteks' => $dosen->namaLengkap(),
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), __('kepegawaian.judul')],
            'dosen' => $dosen,
            'riwayat' => $this->riwayat->riwayat($dosen),
            'sunting' => $this->sunting($request),
        ]);
    }

    public function store(RiwayatKepegawaianRequest $request, JenisRiwayat $jenis): RedirectResponse
    {
        $this->riwayat->ajukan($this->dosen(), $request->toDto());

        return $this->kembali($jenis, __('kepegawaian.pesan.diajukan', ['jenis' => $jenis->label()]));
    }

    public function update(RiwayatKepegawaianRequest $request, JenisRiwayat $jenis, Model $riwayat): RedirectResponse
    {
        $this->riwayat->ubah($riwayat, $request->toDto());

        return $this->kembali($jenis, __('kepegawaian.pesan.diubah', ['jenis' => $jenis->label()]));
    }

    public function destroy(JenisRiwayat $jenis, Model $riwayat): RedirectResponse
    {
        $this->authorize('delete', $riwayat);

        $this->riwayat->hapus($riwayat);

        return $this->kembali($jenis, __('kepegawaian.pesan.dihapus'));
    }

    /**
     * The row being edited, from `?ubah=<jenis>:<uuid>`.
     *
     * Checked against the policy here, so a guessed identifier opens nothing —
     * the form simply does not prefill.
     *
     * @return array{jenis: JenisRiwayat, baris: Model}|null
     */
    private function sunting(Request $request): ?array
    {
        [$jenis, $uuid] = array_pad(explode(':', (string) $request->query('ubah'), 2), 2, null);

        $jenis = JenisRiwayat::tryFrom((string) $jenis);
        $baris = $jenis?->model()::query()->where('uuid', (string) $uuid)->first();

        return $baris !== null && $request->user()?->can('update', $baris)
            ? ['jenis' => $jenis, 'baris' => $baris]
            : null;
    }

    private function kembali(JenisRiwayat $jenis, string $pesan): RedirectResponse
    {
        return redirect()->to(route('dosen.kepegawaian').'#'.$jenis->value)->with('sukses', $pesan);
    }

    private function dosen(): Dosen
    {
        $dosen = Portal::user();

        abort_unless($dosen instanceof Dosen, 403);

        return $dosen;
    }
}
