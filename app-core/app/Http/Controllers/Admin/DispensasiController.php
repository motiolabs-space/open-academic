<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Keuangan\DispensasiData;
use App\Enums\StatusDispensasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\BeriDispensasiRequest;
use App\Http\Requests\Keuangan\CabutDispensasiRequest;
use App\Http\Requests\Keuangan\PutuskanDispensasiRequest;
use App\Models\Keuangan\DispensasiTagihan;
use App\Models\Keuangan\Tagihan;
use App\Services\Keuangan\DispensasiService;
use App\Support\Format;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The finance office's dispensation desk: requests to decide, dispensations
 * in force, and the recent record.
 */
class DispensasiController extends Controller
{
    public function __construct(
        private readonly DispensasiService $dispensasi,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Tagihan::class);

        $muat = ['tagihan.mahasiswa.prodi', 'tagihan.tahunAkademik', 'pemutus', 'pemohon'];

        $menunggu = DispensasiTagihan::query()->with($muat)->menunggu()->orderBy('id')->get();
        $berlaku = DispensasiTagihan::query()->with($muat)->berlaku()->orderBy('sampai')->get();

        return view('admin.dispensasi', [
            'judul' => __('keuangan.dispensasi.judul'),
            'konteks' => __('keuangan.dispensasi.konteks', ['aktif' => $berlaku->count(), 'menunggu' => $menunggu->count()]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Keuangan' => route('admin.keuangan'), __('keuangan.dispensasi.judul')],
            'menunggu' => $menunggu,
            'berlaku' => $berlaku,
            'riwayat' => DispensasiTagihan::query()
                ->with([...$muat, 'pencabut'])
                ->where(fn ($q) => $q
                    ->whereIn('status', [StatusDispensasi::Ditolak->value, StatusDispensasi::Dicabut->value])
                    ->orWhere(fn ($lewat) => $lewat
                        ->where('status', StatusDispensasi::Disetujui->value)
                        ->where('sampai', '<', now()->toDateString())))
                ->latest('updated_at')
                ->limit(20)
                ->get(),
            'bolehKelola' => Portal::user()->can('grantDispensation', Tagihan::class),
            'nomorAwal' => $request->string('tagihan')->toString(),
        ]);
    }

    public function beri(BeriDispensasiRequest $request): RedirectResponse
    {
        $tagihan = $request->tagihan();
        $dispensasi = $this->dispensasi->beri($tagihan, DispensasiData::fromRequest($request), Portal::user());

        return redirect()->route('admin.keuangan.dispensasi')->with('sukses', __('keuangan.dispensasi.berhasil_diberikan', [
            'nomor' => $tagihan->nomor,
            'tanggal' => Format::tanggal($dispensasi->sampai),
        ]));
    }

    public function putuskan(PutuskanDispensasiRequest $request, DispensasiTagihan $dispensasi): RedirectResponse
    {
        if (!$request->boolean('setujui')) {
            $this->dispensasi->tolak($dispensasi, Portal::user(), (string) $request->catatan());

            return back()->with('sukses', __('keuangan.dispensasi.berhasil_ditolak'));
        }

        $dispensasi = $this->dispensasi->setujui($dispensasi, Portal::user(), $request->sampai(), $request->catatan());

        return back()->with('sukses', __('keuangan.dispensasi.berhasil_diberikan', [
            'nomor' => $dispensasi->tagihan->nomor,
            'tanggal' => Format::tanggal($dispensasi->sampai),
        ]));
    }

    public function cabut(CabutDispensasiRequest $request, DispensasiTagihan $dispensasi): RedirectResponse
    {
        $dispensasi = $this->dispensasi->cabut($dispensasi, Portal::user(), $request->alasan());

        return back()->with('sukses', __('keuangan.dispensasi.berhasil_dicabut', ['nomor' => $dispensasi->tagihan->nomor]));
    }
}
