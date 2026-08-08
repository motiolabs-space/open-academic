<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Berkas\BerkasService;
use App\Services\Kemahasiswaan\CutiService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Academic leave, from the registrar's desk.
 *
 * Applications arrive here from the student portal; this screen is where a
 * decision is made and the student's reported status moves with it.
 */
class CutiController extends Controller
{
    public function __construct(
        private readonly CutiService $cuti,
        private readonly BerkasService $berkas,
    ) {}

    public function index(Request $request): View
    {
        $this->izin('mahasiswa.view');

        $daftar = CutiMahasiswa::query()
            ->with(['mahasiswa.prodi', 'tahunAkademik', 'pemroses'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByRaw("CASE status WHEN 'diajukan' THEN 0 ELSE 1 END")
            ->latest('diajukan_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.cuti', [
            'judul' => 'Cuti Mahasiswa',
            'konteks' => CutiMahasiswa::where('status', LeaveStatus::Diajukan->value)->count().' menunggu keputusan',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Cuti Mahasiswa'],
            'daftar' => $daftar,
            'statusPilihan' => LeaveStatus::cases(),
            'filter' => $request->only(['status']),
            'daftarTerm' => TahunAkademik::terbaru()->get(['id', 'kode', 'nama']),
            'termAktif' => Portal::term(),
        ]);
    }

    /** Staff may also file on a student's behalf — most leave starts at a counter. */
    public function ajukan(Request $request): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $validated = $request->validate([
            'mahasiswa_uuid' => ['required', 'string'],
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'jenis' => ['required', Rule::in(['akademik', 'sakit', 'lainnya'])],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],

            // Optional: a sick note usually exists, an academic leave request
            // often has nothing to attach.
            'dokumen' => $this->berkas->aturan('dokumen', wajib: false),
        ], [
            'dokumen.mimes' => 'Dokumen pendukung harus PDF atau gambar (JPG/PNG).',
        ]);

        $mahasiswa = Mahasiswa::whereUuid($validated['mahasiswa_uuid'])->firstOrFail();

        $cuti = $this->cuti->ajukan(
            $mahasiswa,
            TahunAkademik::findOrFail($validated['tahun_akademik_id']),
            $validated['jenis'],
            $validated['alasan'],
        );

        if ($request->hasFile('dokumen')) {
            $cuti->update([
                'dokumen_path' => $this->berkas->simpan(
                    $request->file('dokumen'),
                    'cuti/'.$mahasiswa->uuid,
                ),
            ]);
        }

        return back()->with('sukses', "Pengajuan cuti {$mahasiswa->nama} dicatat.");
    }

    public function setujui(Request $request, CutiMahasiswa $cuti): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $validated = $request->validate(['catatan' => ['nullable', 'string', 'max:500']]);

        $this->cuti->setujui($cuti, Portal::user(), $validated['catatan'] ?? null);

        return back()->with('sukses', "Cuti {$cuti->mahasiswa->nama} disetujui; statusnya berubah menjadi Cuti.");
    }

    public function tolak(Request $request, CutiMahasiswa $cuti): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $validated = $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'catatan.required' => 'Penolakan wajib disertai alasan yang dapat dibaca mahasiswa.',
        ]);

        $this->cuti->tolak($cuti, Portal::user(), $validated['catatan']);

        return back()->with('sukses', 'Pengajuan cuti ditolak.');
    }

    public function aktifkanKembali(CutiMahasiswa $cuti): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $this->cuti->aktifkanKembali($cuti);

        return back()->with('sukses', "{$cuti->mahasiswa->nama} aktif kembali.");
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
