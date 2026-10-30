<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kuesioner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kuesioner\JawabanRequest;
use App\Models\Kuesioner\Kuesioner;
use App\Services\Kuesioner\KuesionerService;
use App\Support\Portal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The respondent's side, shared by the student, lecturer and staff portals.
 *
 * The screens are the same everywhere: what am I asked to fill in, the form,
 * done. Only the URL prefix and the guard differ, as with the helpdesk.
 */
class IsiKuesionerController extends Controller
{
    public function __construct(private readonly KuesionerService $kuesioner) {}

    public function index(): View
    {
        return view('kuesioner.index', [
            'judul' => __('kuesioner.isi.judul'),
            'konteks' => __('kuesioner.isi.konteks'),
            'breadcrumb' => ['Portal' => route($this->dasbor()), __('kuesioner.isi.judul')],
            'rute' => $this->rute(),
            'daftar' => $this->kuesioner->untukResponden($this->responden()),
        ]);
    }

    public function show(Kuesioner $kuesioner): View
    {
        $this->authorize('respond', $kuesioner);

        $kuesioner->load('pertanyaan');

        return view('kuesioner.isi', [
            'judul' => $kuesioner->nama,
            'konteks' => __('kuesioner.isi.konteks_form', ['jumlah' => $kuesioner->pertanyaan->count()]),
            'breadcrumb' => [
                'Portal' => route($this->dasbor()),
                __('kuesioner.isi.judul') => route($this->rute().'.index'),
                $kuesioner->nama,
            ],
            'rute' => $this->rute(),
            'kuesioner' => $kuesioner,
            'sudah' => $this->kuesioner->sudahMengisi($kuesioner, $this->responden()),
            'terbuka' => $kuesioner->terbuka(),
        ]);
    }

    public function kirim(JawabanRequest $request, Kuesioner $kuesioner): RedirectResponse
    {
        $this->kuesioner->isi($kuesioner, $this->responden(), $request->jawaban());

        return redirect()->route($this->rute().'.index')
            ->with('sukses', $kuesioner->anonim ? __('kuesioner.isi.terkirim_anonim') : __('kuesioner.isi.terkirim'));
    }

    private function responden(): Model
    {
        $aktor = Portal::user();

        abort_unless($aktor instanceof Model, 403);

        return $aktor;
    }

    /** Route-name base of the current portal. */
    private function rute(): string
    {
        return match (Portal::role()) {
            UserRole::Dosen => 'dosen.kuesioner',
            UserRole::Staff => 'admin.kuesioner-saya',
            default => 'mahasiswa.kuesioner',
        };
    }

    private function dasbor(): string
    {
        return match (Portal::role()) {
            UserRole::Dosen => 'dosen.dashboard',
            UserRole::Staff => 'admin.dashboard',
            default => 'mahasiswa.dashboard',
        };
    }
}
