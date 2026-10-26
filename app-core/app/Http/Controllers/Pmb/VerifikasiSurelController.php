<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\Pmb\PmbAkun;
use App\Services\Pmb\AkunPendaftarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Proving the address. The link is signed and expires; it also has to be
 * opened by the account it was sent to, so a forwarded link verifies nobody
 * else.
 */
class VerifikasiSurelController extends Controller
{
    public function __construct(private readonly AkunPendaftarService $akun) {}

    public function pemberitahuan(Request $request): View|RedirectResponse
    {
        /** @var PmbAkun $akun */
        $akun = $request->user();

        if ($akun->hasVerifiedEmail()) {
            return redirect()->route('pmb.portal.index');
        }

        return view('pmb.auth.verifikasi', ['akun' => $akun]);
    }

    public function verifikasi(Request $request, PmbAkun $akun, string $hash): RedirectResponse
    {
        abort_unless($request->user()?->is($akun), 403, __('pmb.verifikasi.tidak_cocok'));
        abort_unless($this->akun->verifikasi($akun, $hash), 403, __('pmb.verifikasi.tidak_cocok'));

        return redirect()->route('pmb.portal.index')->with('sukses', __('pmb.verifikasi.berhasil'));
    }

    public function kirimUlang(Request $request): RedirectResponse
    {
        /** @var PmbAkun $akun */
        $akun = $request->user();

        if (!$akun->hasVerifiedEmail()) {
            $akun->sendEmailVerificationNotification();
        }

        return back()->with('sukses', __('pmb.verifikasi.terkirim'));
    }
}
