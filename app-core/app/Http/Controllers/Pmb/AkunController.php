<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pmb\DaftarAkunRequest;
use App\Services\Pmb\AkunPendaftarService;
use App\Support\SesiPendaftar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Opening an applicant account. */
class AkunController extends Controller
{
    public function __construct(private readonly AkunPendaftarService $akun) {}

    public function create(): View|RedirectResponse
    {
        if (Auth::guard(SesiPendaftar::GUARD)->check()) {
            return redirect()->route('pmb.portal.index');
        }

        return view('pmb.auth.daftar');
    }

    public function store(DaftarAkunRequest $request): RedirectResponse
    {
        $akun = $this->akun->daftar($request->dto());

        SesiPendaftar::masuk($request, $akun);

        return redirect()->route('pmb.verifikasi.pemberitahuan');
    }
}
