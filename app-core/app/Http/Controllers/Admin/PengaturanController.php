<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Instalasi\PersetujuanTelemetriData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Instalasi\PersetujuanTelemetriRequest;
use App\Models\System\InstallationPing;
use App\Models\System\Setting;
use App\Services\Dokumen\PengaturanDokumen;
use App\Services\Instalasi\TelemetriService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Institution identity and the academic rules a campus is allowed to change.
 *
 * Deliberately narrow. Anything that changes how a number is *computed* — the
 * credit-limit ladder, the letter-grade scale — stays in config/academic.php
 * under version control, because a mid-semester edit to a grading scale
 * silently rewrites transcripts that were already issued.
 *
 * What lives here is identity (name, colours, PDDIKTI institution code) and
 * thresholds a campus genuinely sets by policy each year.
 */
class PengaturanController extends Controller
{
    public function __construct(
        private readonly PengaturanDokumen $dokumen,
        private readonly TelemetriService $telemetri,
    ) {}

    public function index(): View
    {
        $this->izin('pengaturan.view');

        return view('admin.pengaturan', [
            'judul' => 'Pengaturan',
            'konteks' => 'Identitas institusi & kebijakan',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Pengaturan'],
            'branding' => Setting::group('branding'),
            'dokumen' => $this->dokumen->medan(),
            'telemetri' => [
                'diizinkan' => $this->telemetri->diizinkanEnv(),
                'aktif' => $this->telemetri->aktif(),
                'setuju' => $this->telemetri->disetujui(),
                'sertakanUrl' => $this->telemetri->sertakanUrl(),
                'tujuan' => (string) config('instalasi.telemetri.endpoint'),
                'urlAplikasi' => (string) config('app.url'),
                'pratinjau' => $this->telemetri->pratinjau(),
                'riwayat' => InstallationPing::query()->latest('id')->limit(10)->get(),
            ],

            // Shown read-only so an operator can see what is in force without
            // being invited to change it from a web form.
            'aturanTerkunci' => [
                'Skala nilai huruf' => collect(config('academic.grade_scale'))
                    ->map(fn ($baris) => $baris['letter'] ?? '')->filter()->implode(' · '),
                'Batas SKS per IPS' => count((array) config('academic.krs.credit_limits')).' tingkat',
                'Minimum kehadiran UAS' => config('academic.attendance.min_percent_for_final_exam').'%',
                'Syarat kelulusan' => config('academic.graduation.min_credits').' SKS · IPK '
                    .config('academic.graduation.min_gpa'),
                'Pola NIM' => config('academic.nim.pattern'),
            ],
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $validated = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'institution_short' => ['required', 'string', 'max:32'],

            // The PDDIKTI institution code. Wrong here means every Feeder
            // payload this campus sends is filed under somebody else.
            'institution_code' => ['nullable', 'string', 'max:32'],

            // Contact details from the setup wizard (ADR-043). City and
            // province are also what the installation ping reports.
            'institution_address' => ['nullable', 'string', 'max:500'],
            'institution_city' => ['nullable', 'string', 'max:100'],
            'institution_province' => ['nullable', 'string', 'max:100'],
            'institution_email' => ['nullable', 'email', 'max:255'],
            'institution_phone' => ['nullable', 'string', 'max:32'],
            'institution_website' => ['nullable', 'url:http,https', 'max:255'],

            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'primary_color.regex' => 'Warna harus berupa kode heks enam digit, misalnya #1E2761.',
            'accent_color.regex' => 'Warna harus berupa kode heks enam digit, misalnya #C9A961.',
        ]);

        foreach ($validated as $kunci => $nilai) {
            Setting::put('branding', $kunci, (string) $nilai);
        }

        return back()->with('sukses', 'Pengaturan disimpan. Muat ulang halaman untuk melihat perubahan warna.');
    }

    /**
     * Letterhead, signatory and footer per document type.
     *
     * Every field is free text, and every one of them is *content* — never a
     * template. Nothing written here is compiled or evaluated; the layout that
     * surrounds it lives in Blade files under version control. That boundary is
     * the whole reason this screen is safe to expose.
     */
    public function simpanDokumen(Request $request): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $aturan = [
            'kop_alamat' => ['nullable', 'string', 'max:255'],
            'kop_kontak' => ['nullable', 'string', 'max:255'],
        ];

        foreach ($this->dokumen->jenis() as $jenis => $definisi) {
            $aturan[$jenis.'_judul'] = ['nullable', 'string', 'max:120'];
            $aturan[$jenis.'_catatan_kaki'] = ['nullable', 'string', 'max:500'];

            if (!$definisi['penandatangan']) {
                continue;
            }

            $aturan[$jenis.'_ttd_nama'] = ['nullable', 'string', 'max:120'];
            $aturan[$jenis.'_ttd_jabatan'] = ['nullable', 'string', 'max:120'];
            $aturan[$jenis.'_ttd_nip'] = ['nullable', 'string', 'max:40'];
        }

        foreach ($request->validate($aturan) as $kunci => $nilai) {
            Setting::put(PengaturanDokumen::GRUP, $kunci, (string) $nilai);
        }

        return back()->with('sukses', 'Pengaturan dokumen disimpan.');
    }

    /**
     * Consent to the installation ping (ADR-043). The same sentence the
     * wizard asked, and the same service: turning it on here sends the ping
     * for the running version if it has not gone out yet.
     */
    public function simpanTelemetri(PersetujuanTelemetriRequest $request): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $this->telemetri->atur(PersetujuanTelemetriData::fromRequest($request));

        return back()->with('sukses', __('instalasi.telemetri.disimpan'));
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
