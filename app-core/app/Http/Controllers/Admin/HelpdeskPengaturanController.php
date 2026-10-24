<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\FaqRequest;
use App\Http\Requests\Helpdesk\KategoriRequest;
use App\Models\Helpdesk\HelpdeskFaq;
use App\Models\Helpdesk\HelpdeskKategori;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Sdm\UnitKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Categories and FAQ — the two tables that decide where tickets go and how
 * many never need to be filed.
 *
 * Campus-wide settings, so they sit behind `helpdesk.manage` rather than the
 * per-unit `tangani`: the finance office should not be able to re-route
 * academic tickets to itself.
 */
class HelpdeskPengaturanController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage', HelpdeskTiket::class);

        $kategori = HelpdeskKategori::query()->with('unitKerja')->withCount('tiket')->urut()->get();

        return view('admin.helpdesk.pengaturan', [
            'judul' => __('helpdesk.pengaturan.judul'),
            'konteks' => __('helpdesk.pengaturan.konteks'),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('helpdesk.judul') => route('admin.helpdesk'),
                __('helpdesk.pengaturan.judul'),
            ],
            'kategori' => $kategori,
            'kategoriPilihan' => $kategori->pluck('nama', 'id')->all(),
            'faq' => HelpdeskFaq::query()->with('kategori')->urut()->get(),
            'unitPilihan' => UnitKerja::query()->aktif()->orderBy('nama')->pluck('nama', 'id')->all(),
            'slaBawaan' => (int) config('helpdesk.sla_jam_bawaan'),
        ]);
    }

    public function simpanKategori(KategoriRequest $request): RedirectResponse
    {
        $this->authorize('manage', HelpdeskTiket::class);

        HelpdeskKategori::create($request->nilai());

        return back()->with('sukses', __('helpdesk.pesan.kategori_disimpan'));
    }

    public function perbaruiKategori(KategoriRequest $request, HelpdeskKategori $kategori): RedirectResponse
    {
        $this->authorize('manage', HelpdeskTiket::class);

        $kategori->update($request->nilai());

        return back()->with('sukses', __('helpdesk.pesan.kategori_disimpan'));
    }

    public function simpanFaq(FaqRequest $request): RedirectResponse
    {
        $this->authorize('manage', HelpdeskTiket::class);

        HelpdeskFaq::create($request->nilai());

        return back()->with('sukses', __('helpdesk.pesan.faq_disimpan'));
    }

    public function perbaruiFaq(FaqRequest $request, HelpdeskFaq $faq): RedirectResponse
    {
        $this->authorize('manage', HelpdeskTiket::class);

        $faq->update($request->nilai());

        return back()->with('sukses', __('helpdesk.pesan.faq_disimpan'));
    }

    public function hapusFaq(HelpdeskFaq $faq): RedirectResponse
    {
        $this->authorize('manage', HelpdeskTiket::class);

        $faq->delete();

        return back()->with('sukses', __('helpdesk.pesan.faq_dihapus'));
    }
}
