<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The student's own record, and the parts of it they may correct themselves.
 *
 * The split matters. Contact details are the student's to keep current, and
 * making them ask an office to change a phone number guarantees the number
 * stays wrong. Everything the campus certifies — name, NIM, programme, intake,
 * status — is read-only here: those are claims the institution makes about a
 * person, not fields on a form.
 *
 * The screen also shows what is still missing for PDDIKTI, because the student
 * is the only one who actually has their NIK.
 */
class ProfilController extends Controller
{
    public function index(): View
    {
        $mahasiswa = Portal::user();
        $mahasiswa->load(['prodi.fakultas', 'kurikulum', 'dosenWali']);

        return view('mahasiswa.profil', [
            'judul' => 'Profil Akademik',
            'konteks' => $mahasiswa->nim,
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), 'Profil Akademik'],
            'mahasiswa' => $mahasiswa,

            // Exactly the fields Feeder refuses a biodata push without.
            'kelengkapan' => [
                'NIK' => filled($mahasiswa->nik),
                'Tempat lahir' => filled($mahasiswa->tempat_lahir),
                'Tanggal lahir' => $mahasiswa->tanggal_lahir !== null,
                'Jenis kelamin' => filled($mahasiswa->jenis_kelamin),
            ],

            'riwayatCuti' => CutiMahasiswa::query()
                ->with('tahunAkademik')
                ->where('mahasiswa_id', $mahasiswa->id)
                ->latest('diajukan_at')
                ->get(),
        ]);
    }

    public function perbarui(Request $request): RedirectResponse
    {
        $mahasiswa = Portal::user();

        // Deliberately narrow. Name, NIM, programme and status are institutional
        // claims; a student editing them would be editing their own transcript.
        $validated = $request->validate([
            'email_pribadi' => ['nullable', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:32'],
            'alamat' => ['nullable', 'string', 'max:255'],

            // Identity data the campus needs but only the student holds. Once
            // set it stays set — a NIK that changes is a data-entry error, and
            // correcting one is a registrar's job with a paper trail.
            'nik' => [
                filled($mahasiswa->nik) ? 'prohibited' : 'nullable',
                'string', 'digits:16',
            ],
            'tempat_lahir' => ['nullable', 'string', 'max:64'],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
        ], [
            'nik.prohibited' => 'NIK sudah terisi dan hanya dapat diubah oleh BAAK.',
            'nik.digits' => 'NIK terdiri dari 16 digit.',
        ]);

        $mahasiswa->update(array_filter(
            $validated,
            fn ($nilai) => $nilai !== null && $nilai !== '',
        ));

        return back()->with('sukses', 'Profil diperbarui.');
    }

    public function gantiKataSandi(Request $request): RedirectResponse
    {
        $mahasiswa = Portal::user();

        $validated = $request->validate([
            'kata_sandi_lama' => ['required', 'string'],
            'kata_sandi' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (!Hash::check($validated['kata_sandi_lama'], $mahasiswa->password)) {
            return back()->withErrors(['kata_sandi_lama' => 'Kata sandi lama tidak cocok.']);
        }

        $mahasiswa->forceFill(['password' => Hash::make($validated['kata_sandi'])])->save();

        return back()->with('sukses', 'Kata sandi diperbarui.');
    }
}
