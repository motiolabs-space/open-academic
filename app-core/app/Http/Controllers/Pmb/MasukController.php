<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pmb\MasukPendaftarRequest;
use App\Models\Pmb\PmbAkun;
use App\Support\SesiPendaftar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The admission portal's own sign-in.
 *
 * Separate from /masuk, which resolves NIM, NIDN and NIP to three campus
 * guards and never tries this one. An applicant is not a member of the campus
 * yet; a page that accepted both would make "which account did I just sign in
 * to" a question with two answers.
 */
class MasukController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard(SesiPendaftar::GUARD)->check()) {
            return redirect()->route('pmb.portal.index');
        }

        return view('pmb.auth.masuk');
    }

    public function store(MasukPendaftarRequest $request): RedirectResponse
    {
        $request->pastikanTidakDibatasi();

        $berhasil = Auth::guard(SesiPendaftar::GUARD)->attempt([
            'email' => mb_strtolower(trim((string) $request->string('email'))),
            'password' => (string) $request->string('password'),

            // A deactivated account fails exactly like a wrong password, so
            // the page cannot be used to learn which addresses applied.
            'is_active' => true,
        ], $request->boolean('ingat'));

        if (!$berhasil) {
            $request->catatGagal();

            throw ValidationException::withMessages(['email' => __('pmb.akun.gagal')]);
        }

        $request->bersihkanBatas();

        /** @var PmbAkun $akun */
        $akun = Auth::guard(SesiPendaftar::GUARD)->user();

        SesiPendaftar::masuk($request, $akun, $request->boolean('ingat'));

        return redirect()->intended(route('pmb.portal.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        SesiPendaftar::keluar($request);

        return redirect()->route('pmb.masuk');
    }
}
