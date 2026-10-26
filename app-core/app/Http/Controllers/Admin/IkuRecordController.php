<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TandaiFlagshipRequest;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Sdm\PenugasanDosen;
use App\Services\Bridge\BridgeEventPublisher;
use App\Services\Feeder\FlagshipMbkmService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verification of the records the indicators are built from.
 *
 * Open Campus reads only verified rows by default, so this screen is the point
 * where a self-reported activity becomes evidence. Verification is a deliberate
 * act by a named staff member with a timestamp, not a checkbox that quietly
 * defaults to true — an indicator resting on unchecked claims is not an
 * indicator.
 */
class IkuRecordController extends Controller
{
    public function __construct(
        private readonly BridgeEventPublisher $bridge,
        private readonly FlagshipMbkmService $flagship,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeIku('mahasiswa.view');

        $term = Portal::term();
        $belumSaja = $request->boolean('belum', true);

        $aktivitas = AktivitasMahasiswa::query()
            ->with(['mahasiswa.prodi', 'pembimbing', 'verifikator'])
            ->where('tahun_akademik_id', $term->id)
            ->when($belumSaja, fn ($q) => $q->where('is_verified', false))
            ->orderByDesc('tanggal_mulai')
            ->get();

        $penugasan = PenugasanDosen::query()
            ->with(['dosen', 'verifikator'])
            ->where('tahun_akademik_id', $term->id)
            ->when($belumSaja, fn ($q) => $q->where('is_verified', false))
            ->orderByDesc('tanggal_mulai')
            ->get();

        return view('admin.iku-records', [
            'judul' => 'Verifikasi Data IKU',
            'konteks' => $term->nama.' · '
                .AktivitasMahasiswa::where('tahun_akademik_id', $term->id)->where('is_verified', false)->count()
                .' aktivitas & '
                .PenugasanDosen::where('tahun_akademik_id', $term->id)->where('is_verified', false)->count()
                .' penugasan menunggu',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Verifikasi Data IKU'],
            'aktivitas' => $aktivitas,
            'penugasan' => $penugasan,
            'belumSaja' => $belumSaja,
            'minSksIku2' => 20,
            'programFlagship' => $this->flagship->pilihan(),
        ]);
    }

    public function verifikasiAktivitas(AktivitasMahasiswa $aktivitas): RedirectResponse
    {
        $this->authorizeIku('mahasiswa.manage');

        $aktivitas->update([
            'is_verified' => true,
            'verified_by_staff_id' => Portal::user()->id,
            'verified_at' => now(),
        ]);

        $this->bridge->publish('activity.recorded', [
            'aktivitas_uuid' => $aktivitas->uuid,
            'mahasiswa_uuid' => $aktivitas->mahasiswa->uuid,
            'nim' => $aktivitas->mahasiswa->nim,
            'semester' => $aktivitas->tahunAkademik->kode,
            'jenis' => $aktivitas->jenis->value,
            'judul' => $aktivitas->judul,
            'sks_konversi' => $aktivitas->sks_konversi,
            'mitra' => $aktivitas->mitra_nama,
        ]);

        return back()->with('sukses', 'Aktivitas '.$aktivitas->mahasiswa->nama.' diverifikasi.');
    }

    public function batalkanAktivitas(AktivitasMahasiswa $aktivitas): RedirectResponse
    {
        $this->authorizeIku('mahasiswa.manage');

        $aktivitas->update(['is_verified' => false, 'verified_by_staff_id' => null, 'verified_at' => null]);

        return back()->with('sukses', 'Verifikasi aktivitas dicabut.');
    }

    /** Marks the activity as run under a flagship programme, for the Feeder claim (#45). */
    public function tandaiFlagship(TandaiFlagshipRequest $request, AktivitasMahasiswa $aktivitas): RedirectResponse
    {
        $this->authorizeIku('mahasiswa.manage');

        $this->flagship->tandai($aktivitas, $request->validated('flagship_kode'));

        return back()->with('sukses', __('feeder.flagship.pesan.'.($aktivitas->flagship_kode === null ? 'dicabut' : 'ditandai'), [
            'nama' => $aktivitas->mahasiswa->nama,
        ]));
    }

    public function verifikasiPenugasan(PenugasanDosen $penugasan): RedirectResponse
    {
        $this->authorizeIku('dosen.manage');

        $penugasan->update([
            'is_verified' => true,
            'verified_by_staff_id' => Portal::user()->id,
            'verified_at' => now(),
        ]);

        $this->bridge->publish('lecturer.assignment_recorded', [
            'penugasan_uuid' => $penugasan->uuid,
            'dosen_uuid' => $penugasan->dosen->uuid,
            'nidn' => $penugasan->dosen->nidn,
            'semester' => $penugasan->tahunAkademik->kode,
            'jenis' => $penugasan->jenis->value,
            'judul' => $penugasan->judul,
            'mitra' => $penugasan->mitra_nama,

            // Which indicator this record feeds, decided by the enum rather
            // than left for the consumer to infer from a free-text type.
            'iku' => array_values(array_filter([
                $penugasan->jenis->countsForIku3() ? 3 : null,
                $penugasan->jenis->countsForIku4() ? 4 : null,
            ])),
        ]);

        return back()->with('sukses', 'Penugasan '.$penugasan->dosen->nama.' diverifikasi.');
    }

    public function batalkanPenugasan(PenugasanDosen $penugasan): RedirectResponse
    {
        $this->authorizeIku('dosen.manage');

        $penugasan->update(['is_verified' => false, 'verified_by_staff_id' => null, 'verified_at' => null]);

        return back()->with('sukses', 'Verifikasi penugasan dicabut.');
    }

    private function authorizeIku(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
