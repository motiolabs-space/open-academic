<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\System\Setting;
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
    public function index(): View
    {
        $this->izin('pengaturan.view');

        return view('admin.pengaturan', [
            'judul' => 'Pengaturan',
            'konteks' => 'Identitas institusi & kebijakan',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Pengaturan'],
            'branding' => Setting::group('branding'),

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

            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'primary_color.regex' => 'Warna harus berupa kode heks enam digit, misalnya #1E2761.',
            'accent_color.regex' => 'Warna harus berupa kode heks enam digit, misalnya #C9A961.',
        ]);

        foreach ($validated as $kunci => $nilai) {
            Setting::put('branding', $kunci, $nilai);
        }

        return back()->with('sukses', 'Pengaturan disimpan. Muat ulang halaman untuk melihat perubahan warna.');
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
