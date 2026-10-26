<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\KesiapanPin;
use App\DTOs\Kemahasiswaan\SyaratWisuda;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Support\Format;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Who may register for a graduation ceremony, and why not.
 *
 * One implementation, read by the staff queue, the student's own screen, and
 * WisudaService itself before it writes a row. Before this existed the only
 * rule was "yudisium ditetapkan", checked inside the registration method; a
 * student screen built on top would have needed its own copy to decide whether
 * to enable the button, and the two copies would have drifted the first time
 * somebody added a requirement to one of them.
 *
 * Recomputed on every call, like the graduation checklist: an invoice settled
 * this morning must unlock the button this morning.
 *
 * There is no library-clearance row. No loans module exists to read from, and a
 * row that always passes would make the checklist claim a check it never made.
 * When one exists, it belongs here as a required row.
 */
class SyaratWisudaService
{
    public function __construct(private readonly KesiapanPinService $kesiapanPin) {}

    public function periksa(Mahasiswa $mahasiswa): SyaratWisuda
    {
        return $this->periksaBanyak(collect([$mahasiswa]))[$mahasiswa->id];
    }

    /**
     * The same checklist for many graduates in a fixed number of queries — the
     * staff queue renders one row per waiting graduate.
     *
     * @param Collection<int, Mahasiswa> $mahasiswa
     * @return Collection<int, SyaratWisuda> keyed by student id
     */
    public function periksaBanyak(Collection $mahasiswa): Collection
    {
        $mahasiswa = EloquentCollection::make($mahasiswa->unique('id')->values()->all());

        if ($mahasiswa->isEmpty()) {
            return collect();
        }

        $mahasiswa->loadMissing(['yudisium', 'prodi', 'ujiKompetensiLulus']);

        $tunggakan = Tagihan::tunggakanPerMahasiswa($mahasiswa->modelKeys());
        $pin = $this->kesiapanPin->periksaBanyak($mahasiswa);

        return $mahasiswa->mapWithKeys(fn (Mahasiswa $m): array => [
            $m->id => new SyaratWisuda([
                $this->barisYudisium($m),
                ...$this->barisUkom($m),
                $this->barisKeuangan((int) ($tunggakan[$m->id] ?? 0)),
                $this->barisPin($pin[$m->id]),
            ]),
        ]);
    }

    /**
     * Only for programmes that require the competency examination; omitted
     * elsewhere rather than shown as a pass.
     *
     * Required, not informational: the diploma handed over at the ceremony is
     * not issued until the examination is passed, and the diploma number
     * cannot be reserved for somebody PDDIKTI still lists as waiting.
     *
     * @return array<int, array{kode: string, label: string, terpenuhi: bool, wajib: bool, keterangan: string}>
     */
    private function barisUkom(Mahasiswa $m): array
    {
        if (!$m->prodi?->wajib_ukom) {
            return [];
        }

        $lulus = $m->ujiKompetensiLulus;

        return [[
            'kode' => 'ukom',
            'label' => __('ukom.syarat_wisuda'),
            'terpenuhi' => $lulus !== null,
            'wajib' => true,
            'keterangan' => $lulus !== null
                ? __('ukom.syarat_wisuda_lulus', ['tanggal' => Format::tanggal($lulus->tanggal_ujian)])
                : __('ukom.syarat_wisuda_belum'),
        ]];
    }

    /** @return array{kode: string, label: string, terpenuhi: bool, wajib: bool, keterangan: string} */
    private function barisYudisium(Mahasiswa $m): array
    {
        $yudisium = $m->yudisium;
        $ditetapkan = $yudisium?->status === 'ditetapkan';

        return [
            'kode' => 'yudisium',
            'label' => __('wisuda.syarat.yudisium'),
            'terpenuhi' => $ditetapkan,
            'wajib' => true,
            'keterangan' => match (true) {
                $yudisium === null => __('wisuda.syarat.yudisium_belum_ada'),
                !$ditetapkan => __('wisuda.syarat.yudisium_diajukan'),
                default => __('wisuda.syarat.yudisium_ditetapkan', [
                    'tanggal' => Format::tanggal($yudisium->tanggal_yudisium ?? $yudisium->ditetapkan_at),
                ]),
            },
        ];
    }

    /**
     * Unpaid late fees count; waived ones do not. See
     * Tagihan::tunggakanPerMahasiswa() — the same figure the graduation
     * checklist reads, so a graduate cleared by one is cleared by the other.
     *
     * @return array{kode: string, label: string, terpenuhi: bool, wajib: bool, keterangan: string}
     */
    private function barisKeuangan(int $tunggakan): array
    {
        return [
            'kode' => 'keuangan',
            'label' => __('wisuda.syarat.keuangan'),
            'terpenuhi' => $tunggakan <= 0,
            'wajib' => true,
            'keterangan' => $tunggakan > 0
                ? __('wisuda.syarat.keuangan_sisa', ['sisa' => Format::rupiah($tunggakan)])
                : __('wisuda.syarat.keuangan_lunas'),
        ];
    }

    /**
     * Informational only. Every item on the diploma-number checklist is fixed
     * by the campus in Feeder, not by the graduate; refusing the ceremony over
     * it would punish the wrong person. Shown so the graduate knows to ask.
     *
     * @return array{kode: string, label: string, terpenuhi: bool, wajib: bool, keterangan: string}
     */
    private function barisPin(KesiapanPin $pin): array
    {
        return [
            'kode' => 'pin',
            'label' => __('wisuda.syarat.pin'),
            'terpenuhi' => $pin->siap(),
            'wajib' => false,
            'keterangan' => $pin->siap()
                ? __('wisuda.syarat.pin_siap')
                : __('wisuda.syarat.pin_belum', ['jumlah' => $pin->jumlahBelumSiap()]),
        ];
    }
}
