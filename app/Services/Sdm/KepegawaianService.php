<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creating, disabling and re-credentialing the people who run the campus.
 *
 * The interesting part is not creation — it is refusing to disable someone
 * whose absence would strand students. Deactivating a lecturer mid-semester is
 * a single click that leaves a class with nobody able to enter its grades and
 * a cohort with nobody able to approve their study plans, and nothing else in
 * the system will notice until the deadline.
 */
class KepegawaianService
{
    /**
     * Registers a lecturer and returns the one-time password to hand over.
     *
     * The password is generated rather than typed by an administrator: a
     * password an operator chooses for somebody else is a password the operator
     * still knows, and on these accounts that means access to every student
     * record the lecturer can reach.
     *
     * @param array<string, mixed> $data
     * @return array{dosen: Dosen, kata_sandi: string}
     */
    public function buatDosen(array $data, string $peran = 'dosen'): array
    {
        $kataSandi = $this->kataSandiSementara();

        $dosen = Dosen::create([
            ...$data,
            'password' => Hash::make($kataSandi),
            'is_active' => true,
        ]);

        $dosen->assignRole($peran);

        return ['dosen' => $dosen, 'kata_sandi' => $kataSandi];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{staff: Staff, kata_sandi: string}
     */
    public function buatStaff(array $data, string $peran): array
    {
        $kataSandi = $this->kataSandiSementara();

        $staff = Staff::create([
            ...$data,
            'password' => Hash::make($kataSandi),
            'is_active' => true,
        ]);

        $staff->assignRole($peran);

        return ['staff' => $staff, 'kata_sandi' => $kataSandi];
    }

    /**
     * Disables a lecturer, unless doing so would strand somebody.
     *
     * Two obligations block it, and both are things nobody discovers until the
     * deadline: a class in the running term whose grades only this lecturer can
     * enter, and active students whose study plans only this lecturer can
     * approve. Reassign first, then disable.
     */
    public function nonaktifkanDosen(Dosen $dosen): Dosen
    {
        $halangan = $this->halanganNonaktifDosen($dosen);

        if ($halangan !== []) {
            throw new AturanAkademikException(
                'Dosen ini masih memegang tanggung jawab berjalan: '.implode('; ', $halangan)
                    .'. Alihkan lebih dulu sebelum menonaktifkan.',
            );
        }

        $dosen->update(['is_active' => false]);
        $dosen->recordActivity('deactivated', 'Akun dosen dinonaktifkan.');

        return $dosen->refresh();
    }

    /**
     * The reasons this lecturer cannot be disabled right now.
     *
     * Returned as sentences rather than a boolean so the screen can show what
     * to reassign instead of just refusing.
     *
     * @return array<int, string>
     */
    public function halanganNonaktifDosen(Dosen $dosen): array
    {
        $term = TahunAkademik::aktif();
        $halangan = [];

        if ($term !== null) {
            $kelas = $dosen->kelasKuliah()
                ->where('tahun_akademik_id', $term->id)
                ->count();

            if ($kelas > 0) {
                $halangan[] = "mengampu {$kelas} kelas pada {$term->nama}";
            }
        }

        $bimbingan = Mahasiswa::query()
            ->where('dosen_wali_id', $dosen->id)
            ->where('status', 'A')
            ->count();

        if ($bimbingan > 0) {
            $halangan[] = "menjadi dosen wali bagi {$bimbingan} mahasiswa aktif";
        }

        return $halangan;
    }

    public function aktifkanDosen(Dosen $dosen): Dosen
    {
        $dosen->update(['is_active' => true]);
        $dosen->recordActivity('activated', 'Akun dosen diaktifkan kembali.');

        return $dosen->refresh();
    }

    /**
     * Disables a staff account, unless it is the last one that can administer.
     *
     * Locking every administrator out of an installation is not recoverable
     * from the interface — it needs somebody with database access. Cheap to
     * prevent, expensive to undo.
     */
    public function nonaktifkanStaff(Staff $staff): Staff
    {
        if ($this->satuSatunyaAdmin($staff)) {
            throw new AturanAkademikException(
                'Ini satu-satunya akun Super Admin yang masih aktif. Menonaktifkannya akan '
                    .'mengunci seluruh administrator dari sistem — angkat administrator lain lebih dulu.',
            );
        }

        $staff->update(['is_active' => false]);

        return $staff->refresh();
    }

    public function aktifkanStaff(Staff $staff): Staff
    {
        $staff->update(['is_active' => true]);

        return $staff->refresh();
    }

    private function satuSatunyaAdmin(Staff $staff): bool
    {
        if (!$staff->hasRole('super-admin')) {
            return false;
        }

        return Staff::query()
            ->where('is_active', true)
            ->whereKeyNot($staff->getKey())
            ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
            ->doesntExist();
    }

    /** Issues a new one-time password and returns it for hand-over. */
    public function resetKataSandi(Dosen|Staff $orang): string
    {
        $kataSandi = $this->kataSandiSementara();

        $orang->forceFill(['password' => Hash::make($kataSandi)])->save();

        // The trail records that a reset happened; it must never record what
        // the new password is.
        if (method_exists($orang, 'recordActivity')) {
            $orang->recordActivity('password_reset', 'Kata sandi diatur ulang oleh administrator.');
        }

        return $kataSandi;
    }

    /**
     * Readable but not guessable: an operator has to read this aloud or type it
     * into a message, and a password nobody can transcribe gets written on a
     * sticky note instead.
     */
    private function kataSandiSementara(): string
    {
        return Str::lower(Str::random(4)).'-'.Str::lower(Str::random(4)).'-'.random_int(100, 999);
    }
}
