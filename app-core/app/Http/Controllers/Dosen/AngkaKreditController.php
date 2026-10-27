<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Sdm\AngkaKreditDosen;
use App\Models\Sdm\Dosen;
use App\Services\Sdm\AngkaKreditService;
use App\Support\Portal;
use Illuminate\View\View;

/** A lecturer's own credit ledger and how far it is from the next rank. Read-only. */
class AngkaKreditController extends Controller
{
    public function __construct(private readonly AngkaKreditService $angkaKredit) {}

    public function __invoke(): View
    {
        $dosen = Portal::user();
        abort_unless($dosen instanceof Dosen, 403);

        $this->authorize('rekap', [AngkaKreditDosen::class, $dosen]);

        return view('dosen.angka-kredit', [
            'judul' => __('angka_kredit.judul_dosen'),
            'konteks' => $dosen->namaLengkap(),
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), __('angka_kredit.judul_dosen')],
            'dosen' => $dosen,
            'rekap' => $this->angkaKredit->rekap($dosen),
            'buku' => $dosen->angkaKredit()->orderByDesc('tanggal')->get(),
        ]);
    }
}
