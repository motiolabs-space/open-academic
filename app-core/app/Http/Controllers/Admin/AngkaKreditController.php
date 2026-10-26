<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sdm\AngkaKreditManualRequest;
use App\Http\Requests\Sdm\NuptkRequest;
use App\Models\Sdm\AngkaKreditDosen;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Sdm\AngkaKreditService;
use App\Services\Sdm\KepegawaianService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The credit ledger, campus-wide and per lecturer, and the penyetaraan sheet.
 *
 * A ledger screen: it shows lines and sums, says how far each lecturer is from
 * the next rank's threshold, and produces the file the PAK operator uploads.
 * Assessing the credit is the ministry platform's job and nothing here
 * pretends otherwise.
 */
class AngkaKreditController extends Controller
{
    public function __construct(private readonly AngkaKreditService $angkaKredit) {}

    public function index(): View
    {
        $this->authorize('viewAny', AngkaKreditDosen::class);

        $rekap = $this->angkaKredit->rekapSemua();
        $lembar = $this->angkaKredit->lembarPenyetaraan($rekap);

        return view('admin.angka-kredit.index', [
            'judul' => __('angka_kredit.judul'),
            'konteks' => __('angka_kredit.konteks', [
                'jumlah' => $rekap->count(),
                'siap' => $rekap->where('siap', true)->count(),
            ]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('angka_kredit.judul')],
            'rekap' => $rekap,
            'perJenjang' => $this->angkaKredit->rekapPerJenjang($rekap),
            'jumlahLembar' => count($lembar['baris']),
            'tertahan' => $lembar['tertahan'],
        ]);
    }

    public function show(Dosen $dosen): View
    {
        $this->authorize('rekap', [AngkaKreditDosen::class, $dosen]);

        return view('admin.angka-kredit.show', [
            'judul' => __('angka_kredit.judul'),
            'konteks' => $dosen->namaLengkap(),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('angka_kredit.judul') => route('admin.angka-kredit.index'),
                $dosen->nama,
            ],
            'dosen' => $dosen,
            'rekap' => $this->angkaKredit->rekap($dosen),
            'buku' => $dosen->angkaKredit()->with('pencatat')->orderByDesc('tanggal')->get(),
        ]);
    }

    public function susun(Dosen $dosen): RedirectResponse
    {
        $this->authorize('susun', AngkaKreditDosen::class);

        $this->angkaKredit->susun($dosen);

        return back()->with('sukses', __('angka_kredit.pesan.disusun', ['nama' => $dosen->nama]));
    }

    public function susunSemua(): RedirectResponse
    {
        $this->authorize('susun', AngkaKreditDosen::class);

        $jumlah = $this->angkaKredit->susunSemua();

        return back()->with('sukses', __('angka_kredit.pesan.disusun_semua', ['jumlah' => $jumlah]));
    }

    public function store(AngkaKreditManualRequest $request, Dosen $dosen): RedirectResponse
    {
        $staf = Portal::user();
        abort_unless($staf instanceof Staff, 403);

        $this->angkaKredit->catatManual($dosen, $staf, $request->toDto());

        return back()->with('sukses', __('angka_kredit.pesan.dicatat'));
    }

    public function nuptk(NuptkRequest $request, Dosen $dosen, KepegawaianService $kepegawaian): RedirectResponse
    {
        $kepegawaian->tetapkanNuptk($dosen, $request->validated('nuptk'));

        return back()->with('sukses', __('angka_kredit.pesan.nuptk_disimpan'));
    }

    public function destroy(AngkaKreditDosen $baris): RedirectResponse
    {
        $this->authorize('delete', $baris);

        $this->angkaKredit->hapusManual($baris);

        return back()->with('sukses', __('angka_kredit.pesan.dihapus'));
    }

    public function ekspor(): BinaryFileResponse
    {
        $this->authorize('ekspor', AngkaKreditDosen::class);

        return response()
            ->download($this->angkaKredit->eksporPenyetaraan(), 'penyetaraan-ak-'.now()->format('Y-m-d').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
