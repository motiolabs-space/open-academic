<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Exceptions\AturanAkademikException;
use App\Http\Controllers\Controller;
use App\Services\Akademik\PresensiService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student side of QR attendance.
 *
 * `PresensiService::absenMandiri()` has existed since Phase 1 and nothing could
 * reach it — the lecturer could open a session, and no student could answer it.
 *
 * The token arrives in the URL when a phone camera opens the code, so the
 * common path is a GET that lands here already carrying it. Marking attendance
 * is still a POST: a GET that changes a record can be triggered by a link
 * preview or a prefetch, and here that means a student marked present from a
 * chat app without ever entering the room.
 */
class PresensiMandiriController extends Controller
{
    public function __construct(private readonly PresensiService $presensi) {}

    public function form(Request $request): View
    {
        return view('mahasiswa.presensi-mandiri', [
            'judul' => 'Presensi Mandiri',
            'konteks' => 'Pindai kode dari layar dosen',
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), 'Presensi Mandiri'],
            'token' => (string) $request->string('token'),
        ]);
    }

    public function catat(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ], [
            'token.required' => 'Kode presensi tidak terbaca. Pindai ulang kode di layar dosen.',
        ]);

        try {
            $presensi = $this->presensi->absenMandiri($validated['token'], Portal::user());
        } catch (AturanAkademikException $e) {
            // Rendered on this screen rather than bounced to a generic error:
            // a student standing in a lecture hall needs to know whether to
            // scan again or speak to the lecturer.
            return back()->with('galat', $e->getMessage());
        }

        $kelas = $presensi->pertemuan->kelasKuliah;

        return back()->with('sukses', sprintf(
            'Kehadiran tercatat untuk %s, pertemuan ke-%d.',
            $kelas->mataKuliah->nama,
            $presensi->pertemuan->pertemuan_ke,
        ));
    }
}
