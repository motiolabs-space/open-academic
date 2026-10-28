<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\DTOs\Keuangan\DispensasiData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\AjukanDispensasiRequest;
use App\Models\Keuangan\Tagihan;
use App\Services\Keuangan\DispensasiService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;

/** A student asking the finance office for a dispensation on their invoice. */
class DispensasiController extends Controller
{
    public function __invoke(AjukanDispensasiRequest $request, Tagihan $tagihan, DispensasiService $dispensasi): RedirectResponse
    {
        $dispensasi->ajukan($tagihan, Portal::user(), DispensasiData::fromRequest($request));

        return back()->with('sukses', __('keuangan.dispensasi.berhasil_diajukan'));
    }
}
