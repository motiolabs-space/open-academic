<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use Illuminate\Support\Collection;

/**
 * The org chart, and the three ways it can be corrupted.
 *
 * A tree stored as parent pointers has exactly one structural failure mode —
 * a cycle — and it is silent: nothing breaks on write, and every traversal
 * afterwards runs forever. So it is refused here, where the reason can be
 * explained, rather than discovered by a page that stops responding.
 */
class UnitKerjaService
{
    public function simpan(UnitKerja $unit, array $data): UnitKerja
    {
        $indukId = $data['parent_id'] ?? null;

        if ($indukId !== null) {
            $this->pastikanBukanLingkaran($unit, (int) $indukId);
        }

        $this->pastikanSatuKepala($data);

        $unit->fill($data)->save();

        return $unit->refresh();
    }

    public function buat(array $data): UnitKerja
    {
        $this->pastikanSatuKepala($data);

        return UnitKerja::create($data);
    }

    /**
     * Refuses a parent that is the unit itself or one of its own descendants.
     *
     * Walks up from the proposed parent rather than down from the unit: the
     * ancestor chain is at most as deep as the tree, while the descendant set
     * can be the whole campus.
     */
    private function pastikanBukanLingkaran(UnitKerja $unit, int $indukId): void
    {
        if ($unit->exists && $indukId === $unit->id) {
            throw new AturanAkademikException('Sebuah unit tidak dapat menjadi induk dirinya sendiri.');
        }

        if (!$unit->exists) {
            return;
        }

        $semua = UnitKerja::withTrashed()->get(['id', 'parent_id', 'nama']);
        $kini = $semua->firstWhere('id', $indukId);

        for ($i = 0; $i < $semua->count() && $kini !== null; $i++) {
            if ((int) $kini->id === (int) $unit->id) {
                throw new AturanAkademikException(sprintf(
                    'Unit "%s" berada di bawah "%s", jadi menjadikannya induk akan membentuk lingkaran.',
                    $kini->nama,
                    $unit->nama,
                ));
            }

            $kini = $kini->parent_id === null ? null : $semua->firstWhere('id', $kini->parent_id);
        }
    }

    /**
     * A unit has one head, from one table.
     *
     * Both columns exist because a dean is a lecturer and a bureau head is
     * administrative staff. Both being set is not a richer answer, it is two
     * answers — and every screen that renders "kepala unit" would then have to
     * pick one, differently.
     */
    private function pastikanSatuKepala(array $data): void
    {
        if (filled($data['kepala_staff_id'] ?? null) && filled($data['kepala_dosen_id'] ?? null)) {
            throw new AturanAkademikException(
                'Pilih satu kepala unit saja — dari staf atau dari dosen, tidak keduanya.',
            );
        }
    }

    /**
     * Retires a unit.
     *
     * Refused while people are still filed under it. Deactivating quietly would
     * leave those staff pointing at a unit that no longer appears in any list,
     * which reads as "no unit" on every screen and as a missing row in every
     * report — the same invisible failure the free-text column used to cause.
     */
    public function nonaktifkan(UnitKerja $unit): UnitKerja
    {
        $jumlahStaf = $unit->staf()->count();

        if ($jumlahStaf > 0) {
            throw new AturanAkademikException(sprintf(
                'Masih ada %d staf pada unit "%s". Pindahkan mereka lebih dulu.',
                $jumlahStaf,
                $unit->nama,
            ));
        }

        $jumlahAnak = $unit->anak()->aktif()->count();

        if ($jumlahAnak > 0) {
            throw new AturanAkademikException(sprintf(
                'Unit "%s" masih membawahi %d unit aktif.',
                $unit->nama,
                $jumlahAnak,
            ));
        }

        $unit->update(['is_active' => false]);

        return $unit->refresh();
    }

    public function pindahkanStaf(Staff $staf, UnitKerja $unit): Staff
    {
        if (!$unit->is_active) {
            throw new AturanAkademikException(sprintf(
                'Unit "%s" sudah tidak aktif dan tidak dapat menerima penempatan baru.',
                $unit->nama,
            ));
        }

        $staf->update(['unit_kerja_id' => $unit->id]);

        return $staf->refresh();
    }

    /**
     * The whole tree in one query, ordered for display.
     *
     * @return Collection<int, UnitKerja>
     */
    public function pohon(): Collection
    {
        return UnitKerja::query()
            ->with(['kepalaStaff', 'kepalaDosen'])
            ->withCount('staf')
            ->orderBy('jenis')
            ->orderBy('kode')
            ->get();
    }

    /**
     * Head count for a unit including everything beneath it.
     *
     * The number a dean or a bureau head actually asks for: "how many people do
     * I have", not "how many are filed at exactly my level".
     *
     * @param Collection<int, UnitKerja> $semua
     */
    public function jumlahStafTermasukBawahan(UnitKerja $unit, Collection $semua): int
    {
        return (int) $unit->turunan($semua)->sum(fn (UnitKerja $u): int => (int) ($u->staf_count ?? 0));
    }

    /** @return array<int, Dosen> */
    public function calonKepalaDosen(): array
    {
        return Dosen::query()->orderBy('nama')->get()->all();
    }
}
