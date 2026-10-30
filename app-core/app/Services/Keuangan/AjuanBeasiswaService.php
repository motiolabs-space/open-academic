<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\DTOs\Keuangan\AjuanBeasiswaData;
use App\DTOs\Keuangan\PeriodeBeasiswaData;
use App\Enums\StatusAjuanBeasiswa;
use App\Enums\StatusPenerima;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\BeasiswaAjuan;
use App\Models\Keuangan\BeasiswaPenerima;
use App\Models\Keuangan\BeasiswaPeriode;
use App\Models\Sdm\Staff;
use App\Notifications\Keuangan\AjuanBeasiswaDiputus;
use App\Services\Berkas\BerkasService;
use App\Services\Notifikasi\Notifier;
use App\Support\Format;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Scholarship calls and the applications made to them (ADR-040 §2).
 *
 * This is an inbox in front of BeasiswaService, nothing more. Granting an
 * application calls BeasiswaService::tetapkan() — the same path a direct award
 * takes, with its quota, its uniqueness and its invoice adjustment — so no
 * discount exists that the finance office did not create, and none is created
 * any other way.
 */
class AjuanBeasiswaService
{
    public function __construct(
        private readonly BeasiswaService $beasiswa,
        private readonly BerkasService $berkas,
        private readonly Notifier $notifier,
    ) {}

    public function bukaPeriode(Beasiswa $beasiswa, PeriodeBeasiswaData $data, Staff $staff): BeasiswaPeriode
    {
        if (!$beasiswa->is_active) {
            throw new AturanAkademikException(__('swalayan.beasiswa.galat.skema_nonaktif', ['skema' => $beasiswa->nama]));
        }

        if ($data->dibukaSampai->lt($data->dibukaMulai)) {
            throw new AturanAkademikException(__('swalayan.beasiswa.galat.tanggal_periode'));
        }

        return BeasiswaPeriode::create([
            'beasiswa_id' => $beasiswa->id,
            'tahun_akademik_id' => $data->tahunAkademikId,
            'nama' => $data->nama,
            'dibuka_mulai' => $data->dibukaMulai->toDateString(),
            'dibuka_sampai' => $data->dibukaSampai->toDateString(),
            'syarat' => $data->syarat,
            'ipk_minimum' => $data->ipkMinimum,
            'berkas_diminta' => $data->berkasDiminta === [] ? null : $data->berkasDiminta,
            'dibuat_by_staff_id' => $staff->id,
        ]);
    }

    /** Ends a call early. Applications already in stay and are still decided. */
    public function tutupPeriode(BeasiswaPeriode $periode, Staff $staff): BeasiswaPeriode
    {
        if ($periode->ditutup_at !== null) {
            throw new AturanAkademikException(__('swalayan.beasiswa.galat.sudah_ditutup'));
        }

        $periode->update(['ditutup_at' => now(), 'ditutup_by_staff_id' => $staff->id]);

        return $periode->refresh();
    }

    /**
     * Calls a student can see today, with the scheme loaded.
     *
     * @return Collection<int, BeasiswaPeriode>
     */
    public function periodeTerbuka(): Collection
    {
        return BeasiswaPeriode::query()
            ->terbuka()
            ->whereHas('beasiswa', fn ($q) => $q->where('is_active', true))
            ->with(['beasiswa', 'tahunAkademik'])
            ->orderBy('dibuka_sampai')
            ->get();
    }

