<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JenisSurat;
use App\Enums\StatusSurat;
use App\Http\Controllers\Controller;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Surat\Surat;
use App\Services\Surat\SuratPdfService;
use App\Services\Surat\SuratService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The BAAK counter, as a screen.
 *
 * Requests waiting for a decision sort to the top, because the whole point of
 * this module is that somebody is standing at a window waiting for one.
 */
class SuratController extends Controller
{
    public function __construct(
        private readonly SuratService $surat,
        private readonly SuratPdfService $pdf,
    ) {}

    public function index(Request $request): View
    {
        $this->izin('surat.view');

        $daftar = Surat::query()
            ->with(['mahasiswa.prodi', 'penerbit'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->string('jenis')))
            ->cari($request->string('cari'), ['nomor', 'mahasiswa.nama', 'mahasiswa.nim'])

            // Waiting first, then newest. Portable CASE — no vendor functions.
            ->orderByRaw(sprintf(
                "CASE status WHEN '%s' THEN 0 ELSE 1 END",
                StatusSurat::Diajukan->value,
            ))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.surat', [
            'judul' => 'Surat & Dokumen',
            'konteks' => Surat::menunggu()->count().' menunggu keputusan',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Surat'],
            'daftar' => $daftar,
            'jenisPilihan' => JenisSurat::options(),
            'statusPilihan' => StatusSurat::options(),
            'filter' => $request->only(['status', 'jenis', 'cari']),
        ]);
    }

    public function terbitkan(Surat $surat): RedirectResponse
    {
        $this->izin('surat.manage');

        $this->surat->terbitkan($surat, Portal::user());

        return back()->with('sukses', 'Surat diterbitkan dengan nomor '.$surat->fresh()->nomor.'.');
    }

    public function tolak(Request $request, Surat $surat): RedirectResponse
    {
        $this->izin('surat.manage');

        $data = $request->validate(
            ['alasan' => ['required', 'string', 'max:500']],
            ['alasan.required' => 'Alasan penolakan wajib diisi — pemohon membacanya.'],
        );

        $this->surat->tolak($surat, Portal::user(), $data['alasan']);

        return back()->with('sukses', 'Permohonan ditolak dan pemohon diberi tahu.');
    }

    public function cabut(Request $request, Surat $surat): RedirectResponse
    {
        $this->izin('surat.manage');

        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        $this->surat->cabut($surat, Portal::user(), $data['alasan']);

        return back()->with('sukses',
            'Surat dicabut. Nomornya tetap dapat diverifikasi dan akan tampil sebagai dicabut.');
    }

    public function unduh(Surat $surat): Response
    {
        $this->izin('surat.view');

        return $this->pdf->pdf($surat)->download($this->pdf->namaBerkas($surat));
    }

    /** Issues a diploma supplement for a graduate who has none. */
    public function terbitkanSkpi(Request $request): RedirectResponse
    {
        $this->izin('surat.manage');

        $data = $request->validate([
            'mahasiswa_id' => ['required', 'integer', Rule::exists('mahasiswa', 'id')],
        ]);

        $surat = $this->surat->terbitkanSkpi(
            Mahasiswa::findOrFail($data['mahasiswa_id']),
            Portal::user(),
        );

        return back()->with($surat !== null ? 'sukses' : 'galat', $surat !== null
            ? 'SKPI terbit dengan nomor '.$surat->nomor.'.'
            : 'SKPI belum dapat diterbitkan — kelulusan yang bersangkutan belum ditetapkan.');
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
