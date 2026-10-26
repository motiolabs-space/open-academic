<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pmb\AturUlangSandiRequest;
use App\Http\Requests\Pmb\LupaSandiRequest;
use App\Models\Pmb\PmbAkun;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Forgotten passwords, through the `pendaftar` broker and its own token table.
 *
 * The answer to "send me a link" is the same whether or not the address has an
 * account. Anything else turns the form into a way of checking who applied.
 */
class SandiController extends Controller
{
    public function lupa(): View
    {
        return view('pmb.auth.lupa-sandi');
    }

    public function kirimTautan(LupaSandiRequest $request): RedirectResponse
    {
        Password::broker('pendaftar')->sendResetLink(['email' => (string) $request->string('email')]);

        return back()->with('sukses', __('pmb.sandi.terkirim'));
    }

    public function formAturUlang(string $token): View
    {
        return view('pmb.auth.atur-ulang-sandi', ['token' => $token]);
    }

    public function aturUlang(AturUlangSandiRequest $request): RedirectResponse
    {
        $status = Password::broker('pendaftar')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (PmbAkun $akun, string $password): void {
                $akun->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Following the link proved the inbox, which is all
                // verification ever proves.
                $akun->markEmailAsVerified();

                event(new PasswordReset($akun));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('pmb.sandi.gagal')]);
        }

        return redirect()->route('pmb.masuk')->with('sukses', __('pmb.sandi.berhasil'));
    }
}
