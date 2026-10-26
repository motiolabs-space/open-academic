<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\DTOs\Kemahasiswaan\LogbookData;
use App\Enums\JenisLogbook;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\LogbookKegiatanRequest;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Models\Kemahasiswaan\KegiatanLapanganLogbook;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Kemahasiswaan\KegiatanLapanganService;
use App\Services\Kemahasiswaan\LogbookKegiatanService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The participant's side of KKN/PKL: signing up while registration is open,
 * seeing the group and its supervisors, and keeping the field log.
 *
 * Routes take the programme or the log entry, never the student: the owner is
 * always whoever is signed in, and the policy checks the entry is theirs.
 */
class KegiatanLapanganController extends Controller
{
    public function __construct(
        private readonly KegiatanLapanganService $kegiatan,
        private readonly LogbookKegiatanService $logbook,
    ) {}

    public function index(): View
    {
        $mahasiswa = $this->mahasiswa();

        $partisipasi = AktivitasMahasiswa::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereNotNull('kegiatan_lapangan_id')
            ->with([
                'kegiatanLapangan.tahunAkademik',
                'kelompok.lokasi',
                'kelompok.pembimbing',
                'kelompok.anggota.mahasiswa.prodi',
                'logbook.pemeriksa',
                'krsKonversi.nilai',
            ])
            ->orderByDesc('tanggal_mulai')
            ->get();

        $terbuka = KegiatanLapangan::query()
            ->where('pendaftaran_dibuka', true)
            ->whereNotIn('id', $partisipasi->pluck('kegiatan_lapangan_id'))
            ->with('tahunAkademik')
            ->orderBy('tanggal_mulai')
            ->get();

        return view('mahasiswa.kegiatan-lapangan', [
            'judul' => __('kegiatan_lapangan.judul_mahasiswa'),
            'konteks' => $partisipasi->first()?->kegiatanLapangan->nama ?? __('kegiatan_lapangan.keterangan.belum_ikut'),
            'breadcrumb' => ['Portal' => route('mahasiswa.dashboard'), __('kegiatan_lapangan.judul_mahasiswa')],
            'partisipasi' => $partisipasi,
            'terbuka' => $terbuka,
            'sksSaatIni' => $mahasiswa->sksKumulatif(),
            'jenisLogbook' => JenisLogbook::options(),
        ]);
    }

    public function daftar(KegiatanLapangan $kegiatan): RedirectResponse
    {
        $this->authorize('daftar', $kegiatan);

        $this->kegiatan->daftarkan($kegiatan, $this->mahasiswa(), mandiri: true);

        return back()->with('sukses', __('kegiatan_lapangan.sukses.daftar', ['nama' => $kegiatan->nama]));
    }

    public function catatLogbook(LogbookKegiatanRequest $request, AktivitasMahasiswa $aktivitas): RedirectResponse
    {
        $this->authorize('create', [KegiatanLapanganLogbook::class, $aktivitas]);

        $this->logbook->catat($aktivitas, LogbookData::fromRequest($request));

        return back()->with('sukses', __('kegiatan_lapangan.sukses.logbook'));
    }

    public function hapusLogbook(KegiatanLapanganLogbook $logbook): RedirectResponse
    {
        $this->authorize('delete', $logbook);

        $this->logbook->hapus($logbook);

        return back()->with('sukses', __('kegiatan_lapangan.sukses.logbook_hapus'));
    }

    private function mahasiswa(): Mahasiswa
    {
        $mahasiswa = Portal::user();

        abort_unless($mahasiswa instanceof Mahasiswa, 403);

        return $mahasiswa;
    }
}
