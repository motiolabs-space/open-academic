<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\StatusCpmk;
use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\ProdiCpl;
use App\Services\Akademik\CpmkService;

/**
 * What the shared CPMK editor needs, for the staff screen and the RPS screen.
 *
 * One editor and one service for both, so a course's outcomes are written the
 * same way whoever writes them — the one-source rule of ADR-038 applied to the
 * screen as well as to the data.
 *
 * @property-read CpmkService $cpmk
 */
trait MenyusunCpmk
{
    /**
     * @param array{draf: string, simpan: callable(CpmkVersi): string, terbitkan: callable(CpmkVersi): string} $rute
     * @return array<string, mixed>
     */
    protected function dataEditor(Kurikulum $kurikulum, MataKuliah $mataKuliah, array $rute, bool $bolehMenyusun): array
    {
        $riwayat = $this->cpmk->riwayat($kurikulum, $mataKuliah);

        $draf = $riwayat->first(fn (CpmkVersi $v): bool => $v->status === StatusCpmk::Draft);
        $berlaku = $riwayat->first(fn (CpmkVersi $v): bool => $v->status === StatusCpmk::Berlaku);

        $tampil = ($draf ?? $berlaku)?->load(CpmkVersi::MUAT);

        return [
            'kurikulum' => $kurikulum,
            'mataKuliah' => $mataKuliah,
            'versi' => $tampil,
            'berlaku' => $berlaku,
            'riwayat' => $riwayat,
            'dapatSunting' => $bolehMenyusun && $draf !== null,
            'bolehMenyusun' => $bolehMenyusun,
            'kekurangan' => $draf === null ? [] : $this->cpmk->kekurangan($tampil),
            'cplPilihan' => ProdiCpl::query()
                ->where('prodi_id', $kurikulum->prodi_id)
                ->orderBy('kode')
                ->get(['id', 'kode', 'deskripsi']),
            'ruteDraf' => $rute['draf'],
            'ruteSimpan' => $tampil === null ? null : ($rute['simpan'])($tampil),
            'ruteTerbitkan' => $tampil === null ? null : ($rute['terbitkan'])($tampil),
        ];
    }
}
