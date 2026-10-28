<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\HasilPenetapanWali;
use App\DTOs\Kemahasiswaan\PembagianWaliData;
use App\Enums\CaraPenetapanWali;
use App\Enums\KrsStatus;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Krs;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\RiwayatDosenWali;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Notifications\Kemahasiswaan\DosenWaliDitetapkan;
use App\Services\Notifikasi\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assigning and changing academic advisors (dosen wali).
 *
 * `mahasiswa.dosen_wali_id` decides who may approve a student's KRS — the
 * check lives in KrsService::putuskan() and KrsPolicy — so this is the only
 * code that writes it outside the demo seeder. Every change leaves a
 * RiwayatDosenWali row and tells the student.
 *
 * The rule (ADR-035): an advisor is an active lecturer whose homebase is the
 * student's own programme. A study plan already submitted to the previous
 * advisor needs no rewrite to move: it carries no advisor of its own and is
 * decided by whoever is the advisor when the decision is made, so it appears
 * in the new advisor's queue the moment this service commits.
 */
class DosenWaliService
{
    /**
     * Statuses for which an advisor still means something. A graduate or a
     * student who left keeps whoever advised them last, as history.
     *
     * @var array<int, StudentStatus>
     */
    public const STATUS_BERWALI = [
        StudentStatus::Aktif,
        StudentStatus::Cuti,
        StudentStatus::NonAktif,
        StudentStatus::MenungguUkom,
    ];

    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    /**
     * Lecturers who may advise students of a programme, with their current
     * advisee counts.
     *
     * @return Collection<int, Dosen>
     */
    public function calon(int $prodiId): Collection
    {
        return Dosen::query()
            ->aktif()
            ->where('prodi_id', $prodiId)
            ->withCount(['mahasiswaBimbingan as jumlah_bimbingan' => fn (Builder $q) => $q
                ->whereIn('status', array_map(fn (StudentStatus $s): string => $s->value, self::STATUS_BERWALI))])
            ->orderBy('nama')
            ->get();
    }

    /**
     * Assigns one student's advisor. Null when they already have this one.
     */
    public function tetapkan(
        Mahasiswa $mahasiswa,
        Dosen $dosen,
        Staff $staff,
        ?string $alasan = null,
        CaraPenetapanWali $cara = CaraPenetapanWali::Perorangan,
    ): ?RiwayatDosenWali {
        $this->pastikanLayak($mahasiswa, $dosen);

        if ($mahasiswa->dosen_wali_id === $dosen->id) {
            return null;
        }

        $riwayat = DB::transaction(fn (): RiwayatDosenWali => $this->ganti($mahasiswa, $dosen, $staff, $alasan, $cara));

        $this->beritahu($riwayat);

        return $riwayat;
    }

    /**
     * Assigns many students at once, all or nothing.
     *
     * Every pair is checked before anything is written, and every refusal is
     * reported together — a registrar fixing forty rows should not learn about
     * them one resubmission at a time.
     *
     * @param array<int, int> $penetapan mahasiswa id => dosen id
     */
    public function tetapkanMassal(
        array $penetapan,
        Staff $staff,
        ?string $alasan = null,
        CaraPenetapanWali $cara = CaraPenetapanWali::Manual,
    ): HasilPenetapanWali {
        $mahasiswa = Mahasiswa::query()->with('prodi')->whereIn('id', array_keys($penetapan))->get()->keyBy('id');
        $dosen = Dosen::query()->whereIn('id', array_unique(array_values($penetapan)))->get()->keyBy('id');

        $galat = [];

        foreach ($penetapan as $mahasiswaId => $dosenId) {
            $m = $mahasiswa->get($mahasiswaId);
            $d = $dosen->get($dosenId);

            if ($m === null || $d === null) {
                continue;
            }

            if (($pesan = $this->penghalang($m, $d)) !== null) {
                $galat[$pesan] = true;
            }
        }

        if ($galat !== []) {
            throw new AturanAkademikException(implode(' ', array_keys($galat)));
        }

        $riwayat = DB::transaction(function () use ($penetapan, $mahasiswa, $dosen, $staff, $alasan, $cara): array {
            $ditulis = [];

            foreach ($penetapan as $mahasiswaId => $dosenId) {
                $m = $mahasiswa->get($mahasiswaId);
                $d = $dosen->get($dosenId);

                if ($m !== null && $d !== null && $m->dosen_wali_id !== $d->id) {
                    $ditulis[] = $this->ganti($m, $d, $staff, $alasan, $cara);
                }
            }

            return $ditulis;
        });

        foreach ($riwayat as $baris) {
            $this->beritahu($baris);
        }

        return new HasilPenetapanWali(
            diubah: count($riwayat),
            tetap: count($penetapan) - count($riwayat),
            krsDialihkan: array_sum(array_map(fn (RiwayatDosenWali $r): int => $r->krs_dialihkan, $riwayat)),
        );
    }

