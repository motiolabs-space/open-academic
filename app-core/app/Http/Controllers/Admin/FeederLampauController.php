<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Feeder\HasilKlaimLampau;
use App\DTOs\Feeder\KlaimLampauData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\JalankanAdopsiFeederRequest;
use App\Http\Requests\Admin\KlaimLampauFeederRequest;
use App\Models\Akademik\Prodi;
use App\Models\Feeder\FeederPastStudent;
use App\Services\Feeder\FeederKlaimLampau;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Claiming PDDIKTI's past students, ahead of adopting them.
 *
 * Same shape as the adoption screen — one programme and intake at a time,
 * read inline — plus a selection: the operator ticks the rows to claim, and
 * the claim either goes out for all of them or reports every problem and
 * sends nothing.
 */
class FeederLampauController extends Controller
{
    public function __construct(private readonly FeederKlaimLampau $lampau) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', FeederPastStudent::class);

        $prodi = $request->filled('prodi')
            ? Prodi::query()->whereUuid((string) $request->query('prodi'))->first()
            : null;
        $periode = preg_match('/^\d{4}[123]$/', (string) $request->query('periode')) ? (string) $request->query('periode') : null;

        $laporan = $prodi !== null && $periode !== null ? $this->lampau->laporan($prodi, $periode) : collect();

        return view('admin.feeder-lampau', [
            'judul' => __('feeder.lampau.judul'),
            'konteks' => __('feeder.lampau.konteks'),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Neo Feeder PDDIKTI' => route('admin.feeder'),
                __('feeder.lampau.judul'),
            ],
            'daftarProdi' => Prodi::query()->orderBy('nama')->get(['id', 'uuid', 'nama', 'kode_pddikti']),
            'prodi' => $prodi,
            'periode' => $periode,
            'laporan' => $laporan,
            'percobaan' => $this->lampau->percobaanTerakhir($laporan),
            'jumlah' => $laporan->countBy(fn (FeederPastStudent $b): string => $b->status->value),
            'aktif' => (bool) config('feeder.enabled'),
        ]);
    }

    public function baca(JalankanAdopsiFeederRequest $request): RedirectResponse
    {
        $this->authorize('create', FeederPastStudent::class);

        $prodi = Prodi::query()->whereUuid($request->validated('prodi'))->firstOrFail();
        $periode = $request->validated('periode');

        $hasil = $this->lampau->baca($prodi, $periode);

        return redirect()
            ->route('admin.feeder.lampau', ['prodi' => $prodi->uuid, 'periode' => $periode])
            ->with('sukses', __('feeder.lampau.pesan.dibaca', [
                'prodi' => $hasil['prodi'],
                'periode' => $hasil['periode'],
                'feeder' => $hasil['feeder'],
                'cocok' => $hasil['cocok'],
                'ambigu' => $hasil['ambigu'],
                'tanpa' => $hasil['tanpa_lokal'],
            ]));
    }

    public function klaim(KlaimLampauFeederRequest $request): RedirectResponse
    {
        $this->authorize('klaim', FeederPastStudent::class);

        $prodi = Prodi::query()->whereUuid($request->validated('prodi'))->firstOrFail();

        $hasil = $this->lampau->klaim(KlaimLampauData::fromArray($request->validated(), $prodi), Portal::user(), Portal::term());

        $kembali = redirect()->route('admin.feeder.lampau', ['prodi' => $prodi->uuid, 'periode' => $request->validated('periode')]);

        if (!$hasil->berhasil()) {
            return $kembali
                ->withInput()
                ->with('galat_klaim', $hasil->galat)
                ->with('galat', __('feeder.lampau.pesan.tidak_dikirim', ['jumlah' => count($hasil->galat)]));
        }

        return $kembali
            ->with('ditolak_klaim', $hasil->ditolak)
            ->with($this->perluPerhatian($hasil) ? 'peringatan' : 'sukses', $this->ringkas($hasil));
    }

    private function perluPerhatian(HasilKlaimLampau $hasil): bool
    {
        return $hasil->ditolak !== [] || $hasil->adopsiGagal !== null
            || $hasil->menungguKeputusan !== [] || $hasil->belumTeradopsi !== [];
    }

    private function ringkas(HasilKlaimLampau $hasil): string
    {
        $pesan = [__('feeder.lampau.pesan.diklaim', [
            'diklaim' => $hasil->diklaim,
            'ditolak' => count($hasil->ditolak),
            'dilewati' => $hasil->dilewati,
        ])];

        if ($hasil->adopsi !== null) {
            $pesan[] = __('feeder.lampau.pesan.diadopsi', ['jumlah' => $hasil->adopsi['diadopsi']]);
        }

        if ($hasil->adopsiGagal !== null) {
            $pesan[] = __('feeder.lampau.pesan.adopsi_gagal', ['pesan' => $hasil->adopsiGagal]);
        }

        if ($hasil->menungguKeputusan !== []) {
            $pesan[] = __('feeder.lampau.pesan.menunggu_keputusan', ['nim' => implode(', ', $hasil->menungguKeputusan)]);
        }

        if ($hasil->belumTeradopsi !== [] && $hasil->adopsiGagal === null) {
            $pesan[] = __('feeder.lampau.pesan.belum_teradopsi', ['nim' => implode(', ', $hasil->belumTeradopsi)]);
        }

        return implode(' ', $pesan);
    }
}
