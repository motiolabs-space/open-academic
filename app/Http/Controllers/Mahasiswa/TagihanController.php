<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Keuangan\Tagihan;
use App\Support\Portal;
use Illuminate\View\View;

class TagihanController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', Tagihan::class);

        $mahasiswa = Portal::user();

        $tagihan = Tagihan::query()
            ->with(['tahunAkademik', 'item', 'pembayaran'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->join('tahun_akademik', 'tahun_akademik.id', '=', 'tagihan.tahun_akademik_id')
            ->orderByDesc('tahun_akademik.kode')
            ->select('tagihan.*')
            ->get();

        return view('mahasiswa.tagihan', [
            'judul' => 'Tagihan & Pembayaran',
            'konteks' => $mahasiswa->nim.' · '.$mahasiswa->prodi->namaLengkap(),
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), 'Tagihan & Pembayaran'],
            'tagihan' => $tagihan,
            'aktif' => $tagihan->firstWhere('tahun_akademik_id', Portal::term()?->id),
        ]);
    }
}
