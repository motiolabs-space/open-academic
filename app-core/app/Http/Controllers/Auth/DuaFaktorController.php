<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Sdm\Staff;
use App\Services\Auth\DuaFaktorService;
use App\Services\Surat\PembuatQr;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Two screens: the challenge at sign-in, and enrolment from the profile.
 *
 * The pending identity between password and code lives in the session and
 * nowhere else. A hidden form field naming the account about to be signed in
 * is an invitation to post a different one.
 */
class DuaFaktorController extends Controller
{
    /** Kedaluwarsa tantangan, dalam detik. */
    private const BATAS_TANTANGAN = 300;

    public function __construct(
        private readonly DuaFaktorService $duaFaktor,
        private readonly PembuatQr $qr,
    ) {}

    /* ---------------------------------------------------------------------
     | Tantangan saat masuk
     |-------------------------------------------------------------------- */

    public function tantangan(Request $request): View|RedirectResponse
    {
        if (!$this->menunggu($request) instanceof Staff) {
            return redirect()->route('login');
        }

        return view('auth.dua-faktor-tantangan');
    }

    public function verifikasi(Request $request): RedirectResponse
    {
        $staff = $this->menunggu($request);

        if (!$staff instanceof Staff) {
            return redirect()->route('login')->with('galat',
                'Sesi verifikasi kedaluwarsa. Silakan masuk kembali.');
        }

        $request->validate(['kode' => ['required', 'string', 'max:32']]);

        /*
         * The challenge is rate limited separately from the password.
         *
         * Six digits is a million combinations, but only about thirty thousand
         * are live in any given window across the drift tolerance. Unlimited
         * guessing turns the second factor into a formality; the limit is what
         * makes it a factor.
         */
        $kunci = 'dua-faktor:'.$staff->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            return back()->with('galat', sprintf(
                'Terlalu banyak percobaan. Coba lagi dalam %d detik.',
                RateLimiter::availableIn($kunci),
            ));
        }

        if (!$this->duaFaktor->lolos($staff, (string) $request->string('kode'))) {
            RateLimiter::hit($kunci, 300);

            return back()->with('galat', 'Kode tidak cocok atau sudah terpakai.');
        }

        RateLimiter::clear($kunci);

        $ingat = (bool) ($request->session()->get('dua_faktor.ingat', false));
        $request->session()->forget('dua_faktor');

        Auth::guard('staff')->login($staff, $ingat);

        // Regenerated only now, at the point the sign-in actually completes.
        $request->session()->regenerate();

        $staff->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route(UserRole::Staff->homeRoute()));
    }

    /* ---------------------------------------------------------------------
     | Pendaftaran dari profil
     |-------------------------------------------------------------------- */

    public function kelola(Request $request): View
    {
        $staff = $this->staf();

        $mulai = $request->boolean('pasang') && !$staff->duaFaktorAktif()
            ? $this->duaFaktor->mulai($staff)
            : null;

        return view('auth.dua-faktor-kelola', [
            'judul' => 'Verifikasi Dua Langkah',
            'konteks' => $staff->duaFaktorAktif() ? 'Aktif' : 'Belum aktif',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Verifikasi Dua Langkah'],

            'aktif' => $staff->duaFaktorAktif(),
            'wajib' => (bool) config('dua_faktor.wajib'),
            'sisaPemulihan' => $this->duaFaktor->sisaPemulihan($staff),

            'rahasia' => $mulai['rahasia'] ?? null,
            'qr' => $mulai ? $this->qr->dataUri($mulai['uri'], 190) : null,

            // Ditampilkan sekali saja, dari flash — tidak pernah dibaca ulang
            // dari basis data, karena yang tersimpan hanya hash-nya.
            'kodePemulihan' => $request->session()->get('kode_pemulihan'),
        ]);
    }

    public function konfirmasi(Request $request): RedirectResponse
    {
        $staff = $this->staf();

        $request->validate(['kode' => ['required', 'string', 'max:32']]);

        $kode = $this->duaFaktor->konfirmasi($staff, (string) $request->string('kode'));

        if ($kode === null) {
            return back()->with('galat',
                'Kode tidak cocok. Periksa jam ponsel Anda, lalu coba kode terbaru.');
        }

        return redirect()->route('dua-faktor.kelola')
            ->with('kode_pemulihan', $kode)
            ->with('sukses', 'Verifikasi dua langkah aktif. Simpan kode pemulihan di bawah sekarang.');
    }

    public function pemulihanBaru(): RedirectResponse
    {
        $staff = $this->staf();

        abort_unless($staff->duaFaktorAktif(), 404);

        return redirect()->route('dua-faktor.kelola')
            ->with('kode_pemulihan', $this->duaFaktor->perbaruiPemulihan($staff))
            ->with('sukses', 'Kode pemulihan diganti. Yang lama tidak berlaku lagi.');
    }

    /**
     * Turns it off for one's own account, password in hand.
     *
     * The password is asked for again on purpose: without it, a borrowed
     * unlocked browser is enough to remove the second factor, which would make
     * the whole thing decorative.
     */
    public function matikan(Request $request): RedirectResponse
    {
        $staff = $this->staf();

        abort_if(config('dua_faktor.wajib'), 403);

        $request->validate(['password' => ['required', 'string']]);

        if (!Auth::guard('staff')->getProvider()->validateCredentials(
            $staff, ['password' => (string) $request->string('password')],
        )) {
            return back()->with('galat', 'Kata sandi salah.');
        }

        $this->duaFaktor->matikan($staff);

        return redirect()->route('dua-faktor.kelola')
            ->with('sukses', 'Verifikasi dua langkah dimatikan.');
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    /**
     * The account waiting between password and code, if the wait is still
     * valid.
     *
     * Expires on its own. A challenge left open on a shared machine is a
     * half-finished sign-in that anyone walking past can complete.
     */
    private function menunggu(Request $request): ?Staff
    {
        $tunggu = $request->session()->get('dua_faktor');

        if (!is_array($tunggu) || !isset($tunggu['staff_id'], $tunggu['sejak'])) {
            return null;
        }

        if (now()->timestamp - (int) $tunggu['sejak'] > self::BATAS_TANTANGAN) {
            $request->session()->forget('dua_faktor');

            return null;
        }

        return Staff::where('is_active', true)->find($tunggu['staff_id']);
    }

    private function staf(): Staff
    {
        $staff = Portal::user();

        abort_unless($staff instanceof Staff, 403);

        return $staff;
    }
}
