<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\System\Pengumuman;
use App\Support\Portal;
use Illuminate\View\View;

/**
 * The announcement list and a single announcement, in whichever portal the
 * reader is in.
 *
 * One controller for all three portals, like the calendar: the selection is
 * Pengumuman::tampilUntuk(), the portal only decides which role is asked
 * for. An announcement not aimed at the reader's portal is a 404, not a 403
 * — it does not exist for them.
 */
class PengumumanPortalController extends Controller
{
    public function index(): View
    {
        $peran = Portal::role();

        $daftar = Pengumuman::query()->tampilUntuk($peran)->paginate(15);

        return view('pengumuman.index', [
            'judul' => __('pengumuman.judul'),
            'konteks' => __('pengumuman.konteks', ['jumlah' => $daftar->total()]),
            'breadcrumb' => [...$this->dasbor(), __('pengumuman.judul')],
            'daftar' => $daftar,
            'ruteTampil' => $this->rute('show'),
        ]);
    }

    public function show(Pengumuman $pengumuman): View
    {
        abort_unless(
            Pengumuman::query()->tampilUntuk(Portal::role())->whereKey($pengumuman->getKey())->exists(),
            404,
        );

        return view('pengumuman.show', [
            'judul' => $pengumuman->judul,
            'konteks' => null,
            'breadcrumb' => [...$this->dasbor(), __('pengumuman.judul') => route($this->rute('index')), $pengumuman->judul],
            'pengumuman' => $pengumuman,
            'ruteDaftar' => $this->rute('index'),
        ]);
    }

    /** @return array<string, string> */
    private function dasbor(): array
    {
        return match (Portal::role()?->value) {
            'mahasiswa' => ['Portal Mahasiswa' => route('mahasiswa.dashboard')],
            'dosen' => ['Portal Dosen' => route('dosen.dashboard')],
            default => ['Dasbor' => route('admin.dashboard')],
        };
    }

    private function rute(string $aksi): string
    {
        return match (Portal::role()?->value) {
            'mahasiswa' => 'mahasiswa.pengumuman.'.$aksi,
            'dosen' => 'dosen.pengumuman.'.$aksi,
            default => 'admin.papan-pengumuman.'.$aksi,
        };
    }
}
