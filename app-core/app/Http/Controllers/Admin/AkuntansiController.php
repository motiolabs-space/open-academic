<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JenisDokumenAkuntansi;
use App\Enums\StatusDokumenAkuntansi;
use App\Http\Controllers\Controller;
use App\Models\Akuntansi\DokumenAkuntansi;
use App\Models\Akuntansi\PemetaanAkuntansi;
use App\Services\Akuntansi\Contracts\AkuntansiClientInterface;
use App\Services\Akuntansi\EksporJurnal;
use App\Services\Akuntansi\PengirimAkuntansi;
use App\Support\Akuntansi;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The accounting queue, and what to do when it stops moving.
 *
 * Shaped after the Neo Feeder monitor, because it answers the same question: is
 * anything stuck, and what is the actual reason. A queue screen that shows only
 * counts sends whoever is looking at it to the log files.
 */
class AkuntansiController extends Controller
{
    public function __construct(
        private readonly PengirimAkuntansi $pengirim,
        private readonly EksporJurnal $ekspor,
    ) {}

    public function index(Request $request, AkuntansiClientInterface $klien): View
    {
        $this->izin('keuangan.view');

        $status = $request->string('status')->toString();

        return view('admin.akuntansi', [
            'judul' => 'Integrasi Akuntansi',
            'konteks' => 'Easy Accounting · driver '.config('akuntansi.driver'),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Akuntansi'],

            'driver' => Akuntansi::driver(),
            'aktif' => Akuntansi::aktif(),

            /*
             * Probed on load rather than cached: the whole point of the screen
             * is telling somebody why nothing is moving right now.
             *
             * Skipped entirely when inactive or faked — reaching out over the
             * network to check on a system the campus has not configured is a
             * request that can only hang.
             */
            'tersambung' => Akuntansi::mengirim() ? $klien->tersedia() : null,

            'perStatus' => collect(StatusDokumenAkuntansi::cases())
                ->mapWithKeys(fn (StatusDokumenAkuntansi $s): array => [
                    $s->value => DokumenAkuntansi::where('status', $s->value)->count(),
                ])
                ->all(),

            'nilaiMenunggu' => (int) DokumenAkuntansi::query()
                ->where('status', StatusDokumenAkuntansi::Menunggu->value)
                ->sum('nominal'),

            'dokumen' => DokumenAkuntansi::query()
                ->when($status !== '', fn ($q) => $q->where('status', $status))

                // Failures first: they are the only rows that need a person.
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StatusDokumenAkuntansi::Gagal->value])
                ->orderByDesc('id')
                ->limit(100)
                ->get(),

            'statusPilihan' => StatusDokumenAkuntansi::options(),
            'jenisPilihan' => JenisDokumenAkuntansi::options(),
            'akun' => config('akuntansi.akun'),
            'perlakuan' => config('akuntansi.perlakuan'),
            'jumlahPemetaan' => PemetaanAkuntansi::count(),
            'bolehKelola' => Portal::user()?->hasPermissionTo('keuangan.manage', 'staff') ?? false,
        ]);
    }

    /** Sends one batch now, without waiting for the scheduler. */
    public function kirim(): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $hasil = $this->pengirim->jalankan();

        return back()->with('sukses', sprintf(
            '%d terkirim, %d ditunda, %d gagal.',
            $hasil['terkirim'],
            $hasil['ditunda'],
            $hasil['gagal'],
        ));
    }

    /**
     * Puts a failed document back in the queue.
     *
     * Deliberately manual. A document fails because something is wrong on the
     * other side — an account code that does not exist, most often — and
     * automatic requeueing would spin against the same wall forever while
     * looking busy.
     */
    public function ulangi(DokumenAkuntansi $dokumen): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $this->pengirim->ulangi($dokumen);

        return back()->with('sukses', 'Dokumen dikembalikan ke antrean.');
    }

    public function ulangiSemua(): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $jumlah = 0;

        DokumenAkuntansi::where('status', StatusDokumenAkuntansi::Gagal->value)
            ->each(function (DokumenAkuntansi $dokumen) use (&$jumlah): void {
                $this->pengirim->ulangi($dokumen);
                $jumlah++;
            });

        return back()->with('sukses', $jumlah.' dokumen dikembalikan ke antrean.');
    }

    /**
     * The journal sheet as CSV.
     *
     * The way out that keeps working when the API is unreachable, the API key
     * has expired, or the campus never connected one in the first place. Every
     * queued document, balanced, in the columns an accountant expects.
     */
    public function eksporJurnal(Request $request): Response
    {
        $this->izin('keuangan.view');

        return $this->ekspor->csv(
            $request->date('dari'),
            $request->date('sampai'),
        );
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