    /**
     * Splits a cohort evenly across the chosen advisors.
     *
     * Each advisor's share differs from the others' by at most one. Students
     * already advised by one of the chosen lecturers stay with them while that
     * lecturer's share has room, so running the same split twice changes
     * nothing and adding one lecturer moves only as many students as needed.
     */
    public function bagiMerata(PembagianWaliData $data, Staff $staff): HasilPenetapanWali
    {
        if ($data->dosenIds === []) {
            throw new AturanAkademikException(__('dosen_wali.galat.tanpa_dosen'));
        }

        $dosen = Dosen::query()->whereIn('id', $data->dosenIds)->orderBy('nama')->orderBy('id')->get();

        foreach ($dosen as $d) {
            if (!$d->is_active) {
                throw new AturanAkademikException(__('dosen_wali.galat.dosen_tidak_aktif', ['dosen' => $d->namaLengkap()]));
            }
        }

        $mahasiswa = $this->kohort($data->prodiId, $data->angkatan, $data->hanyaTanpaWali)->get();

        if ($mahasiswa->isEmpty()) {
            throw new AturanAkademikException(__('dosen_wali.galat.tanpa_mahasiswa'));
        }

        $jumlahDosen = $dosen->count();
        $dasar = intdiv($mahasiswa->count(), $jumlahDosen);
        $lebih = $mahasiswa->count() % $jumlahDosen;

        // The first `$lebih` advisors in name order take one extra student.
        $kuota = [];
        foreach ($dosen->values() as $i => $d) {
            $kuota[$d->id] = $dasar + ($i < $lebih ? 1 : 0);
        }

        $penetapan = [];

        foreach ($mahasiswa as $m) {
            if ($m->dosen_wali_id !== null && ($kuota[$m->dosen_wali_id] ?? 0) > 0) {
                $penetapan[$m->id] = $m->dosen_wali_id;
                $kuota[$m->dosen_wali_id]--;
            }
        }

        foreach ($mahasiswa as $m) {
            if (isset($penetapan[$m->id])) {
                continue;
            }

            $dosenId = (int) array_key_first(array_filter($kuota, fn (int $sisa): bool => $sisa > 0));
            $penetapan[$m->id] = $dosenId;
            $kuota[$dosenId]--;
        }

        return $this->tetapkanMassal($penetapan, $staff, $data->alasan, CaraPenetapanWali::Merata);
    }

    /**
     * The students of one programme an advisor screen works on.
     *
     * @return Builder<Mahasiswa>
     */
    public function kohort(int $prodiId, ?int $angkatan = null, bool $hanyaTanpaWali = false): Builder
    {
        return Mahasiswa::query()
            ->where('prodi_id', $prodiId)
            ->whereIn('status', array_map(fn (StudentStatus $s): string => $s->value, self::STATUS_BERWALI))
            ->when($angkatan !== null, fn (Builder $q) => $q->where('angkatan', $angkatan))
            ->when($hanyaTanpaWali, fn (Builder $q) => $q->whereNull('dosen_wali_id'))
            ->orderBy('nim');
    }

    /** Throws the reason this lecturer may not advise this student, if any. */
    public function pastikanLayak(Mahasiswa $mahasiswa, Dosen $dosen): void
    {
        if (($pesan = $this->penghalang($mahasiswa, $dosen)) !== null) {
            throw new AturanAkademikException($pesan);
        }
    }

    private function penghalang(Mahasiswa $mahasiswa, Dosen $dosen): ?string
    {
        if (!in_array($mahasiswa->status, self::STATUS_BERWALI, true)) {
            return __('dosen_wali.galat.mahasiswa_tidak_berwali', [
                'mahasiswa' => $mahasiswa->nama,
                'status' => $mahasiswa->status->label(),
            ]);
        }

        if (!$dosen->is_active) {
            return __('dosen_wali.galat.dosen_tidak_aktif', ['dosen' => $dosen->namaLengkap()]);
        }

        if ($dosen->prodi_id !== $mahasiswa->prodi_id) {
            return __('dosen_wali.galat.beda_prodi', [
                'dosen' => $dosen->namaLengkap(),
                'prodi' => $mahasiswa->loadMissing('prodi')->prodi?->nama ?? '—',
            ]);
        }

        return null;
    }

    private function ganti(Mahasiswa $mahasiswa, Dosen $dosen, Staff $staff, ?string $alasan, CaraPenetapanWali $cara): RiwayatDosenWali
    {
        // Counted, not rewritten: a submitted plan follows the advisor column.
        $krsMenunggu = Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('status', KrsStatus::Diajukan->value)
            ->count();

        $lama = $mahasiswa->dosen_wali_id;

        $mahasiswa->update(['dosen_wali_id' => $dosen->id]);

        // krs.approve lives on the dosen-wali role. A lecturer assigned
        // advisees without it would see them and be refused every decision.
        if (!$dosen->hasRole('dosen-wali', 'dosen')) {
            $dosen->assignRole('dosen-wali');
        }

        return RiwayatDosenWali::create([
            'mahasiswa_id' => $mahasiswa->id,
            'dosen_lama_id' => $lama,
            'dosen_baru_id' => $dosen->id,
            'cara' => $cara,
            'alasan' => $alasan,
            'krs_dialihkan' => $krsMenunggu,
            'ditetapkan_by_staff_id' => $staff->id,
        ]);
    }

    private function beritahu(RiwayatDosenWali $riwayat): void
    {
        $riwayat->loadMissing(['mahasiswa', 'dosenBaru']);

        $this->notifier->kirim($riwayat->mahasiswa, new DosenWaliDitetapkan($riwayat));

        if ($riwayat->krs_dialihkan > 0) {
            $this->notifier->kirim($riwayat->dosenBaru, new DosenWaliDitetapkan($riwayat));
        }
    }
}
