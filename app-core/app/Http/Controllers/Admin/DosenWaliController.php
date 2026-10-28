<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Kemahasiswaan\HasilPenetapanWali;
use App\DTOs\Kemahasiswaan\PembagianWaliData;
use App\Enums\KrsStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\BagiDosenWaliRequest;
use App\Http\Requests\Kemahasiswaan\TetapkanDosenWaliMassalRequest;
use App\Http\Requests\Kemahasiswaan\TetapkanDosenWaliRequest;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Kemahasiswaan\DosenWaliService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Assigning academic advisors, one programme at a time.
 *
 * One screen for the three ways a registrar actually does this: the even
 * split after daftar ulang, a row-by-row pass when a lecturer leaves, and a
 * single change (also reachable from the student's own page).
 */
class DosenWaliController extends Controller
{
    public function __construct(
        private readonly DosenWaliService $wali,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('assignAdvisor', Mahasiswa::class);

        $prodi = $request->filled('prodi') ? Prodi::find($request->integer('prodi')) : null;
        $angkatan = $request->filled('angkatan') ? $request->integer('angkatan') : null;
        $tanpaWali = $request->boolean('tanpa_wali');

        $mahasiswa = $prodi === null ? null : $this->wali->kohort($prodi->id, $angkatan, $tanpaWali)
            ->with('dosenWali')
            ->withCount(['krs as krs_menunggu' => fn ($q) => $q->where('status', KrsStatus::Diajukan->value)])
            ->paginate(50)
            ->withQueryString();

        $kohort = $prodi === null ? null : $this->wali->kohort($prodi->id, $angkatan);

        return view('admin.dosen-wali', [
            'judul' => __('dosen_wali.judul'),
            'konteks' => $kohort === null ? null : __('dosen_wali.konteks', [
                'jumlah' => (clone $kohort)->count(),
                'tanpa' => (clone $kohort)->whereNull('dosen_wali_id')->count(),
            ]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('dosen_wali.judul')],
            'daftarProdi' => Prodi::orderBy('nama')->get(),
            'daftarAngkatan' => $prodi === null ? collect() : Mahasiswa::query()
                ->where('prodi_id', $prodi->id)->distinct()->orderByDesc('angkatan')->pluck('angkatan'),
            'prodi' => $prodi,
            'angkatan' => $angkatan,
            'tanpaWali' => $tanpaWali,
            'mahasiswa' => $mahasiswa,
            'calon' => $prodi === null ? collect() : $this->wali->calon($prodi->id),
        ]);
    }

    public function tetapkan(TetapkanDosenWaliRequest $request, Mahasiswa $mahasiswa): RedirectResponse
    {
        $dosen = $request->dosen();

        $riwayat = $this->wali->tetapkan($mahasiswa, $dosen, Portal::user(), $request->alasan());

        if ($riwayat === null) {
            return back()->with('sukses', __('dosen_wali.sudah_wali', ['dosen' => $dosen->namaLengkap()]));
        }

        $pesan = __('dosen_wali.hasil_satu', ['dosen' => $dosen->namaLengkap(), 'mahasiswa' => $mahasiswa->nama]);

        if ($riwayat->krs_dialihkan > 0) {
            $pesan .= ' '.__('dosen_wali.hasil_krs', ['jumlah' => $riwayat->krs_dialihkan]);
        }

        return back()->with('sukses', $pesan);
    }

    public function massal(TetapkanDosenWaliMassalRequest $request): RedirectResponse
    {
        return $this->hasil($this->wali->tetapkanMassal($request->penetapan(), Portal::user(), $request->alasan()));
    }

    public function merata(BagiDosenWaliRequest $request): RedirectResponse
    {
        return $this->hasil($this->wali->bagiMerata(PembagianWaliData::fromRequest($request), Portal::user()));
    }

    private function hasil(HasilPenetapanWali $hasil): RedirectResponse
    {
        $pesan = __('dosen_wali.hasil', ['diubah' => $hasil->diubah, 'tetap' => $hasil->tetap]);

        if ($hasil->krsDialihkan > 0) {
            $pesan .= ' '.__('dosen_wali.hasil_krs', ['jumlah' => $hasil->krsDialihkan]);
        }

        return back()->with('sukses', $pesan);
    }
}
