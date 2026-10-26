<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pmb\BatalPembayaranFormulirRequest;
use App\Http\Requests\Pmb\CatatPembayaranFormulirRequest;
use App\Models\Pmb\PmbPembayaran;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use App\Services\Pmb\PembayaranFormulirService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cashier's queue of form fees.
 *
 * Deliberately narrow: name, number, wave, amount, and the transfer receipt.
 * The finance office does not hold `pmb.view` and must not read an applicant's
 * family card or diploma to take their money — so this is a list of its own
 * rather than a column on the admissions screen.
 */
class PmbFormulirController extends Controller
{
    public function __construct(private readonly PembayaranFormulirService $pembayaran) {}

    public function index(Request $request): View
    {
        $aktor = Portal::user();

        abort_unless(
            ($aktor?->hasPermissionTo('keuangan.view', 'staff') ?? false)
                || ($aktor?->hasPermissionTo('pmb.view', 'staff') ?? false),
            403,
        );

        $menunggu = $this->pembayaran->antrean((string) $request->string('cari'));

        return view('admin.pmb.formulir', [
            'judul' => __('pmb.admin.antrean_formulir'),
            'konteks' => __('pmb.admin.antrean_formulir_konteks', ['jumlah' => $menunggu->total()]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('pmb.admin.antrean_formulir')],
            'menunggu' => $menunggu,
            'terakhir' => $this->pembayaran->terakhir(),
            'channel' => CatatPembayaranFormulirRequest::CHANNEL,
            'bolehCatat' => ($aktor?->hasPermissionTo('keuangan.manage', 'staff') ?? false)
                || ($aktor?->hasPermissionTo('pmb.manage', 'staff') ?? false),
            'filter' => $request->only('cari'),
        ]);
    }

    public function store(CatatPembayaranFormulirRequest $request, PmbPendaftar $pendaftar): RedirectResponse
    {
        /** @var Staff $staff */
        $staff = Portal::user();

        $this->pembayaran->catat($pendaftar, $request->dto(), $staff);

        return back()->with('sukses', __('pmb.admin.bayar_tercatat'));
    }

    public function batal(BatalPembayaranFormulirRequest $request, PmbPembayaran $pembayaran): RedirectResponse
    {
        /** @var Staff $staff */
        $staff = Portal::user();

        $this->pembayaran->batalkan($pembayaran, $staff, (string) $request->string('alasan'));

        return back()->with('sukses', __('pmb.admin.bayar_dibatalkan'));
    }
}
