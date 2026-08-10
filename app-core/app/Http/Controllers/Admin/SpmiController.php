<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Models\Spmi\AuditMutu;
use App\Models\Spmi\StandarMutu;
use App\Models\Spmi\TemuanAudit;
use App\Models\Spmi\TindakLanjutTemuan;
use App\Services\Spmi\SpmiService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SPMI — standar mutu dan Audit Mutu Internal.
 *
 * Borang akreditasi tidak di sini; ia membutuhkan data penelitian, PkM dan
 * keuangan yang aplikasi ini tidak miliki. Lihat docs/SPMI.md.
 */
class SpmiController extends Controller
{
    public function __construct(private readonly SpmiService $spmi) {}

    public function index(Request $request): View
    {
        $this->izin('pengaturan.view');

        $tahun = $request->filled('tahun') ? (int) $request->integer('tahun') : null;

        // Diambil sekali, dihitung dari yang sudah di tangan. Meminta rekap
        // secara terpisah menjalankan kueri yang sama untuk kedua kalinya.
        $temuan = $this->spmi->temuanTerbuka($tahun);

        return view('admin.spmi', [
            'judul' => 'SPMI & Audit Mutu',
            'konteks' => $tahun ? 'Tahun '.$tahun : 'Semua tahun',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'SPMI'],

            'rekap' => $this->spmi->rekapDari($temuan),
            'temuan' => $temuan,

            'audit' => AuditMutu::with(['unit', 'auditorDosen', 'auditorStaff'])
                ->withCount('temuan')
                ->when($tahun, fn ($q) => $q->where('tahun', $tahun))
                ->orderByDesc('tanggal_audit')
                ->get(),

            'standar' => StandarMutu::with('unitPenanggungJawab')->orderBy('kode')->get(),

            'unitAktif' => UnitKerja::aktif()->orderBy('kode')->get(),
            'calonAuditorStaf' => Staff::where('is_active', true)->orderBy('nama')->get(),
            'calonAuditorDosen' => Dosen::orderBy('nama')->get(),
            'jenisTemuan' => (array) config('spmi.jenis_temuan'),
            'siklusPpepp' => (array) config('spmi.ppepp'),
            'tahun' => $tahun,
        ]);
    }

    public function simpanStandar(Request $request): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate([
            'kode' => ['required', 'string', 'max:24', 'unique:standar_mutu,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'pernyataan' => ['required', 'string', 'max:2000'],
            'kategori' => ['nullable', 'string', 'max:32'],
            'siklus' => ['required', 'string', 'in:'.implode(',', array_keys((array) config('spmi.ppepp')))],
            'melampaui_sndikti' => ['nullable', 'boolean'],
            'unit_penanggung_jawab_id' => ['nullable', 'integer', 'exists:unit_kerja,id'],
        ]);

        StandarMutu::create([
            ...$data,
            'melampaui_sndikti' => (bool) ($data['melampaui_sndikti'] ?? false),
        ]);

        return back()->with('sukses', 'Standar mutu ditambahkan.');
    }

    public function rencanakanAudit(Request $request): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate([
            'unit_kerja_id' => ['required', 'integer', 'exists:unit_kerja,id'],
            'nama' => ['required', 'string', 'max:255'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'auditor_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'auditor_dosen_id' => ['nullable', 'integer', 'exists:dosen,id'],
            'tanggal_audit' => ['required', 'date'],
        ]);

        $this->spmi->rencanakanAudit(UnitKerja::findOrFail($data['unit_kerja_id']), $data);

        return back()->with('sukses', 'Audit direncanakan.');
    }

    public function mulaiAudit(AuditMutu $audit): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $this->spmi->mulaiAudit($audit);

        return back()->with('sukses', 'Audit dimulai. Temuan sudah dapat dicatat.');
    }

    public function tutupAudit(Request $request, AuditMutu $audit): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate(['ringkasan' => ['nullable', 'string', 'max:2000']]);

        $this->spmi->tutupAudit($audit, $data['ringkasan'] ?? null);

        return back()->with('sukses', 'Audit ditutup. Temuannya tetap berjalan sampai selesai ditindaklanjuti.');
    }

    public function catatTemuan(Request $request, AuditMutu $audit): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate([
            'jenis' => ['required', 'string'],
            'uraian' => ['required', 'string', 'max:2000'],
            'akar_masalah' => ['nullable', 'string', 'max:2000'],
            'standar_mutu_id' => ['nullable', 'integer', 'exists:standar_mutu,id'],
        ]);

        $this->spmi->catatTemuan($audit, $data);

        return back()->with('sukses', 'Temuan dicatat beserta tenggatnya.');
    }

    public function catatTindakLanjut(Request $request, TemuanAudit $temuan): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate([
            'rencana' => ['required', 'string', 'max:2000'],
            'target_selesai' => ['nullable', 'date'],
            'realisasi' => ['nullable', 'string', 'max:2000'],
            'tanggal_realisasi' => ['nullable', 'date'],
        ]);

        $this->spmi->catatTindakLanjut($temuan, $data, $this->staf());

        return back()->with('sukses', 'Tindak lanjut dicatat.');
    }

    public function verifikasiTindakLanjut(Request $request, TindakLanjutTemuan $tindak): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate(['catatan_verifikasi' => ['nullable', 'string', 'max:500']]);

        $this->spmi->verifikasiTindakLanjut($tindak, $this->staf(), $data['catatan_verifikasi'] ?? null);

        return back()->with('sukses', 'Tindak lanjut diverifikasi.');
    }

    public function tutupTemuan(TemuanAudit $temuan): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $this->spmi->tutupTemuan($temuan, $this->staf());

        return back()->with('sukses', 'Temuan ditutup. Uraiannya tidak dapat diubah lagi.');
    }

    private function staf(): Staff
    {
        $staf = Portal::user();

        abort_unless($staf instanceof Staff, 403);

        return $staf;
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
