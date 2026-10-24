<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AjukanPerubahanFeederRequest;
use App\Models\Feeder\FeederChangeRequest;
use App\Services\Feeder\FeederPerubahanService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;

/**
 * The three things an operator does with a PDM/PJK request: file it at
 * PDDIKTI admin, send it through the web service where that is allowed, and
 * declare it done once PDDIKTI holds the new value.
 */
class FeederPerubahanController extends Controller
{
    public function __construct(private readonly FeederPerubahanService $perubahan) {}

    public function ajukan(AjukanPerubahanFeederRequest $request, FeederChangeRequest $perubahan): RedirectResponse
    {
        $this->authorize('update', $perubahan);

        $this->perubahan->ajukan(
            $perubahan,
            Portal::user(),
            $request->validated('nomor_surat'),
            $request->validated('catatan'),
        );

        return back()->with('sukses', __('feeder.perubahan.pesan.diajukan', [
            'kolom' => $perubahan->labelKolom(),
            'nim' => $perubahan->mahasiswa()->value('nim'),
        ]));
    }

    public function selesai(FeederChangeRequest $perubahan): RedirectResponse
    {
        $this->authorize('update', $perubahan);

        $this->perubahan->selesaikan($perubahan, Portal::user());

        return back()->with('sukses', __('feeder.perubahan.pesan.selesai', [
            'kolom' => $perubahan->labelKolom(),
            'nim' => $perubahan->mahasiswa()->value('nim'),
        ]));
    }

    public function kirim(FeederChangeRequest $perubahan): RedirectResponse
    {
        $this->authorize('update', $perubahan);

        $hasil = $this->perubahan->kirim($perubahan, Portal::user(), Portal::term());

        if ($hasil->error_message !== null) {
            return back()->with('peringatan', __('feeder.perubahan.pesan.ditolak', [
                'kolom' => $hasil->labelKolom(),
                'sebab' => $hasil->error_message,
            ]));
        }

        return back()->with('sukses', __('feeder.perubahan.pesan.terkirim', [
            'kolom' => $hasil->labelKolom(),
            'nim' => $hasil->mahasiswa()->value('nim'),
        ]));
    }
}