    /**
     * Why this student cannot apply to this call, or null when they can.
     *
     * Only what the application can know for certain is checked here. The
     * written requirements are for the person who reviews the application,
     * and refusing on a reading of them would be the system deciding.
     */
    public function halangan(BeasiswaPeriode $periode, Mahasiswa $mahasiswa, ?float $ipk = null): ?string
    {
        if (!$periode->dibuka() || !$periode->beasiswa->is_active) {
            return __('swalayan.beasiswa.halangan.ditutup');
        }

        if ($mahasiswa->status !== StudentStatus::Aktif) {
            return __('swalayan.beasiswa.halangan.bukan_aktif', ['status' => $mahasiswa->status->label()]);
        }

        if (BeasiswaAjuan::where('beasiswa_periode_id', $periode->id)->where('mahasiswa_id', $mahasiswa->id)->exists()) {
            return __('swalayan.beasiswa.halangan.sudah_mengajukan');
        }

        $penerimaAktif = BeasiswaPenerima::query()
            ->where('beasiswa_id', $periode->beasiswa_id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('status', StatusPenerima::Aktif->value)
            ->exists();

        if ($penerimaAktif) {
            return __('swalayan.beasiswa.halangan.sudah_menerima', ['skema' => $periode->beasiswa->nama]);
        }

        if ($periode->ipk_minimum !== null) {
            $ipk ??= $mahasiswa->ipk();

            if ($ipk < (float) $periode->ipk_minimum) {
                return __('swalayan.beasiswa.halangan.ipk', [
                    'minimum' => Format::angka((float) $periode->ipk_minimum, 2),
                    'ipk' => Format::angka($ipk, 2),
                ]);
            }
        }

        return null;
    }

    public function ajukan(BeasiswaPeriode $periode, Mahasiswa $mahasiswa, AjuanBeasiswaData $data): BeasiswaAjuan
    {
        $periode->loadMissing('beasiswa');
        $ipk = $mahasiswa->ipk();

        $halangan = $this->halangan($periode, $mahasiswa, $ipk);

        if ($halangan !== null) {
            throw new AturanAkademikException($halangan);
        }

        $diminta = $periode->daftarBerkas();

        if (count($data->berkas) !== count($diminta)) {
            throw new AturanAkademikException(__('swalayan.beasiswa.galat.berkas_wajib'));
        }

        if (blank($data->motivasi)) {
            throw new AturanAkademikException(__('swalayan.beasiswa.galat.motivasi_wajib'));
        }

        $disimpan = [];

        try {
            return DB::transaction(function () use ($periode, $mahasiswa, $data, $diminta, $ipk, &$disimpan): BeasiswaAjuan {
                foreach (array_values($data->berkas) as $i => $berkas) {
                    $disimpan[] = [
                        'label' => $diminta[$i],
                        'path' => $this->berkas->simpan($berkas, 'beasiswa/'.$mahasiswa->uuid),
                    ];
                }

                return BeasiswaAjuan::create([
                    'beasiswa_periode_id' => $periode->id,
                    'mahasiswa_id' => $mahasiswa->id,
                    'status' => StatusAjuanBeasiswa::Diajukan,
                    'motivasi' => $data->motivasi,
                    'ipk_saat_ajuan' => $ipk,
                    'berkas' => $disimpan === [] ? null : $disimpan,
                    'diajukan_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            // The row was not written, so neither should its files be: a
            // household document with no application pointing at it is one
            // nobody will ever know to delete.
            foreach ($disimpan as $berkas) {
                $this->berkas->hapus($berkas['path']);
            }

            // Two submits racing past halangan(): the index keeps one.
            if ($e instanceof UniqueConstraintViolationException) {
                throw new AturanAkademikException(__('swalayan.beasiswa.halangan.sudah_mengajukan'));
            }

            throw $e;
        }
    }

    /**
     * Grants an application through the ordinary award path.
     *
     * In one transaction with the award itself: a granted application without
     * an award, or an award whose application still reads "waiting", is the
     * kind of record nobody can explain a semester later. A full quota or an
     * existing award refuses here exactly as it does on a direct grant.
     */
    public function tetapkan(
        BeasiswaAjuan $ajuan,
        Staff $staff,
        ?TahunAkademik $selesai = null,
        ?string $nomorSk = null,
        ?string $catatan = null,
    ): BeasiswaAjuan {
        $this->pastikanMenunggu($ajuan);

        $ajuan->loadMissing(['periode.beasiswa', 'periode.tahunAkademik', 'mahasiswa']);

        DB::transaction(function () use ($ajuan, $staff, $selesai, $nomorSk, $catatan): void {
            $penerima = $this->beasiswa->tetapkan(
                $ajuan->periode->beasiswa,
                $ajuan->mahasiswa,
                $ajuan->periode->tahunAkademik,
                $selesai,
                $nomorSk,
                $staff,
            );

            $ajuan->update([
                'status' => StatusAjuanBeasiswa::Ditetapkan,
                'beasiswa_penerima_id' => $penerima->id,
                'diputus_by_staff_id' => $staff->id,
                'diputus_at' => now(),
                'catatan_keputusan' => $catatan,
            ]);
        });

        $this->notifier->kirim($ajuan->mahasiswa, new AjuanBeasiswaDiputus($ajuan->refresh()));

        return $ajuan;
    }

    public function tolak(BeasiswaAjuan $ajuan, Staff $staff, string $catatan): BeasiswaAjuan
    {
        $this->pastikanMenunggu($ajuan);

        if (blank($catatan)) {
            throw new AturanAkademikException(__('swalayan.beasiswa.galat.catatan_tolak_wajib'));
        }

        $ajuan->update([
            'status' => StatusAjuanBeasiswa::Ditolak,
            'diputus_by_staff_id' => $staff->id,
            'diputus_at' => now(),
            'catatan_keputusan' => $catatan,
        ]);

        $ajuan->loadMissing(['periode.beasiswa', 'mahasiswa']);
        $this->notifier->kirim($ajuan->mahasiswa, new AjuanBeasiswaDiputus($ajuan->refresh()));

        return $ajuan;
    }

    private function pastikanMenunggu(BeasiswaAjuan $ajuan): void
    {
        if ($ajuan->status !== StatusAjuanBeasiswa::Diajukan) {
            throw new AturanAkademikException(__('swalayan.beasiswa.galat.sudah_diputus', ['status' => $ajuan->status->label()]));
        }
    }
}
