<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\Pmb\PmbAkun;
use App\Services\Pmb\GelombangPmbService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The applicant's home: their applications, and the waves they could join. */
class PortalController extends Controller
{
    public function __construct(private readonly GelombangPmbService $gelombang) {}

    public function __invoke(Request $request): View
    {
        /** @var PmbAkun $akun */
        $akun = $request->user();

        $akun->load([
            'pendaftaran' => fn ($q) => $q->with(['gelombang', 'prodiPilihan1', 'prodiDiterima'])->latest('id'),
        ]);

        return view('pmb.portal.index', [
            'akun' => $akun,
            'pendaftaran' => $akun->pendaftaran,
            'daftarGelombang' => $this->gelombang->terbukaDaring(),
            'notifikasi' => $akun->notifications()->latest()->limit(5)->get(),
        ]);
    }
}
