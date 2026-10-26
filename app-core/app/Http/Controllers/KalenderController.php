<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Akademik\FilterKalender;
use App\DTOs\Akademik\PeristiwaKalender;
use App\Enums\JenisPeristiwa;
use App\Enums\SasaranPeristiwa;
use App\Enums\UserRole;
use App\Models\Akademik\KalenderPeristiwa;
use App\Models\Akademik\Prodi;
use App\Services\Akademik\KalenderService;
use App\Services\Akademik\LanggananKalenderService;
use App\Support\Format;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The academic calendar, one screen for the three portals.
 *
 * Same controller under three route prefixes, like the helpdesk: what differs
 * between portals is who is reading, and KalenderService decides that from the
 * reader — not from which URL they came in through.
 */
class KalenderController extends Controller
{
    public function __construct(
        private readonly KalenderService $kalender,
        private readonly LanggananKalenderService $langganan,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', KalenderPeristiwa::class);

        $aktor = Portal::user();
        $peran = Portal::role();
        $terlihat = SasaranPeristiwa::terlihatOleh($peran);

        $bulan = FilterKalender::bulanDari($request);
        $filter = FilterKalender::fromRequest($request);

        // A student's programme is fixed by KalenderService; an audience the
        // portal cannot see is dropped rather than answered with nothing.
        if ($filter->sasaran !== null && !in_array($filter->sasaran, $terlihat, true)) {
            $filter = new FilterKalender($filter->dari, $filter->sampai, null, $filter->prodiId, $filter->jenis);
        }

        $tampilan = $request->string('tampilan')->toString() === 'daftar' ? 'daftar' : 'bulan';

        $peristiwa = $this->kalender->untuk($aktor, $filter, Gate::allows('viewDraft', KalenderPeristiwa::class));

        $awal = $bulan->copy()->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth()->startOfDay();

        $prefix = $this->prefix($peran);

        return view('kalender.index', [
            'judul' => __('kalender.judul'),
            'konteks' => __('kalender.konteks', ['bulan' => Format::bulanTahun($bulan)]),
            'breadcrumb' => [$this->beranda($peran) => route($peran->homeRoute()), __('kalender.judul')],
            'prefix' => $prefix,
            'bulan' => $bulan,
            'filter' => $filter,
            'tampilan' => $tampilan,
            'grid' => $tampilan === 'bulan' ? $this->kalender->grid($bulan, $peristiwa) : [],
            'daftar' => $peristiwa->filter(fn (PeristiwaKalender $p): bool => $p->beririsan($awal, $akhir))->values(),
            'sasaranPilihan' => $peran === UserRole::Mahasiswa
                ? []
                : SasaranPeristiwa::options(array_values(array_filter($terlihat, fn (SasaranPeristiwa $s): bool => $s !== SasaranPeristiwa::Semua))),
            'prodiPilihan' => $peran === UserRole::Mahasiswa ? [] : Prodi::orderBy('nama')->pluck('nama', 'id'),
            'jenisPilihan' => JenisPeristiwa::options($this->jenisTerlihat($peran)),
            'langganan' => $this->langganan->aktif($aktor),
            'bolehKelola' => Gate::allows('create', KalenderPeristiwa::class),
        ]);
    }

    /** Issues a new feed link, revoking any older one; the link is shown once. */
    public function langganan(): RedirectResponse
    {
        $this->authorize('subscribe', KalenderPeristiwa::class);

        $token = $this->langganan->terbitkan(Portal::user());

        return redirect()
            ->route($this->prefix(Portal::role()).'.kalender')
            ->with('tautan_kalender', route('kalender.ics', ['token' => $token]))
            ->with('sukses', __('kalender.sukses.langganan'));
    }

    public function cabut(): RedirectResponse
    {
        $this->authorize('subscribe', KalenderPeristiwa::class);

        $this->langganan->cabut(Portal::user());

        return redirect()
            ->route($this->prefix(Portal::role()).'.kalender')
            ->with('sukses', __('kalender.sukses.cabut'));
    }

    /**
     * The kinds worth offering in the filter: admission waves are staff-only,
     * and grading windows are aimed at lecturers.
     *
     * @return list<JenisPeristiwa>
     */
    private function jenisTerlihat(UserRole $peran): array
    {
        $tersembunyi = match ($peran) {
            UserRole::Staff => [],
            UserRole::Dosen => [JenisPeristiwa::Pmb],
            UserRole::Mahasiswa => [JenisPeristiwa::Pmb, JenisPeristiwa::Nilai],
        };

        return array_values(array_filter(
            JenisPeristiwa::cases(),
            fn (JenisPeristiwa $j): bool => !in_array($j, $tersembunyi, true),
        ));
    }

    private function prefix(UserRole $peran): string
    {
        return match ($peran) {
            UserRole::Staff => 'admin',
            UserRole::Dosen => 'dosen',
            UserRole::Mahasiswa => 'mahasiswa',
        };
    }

    private function beranda(UserRole $peran): string
    {
        return match ($peran) {
            UserRole::Staff => 'Dasbor',
            UserRole::Dosen => 'Portal Dosen',
            UserRole::Mahasiswa => 'Portal Mahasiswa',
        };
    }
}
