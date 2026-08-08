<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\JenisSurat;
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
 * The student's side: asking for a letter and downloading it.
 *
 * The screen leads with what they can have right now. A certificate of
 * enrolment is issued the moment it is asked for, because the campus is not
 * deciding anything — it is reading a status column out loud, and the counter
 * queue for that was never doing any work.
 *
 * Every route resolves the letter through the student's own relation, so an
 * identifier belonging to somebody else does not resolve at all.
 */
class SuratController extends Controller
{
    public function __construct(
        private readonly SuratService $surat,
        private readonly SuratPdfService $pdf,
    ) {}

    public function index(): View
    {
        $mahasiswa = $this->mahasiswa();

        // Why each type is or is not available, computed once for the screen.
        // Showing a disabled button with the reason beside it saves the trip to
        // the counter that this module exists to remove.
        $tersedia = collect(JenisSurat::dapatDiajukan())
            ->map(fn (JenisSurat $j): array => [
                'jenis' => $j,
                'halangan' => $this->surat->halangan($mahasiswa, $j),
            ])
            ->values();

        return view('mahasiswa.surat', [
            'judul' => 'Surat & Dokumen',
            'konteks' => 'Layanan administrasi',
            'breadcrumb' => ['Portal' => route('mahasiswa.dashboard'), 'Surat'],
            'tersedia' => $tersedia,
            'daftar' => $mahasiswa->surat()->with('penerbit')->paginate(15),
        ]);
    }

    public function ajukan(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa();

        $data = $request->validate([
            'jenis' => [
                'required',
                Rule::enum(JenisSurat::class)->except([JenisSurat::Skpi]),
            ],
            'keperluan' => ['nullable', 'string', 'max:255'],
        ]);

        $surat = $this->surat->ajukan(
            $mahasiswa,
            JenisSurat::from($data['jenis']),
            $data['keperluan'] ?? null,
        );

        return back()->with('sukses', $surat->jenis->swalayan()
            ? 'Surat terbit dengan nomor '.$surat->nomor.' dan siap diunduh.'
            : 'Permohonan diajukan dan menunggu keputusan bagian akademik.');
    }

    public function unduh(Surat $surat): Response
    {
        abort_unless($surat->mahasiswa_id === $this->mahasiswa()->id, 403);

        return $this->pdf->pdf($surat)->download($this->pdf->namaBerkas($surat));
    }

    private function mahasiswa(): Mahasiswa
    {
        $mahasiswa = Portal::user();

        abort_unless($mahasiswa instanceof Mahasiswa, 403);

        return $mahasiswa;
    }
}
