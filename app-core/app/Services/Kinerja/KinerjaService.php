<?php

declare(strict_types=1);

namespace App\Services\Kinerja;

use App\Enums\StatusPeriodeKinerja;
use App\Enums\SumberRealisasi;
use App\Exceptions\AturanAkademikException;
use App\Models\Kinerja\CapaianKinerja;
use App\Models\Kinerja\PeriodeKinerja;
use App\Models\Kinerja\SasaranKinerja;
use App\Models\Kinerja\UkuranKinerja;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rencana kinerja: objectives per unit, cascaded down the org chart.
 *
 * Four properties are defended here, and each is a lesson already paid for
 * elsewhere in this codebase:
 *
 *   1. a computed measure never takes a typed number;
 *   2. the cascade refuses cycles at write time;
 *   3. locking freezes both target and realisation, permanently;
 *   4. nothing here judges a person — it reports figures against targets.
 */
class KinerjaService
{
    public function __construct(private readonly PengukurKinerja $pengukur) {}

    /* ------------------------------------------------------------------
     | Sasaran
     |----------------------------------------------------------------- */

    /** @param array<string, mixed> $data */
    public function buatSasaran(PeriodeKinerja $periode, UnitKerja $unit, array $data): SasaranKinerja
    {
        $this->pastikanDapatDiubah($periode);

        if (!$unit->is_active) {
            throw new AturanAkademikException(sprintf(
                'Unit "%s" sudah tidak aktif dan tidak dapat menerima sasaran baru.',
                $unit->nama,
            ));
        }

        if (($data['parent_id'] ?? null) !== null) {
            $this->pastikanIndukSah($periode, null, (int) $data['parent_id']);
        }

        return SasaranKinerja::create([
            ...$data,
            'periode_kinerja_id' => $periode->id,
            'unit_kerja_id' => $unit->id,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function perbaruiSasaran(SasaranKinerja $sasaran, array $data): SasaranKinerja
    {
        $this->pastikanDapatDiubah($sasaran->periode);

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->pastikanIndukSah($sasaran->periode, $sasaran, (int) $data['parent_id']);
        }

        $sasaran->fill($data)->save();

        return $sasaran->refresh();
    }

    /**
     * Refuses a parent that is the objective itself, one of its descendants, or
     * one from another period.
     *
     * A cycle is the only structural failure a parent-pointer tree has, and it
     * is silent: nothing breaks on write, and every traversal afterwards runs
     * forever. Walked upwards from the proposed parent — the ancestor chain is
     * at most as deep as the tree, while the descendant set can be everything.
     */
    private function pastikanIndukSah(PeriodeKinerja $periode, ?SasaranKinerja $sasaran, int $indukId): void
    {
        $semua = SasaranKinerja::query()
            ->where('periode_kinerja_id', $periode->id)
            ->get(['id', 'parent_id', 'judul']);

        $induk = $semua->firstWhere('id', $indukId);

        if ($induk === null) {
            throw new AturanAkademikException(
                'Sasaran induk harus berada pada periode yang sama.',
            );
        }

        if ($sasaran === null) {
            return;
        }

        if ($indukId === $sasaran->id) {
            throw new AturanAkademikException('Sebuah sasaran tidak dapat menjadi induk dirinya sendiri.');
        }

        $kini = $induk;

        for ($i = 0; $i < $semua->count() && $kini !== null; $i++) {
            if ((int) $kini->id === (int) $sasaran->id) {
                throw new AturanAkademikException(sprintf(
                    'Sasaran "%s" berada di bawah "%s", jadi menjadikannya induk akan membentuk lingkaran.',
                    $induk->judul,
                    $sasaran->judul,
                ));
            }

            $kini = $kini->parent_id === null ? null : $semua->firstWhere('id', $kini->parent_id);
        }
    }

    /* ------------------------------------------------------------------
     | Ukuran
     |----------------------------------------------------------------- */

    /** @param array<string, mixed> $data */
    public function tambahUkuran(SasaranKinerja $sasaran, array $data): UkuranKinerja
    {
        $this->pastikanDapatDiubah($sasaran->periode);

        $sumber = $data['sumber_realisasi'] instanceof SumberRealisasi
            ? $data['sumber_realisasi']
            : SumberRealisasi::from((string) $data['sumber_realisasi']);

        /*
         * A computed measure must name an indicator that actually has a
         * counter.
         *
         * Without this check the row is a target that can never be realised,
         * and nobody finds out until the review it was created for.
         */
        if ($sumber === SumberRealisasi::Dihitung) {
            $kunci = (string) ($data['indikator_kunci'] ?? '');

            if (!$this->pengukur->dikenal($kunci)) {
                throw new AturanAkademikException(sprintf(
                    'Ukuran yang dihitung harus menyebut indikator terdaftar. Yang tersedia: %s.',
                    implode(', ', array_keys($this->pengukur->katalog())),
                ));
            }

            $data['satuan'] ??= $this->pengukur->definisi($kunci)['satuan'] ?? null;
        } else {
            // A key on a non-computed measure would imply a link that is never
            // followed, and somebody would eventually trust it.
            $data['indikator_kunci'] = null;
        }

        return UkuranKinerja::create([
            ...$data,
            'sumber_realisasi' => $sumber,
            'sasaran_kinerja_id' => $sasaran->id,
        ]);
    }

    /* ------------------------------------------------------------------
     | Capaian
     |----------------------------------------------------------------- */

    /**
     * Records a typed check-in.
     *
     * Refused outright for computed measures. Not by permission — by there
     * being no legitimate path: the number for those comes from the data, and
     * an override would be indistinguishable from the real thing afterwards.
     */
    public function catatCapaian(
        UkuranKinerja $ukuran,
        float $nilai,
        string $tanggal,
        ?Staff $staff = null,
        ?string $catatan = null,
    ): CapaianKinerja {
        $periode = $ukuran->sasaran->periode;

        if (!$periode->status->menerimaCapaian()) {
            throw new AturanAkademikException(sprintf(
                'Periode "%s" berstatus %s dan tidak menerima capaian baru.',
                $periode->nama,
                $periode->status->label(),
            ));
        }

        if (!$ukuran->sumber_realisasi->bolehDiketik()) {
            throw new AturanAkademikException(sprintf(
                'Ukuran "%s" bersumber %s, jadi realisasinya tidak dapat diketik.',
                $ukuran->nama,
                $ukuran->sumber_realisasi->label(),
            ));
        }

        return CapaianKinerja::create([
            'ukuran_kinerja_id' => $ukuran->id,
            'tanggal' => $tanggal,
            'nilai' => $nilai,
            'sumber' => $ukuran->sumber_realisasi,
            'catatan' => $catatan,
            'dicatat_by_staff_id' => $staff?->id,
        ]);
    }

    /**
     * Measures every computed measure in a period and records the results.
     *
     * @return int how many were measured
     */
    public function ukurOtomatis(PeriodeKinerja $periode): int
    {
        if (!$periode->status->menerimaCapaian()) {
            throw new AturanAkademikException(sprintf(
                'Periode "%s" berstatus %s dan tidak menerima capaian baru.',
                $periode->nama,
                $periode->status->label(),
            ));
        }

        $ukuran = UkuranKinerja::query()
            ->with('sasaran.unit')
            ->whereHas('sasaran', fn ($q) => $q->where('periode_kinerja_id', $periode->id))
            ->where('sumber_realisasi', SumberRealisasi::Dihitung->value)
            ->get();

        $jumlah = 0;

        foreach ($ukuran as $satu) {
            $unit = $satu->sasaran->unit;

            if ($unit === null) {
                continue;
            }

            CapaianKinerja::create([
                'ukuran_kinerja_id' => $satu->id,
                'tanggal' => now()->toDateString(),
                'nilai' => $this->pengukur->ukur(
                    (string) $satu->indikator_kunci,
                    $unit,
                    $periode->tahunAkademik,
                ),
                'sumber' => SumberRealisasi::Dihitung,
            ]);

            $jumlah++;
        }

        return $jumlah;
    }

    /* ------------------------------------------------------------------
     | Periode
     |----------------------------------------------------------------- */

    public function jalankan(PeriodeKinerja $periode): PeriodeKinerja
    {
        if ($periode->status !== StatusPeriodeKinerja::Draf) {
            throw new AturanAkademikException('Hanya periode berstatus draf yang dapat dijalankan.');
        }

        $periode->update(['status' => StatusPeriodeKinerja::Berjalan]);

        return $periode->refresh();
    }

    /**
     * Closes a period and freezes every measure in it.
     *
     * One way. A period that can be reopened is a period whose figures can be
     * revised after they were reported, and the point of freezing is that the
     * report and the record still agree a year later.
     */
    public function kunci(PeriodeKinerja $periode, Staff $staff): PeriodeKinerja
    {
        if ($periode->status === StatusPeriodeKinerja::Dikunci) {
            throw new AturanAkademikException('Periode ini sudah dikunci.');
        }

        return DB::transaction(function () use ($periode, $staff): PeriodeKinerja {
            $ukuran = UkuranKinerja::query()
                ->with('capaian')
                ->whereHas('sasaran', fn ($q) => $q->where('periode_kinerja_id', $periode->id))
                ->get();

            foreach ($ukuran as $satu) {
                $satu->update([
                    'target_beku' => $satu->target,
                    'realisasi_beku' => $satu->realisasi(),
                ]);
            }

            $periode->update([
                'status' => StatusPeriodeKinerja::Dikunci,
                'dikunci_at' => now(),
                'dikunci_by_staff_id' => $staff->id,
            ]);

            return $periode->refresh();
        });
    }

    private function pastikanDapatDiubah(PeriodeKinerja $periode): void
    {
        if (!$periode->status->dapatDiubah()) {
            throw new AturanAkademikException(sprintf(
                'Periode "%s" sudah dikunci; sasaran dan targetnya tidak dapat diubah lagi.',
                $periode->nama,
            ));
        }
    }

    /* ------------------------------------------------------------------
     | Baca
     |----------------------------------------------------------------- */

    /** @return Collection<int, SasaranKinerja> */
    public function pohonSasaran(PeriodeKinerja $periode): Collection
    {
        return SasaranKinerja::query()
            ->with(['unit.kepalaStaff', 'unit.kepalaDosen', 'ukuran.capaian'])
            ->where('periode_kinerja_id', $periode->id)
            ->orderBy('parent_id')
            ->orderBy('urutan')
            ->get();
    }
}
