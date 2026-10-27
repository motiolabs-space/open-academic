<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\AlasanAmbiguAdopsi;
use App\Enums\StatusAdopsiFeeder;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Decides which PDDIKTI record, if any, is a local student.
 *
 * The one matcher for every list read back from Feeder: the registrations
 * adoption takes over and the past students a campus claims are judged by
 * the same rules, so a student cannot be "clearly the same person" on one
 * screen and "ambiguous" on the next. The rules are ADR-020's:
 *
 *  - candidates are the Feeder rows with the same NIM plus those with the
 *    same NIK;
 *  - one candidate with the same NIM and NIK is a match, whatever the name;
 *  - with no NIK on one side the normalised name stands in, and must agree;
 *  - two candidates, a NIM whose NIK disagrees, a NIK under another NIM, or
 *    a student already linked to another record is ambiguous, and stays so
 *    until a person decides.
 *
 * Candidates are arrays keyed `id_registrasi_mahasiswa`, `id_mahasiswa`,
 * `nim`, `nik`, `nama`, `tanggal_lahir`. Nothing here stores a NIK.
 */
class PencocokMahasiswaFeeder
{
    /**
     * What each local student is, as far as the matcher can tell.
     *
     * @param Collection<int, Mahasiswa> $lokal
     * @param array<string, array<string, mixed>> $kandidat
     * @return array<int, array{mahasiswa: Mahasiswa, status: StatusAdopsiFeeder, alasan: AlasanAmbiguAdopsi|null, kandidat: array<int, array<string, mixed>>}>
     */
    public function cocokkan(Collection $lokal, array $kandidat): array
    {
        $perNim = collect($kandidat)->filter(fn (array $k): bool => filled($k['nim']))->groupBy(fn (array $k): string => $this->nim($k['nim']));
        $perNik = collect($kandidat)->filter(fn (array $k): bool => filled($k['nik']))->groupBy(fn (array $k): string => $this->nik($k['nik']));

        $usulan = [];

        foreach ($lokal as $m) {
            $calon = collect($perNim->get($this->nim($m->nim), []))
                ->merge(filled($m->nik) ? $perNik->get($this->nik($m->nik), []) : [])
                ->unique('id_registrasi_mahasiswa')
                ->values()
                ->all();

            [$status, $alasan] = $this->putuskan($m, $calon);

            $usulan[] = ['mahasiswa' => $m, 'status' => $status, 'alasan' => $alasan, 'kandidat' => $calon];
        }

        return $usulan;
    }

    /** @param array<string, mixed> $kandidat */
    public function tertautLain(Mahasiswa $m, array $kandidat): bool
    {
        return (filled($m->feeder_id) && $m->feeder_id !== $kandidat['id_mahasiswa'])
            || (filled($m->feeder_registrasi_id) && $m->feeder_registrasi_id !== $kandidat['id_registrasi_mahasiswa']);
    }

    /**
     * Whether two NIKs are the same, reduced to what a report may keep.
     *
     * Null when either side has none: "could not compare" is not "differs".
     */
    public function nikSama(mixed $lokal, mixed $feeder): ?bool
    {
        return filled($lokal) && filled($feeder) ? $this->nik($lokal) === $this->nik($feeder) : null;
    }

    /**
     * @param array<int, array<string, mixed>> $calon
     * @return array{0: StatusAdopsiFeeder, 1: AlasanAmbiguAdopsi|null}
     */
    private function putuskan(Mahasiswa $m, array $calon): array
    {
        if ($calon === []) {
            return [StatusAdopsiFeeder::HanyaLokal, null];
        }

        if (count($calon) > 1) {
            return [StatusAdopsiFeeder::Ambigu, AlasanAmbiguAdopsi::KandidatGanda];
        }

        $k = $calon[0];

        if ($this->tertautLain($m, $k)) {
            return [StatusAdopsiFeeder::Ambigu, AlasanAmbiguAdopsi::TertautLain];
        }

        // Found only through the NIK: the right person, under a NIM this
        // campus does not use for them. Adopting it would leave PDDIKTI's
        // NIM in place behind a ledger that says nothing needs sending.
        if (filled($k['nim']) && $this->nim($k['nim']) !== $this->nim($m->nim)) {
            return [StatusAdopsiFeeder::Ambigu, AlasanAmbiguAdopsi::NimBerbeda];
        }

        if (filled($m->nik) && filled($k['nik'])) {
            return $this->nik($m->nik) === $this->nik($k['nik'])
                ? [StatusAdopsiFeeder::Diadopsi, null]
                : [StatusAdopsiFeeder::Ambigu, AlasanAmbiguAdopsi::NikBerbeda];
        }

        // No NIK on one side: the name stands in for it, and has to agree.
        return $this->nama($m->nama) === $this->nama($k['nama'])
            ? [StatusAdopsiFeeder::Diadopsi, null]
            : [StatusAdopsiFeeder::Ambigu, AlasanAmbiguAdopsi::NamaBerbeda];
    }

    private function nim(mixed $nim): string
    {
        return strtoupper(trim((string) $nim));
    }

    private function nik(mixed $nik): string
    {
        return preg_replace('/\D/', '', (string) $nik) ?? '';
    }

    /** Letters only, lower case: "Ayu  Lestari, S." and "AYU LESTARI S" are one name. */
    private function nama(mixed $nama): string
    {
        return preg_replace('/[^a-z]/', '', Str::lower(Str::ascii((string) $nama))) ?? '';
    }
}
