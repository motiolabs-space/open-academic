<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Aktivitas Mahasiswa — an MBKM programme (internship, exchange, independent
 * study, ...) as PDDIKTI records it.
 *
 * Only verified activities are reported: an unverified one is a claim, and
 * IKU 2 is computed from what PDDIKTI holds, so a claim that reaches it
 * counts whether or not it was ever true.
 *
 * The activity kind goes through the mapping table (group jenis_aktivitas),
 * filled from the GetJenisAktivitasMahasiswa reference pull. It is not
 * seeded: PDDIKTI's list changes with each MBKM cycle, and a guessed code
 * reports a student's internship as something else.
 *
 * Managed KKN and PKL participants (#37) travel the same route. Their
 * programme's decree goes along as `sk_tugas`, and a regular KKN or PKL is
 * sent with `untuk_kampus_merdeka` 0 — only the MBKM kinds are flagged.
 */
class AktivitasMbkmMapper extends FeederMapper
{
    public function act(): string
    {
        return 'InsertAktivitasMahasiswa';
    }

    /** @return Builder<AktivitasMahasiswa> */
    protected function query(string $termCode): Builder
    {
        return AktivitasMahasiswa::query()
            ->with(['mahasiswa.prodi', 'tahunAkademik', 'pembimbing', 'kegiatanLapangan'])
            ->terverifikasi()
            ->whereHas('tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode))
            ->orderBy('id');
    }

    /** @param AktivitasMahasiswa $model */
    public function payload(Model $model): array
    {
        return array_filter([
            'id_jenis_aktivitas' => $this->kode('jenis_aktivitas', $model->jenis->value),
            'id_prodi' => $model->mahasiswa->prodi->kode_pddikti,
            'id_semester' => $model->tahunAkademik->kode,
            'judul' => $model->judul,
            'keterangan' => $model->keterangan,
            'lokasi' => $model->lokasi ?? $model->mitra_nama,
            'sk_tugas' => $model->kegiatanLapangan?->nomor_sk,
            'tanggal_sk_tugas' => $this->tanggal($model->kegiatanLapangan?->tanggal_sk ?? $model->tanggal_mulai),
            'untuk_kampus_merdeka' => $model->jenis->kampusMerdeka() ? 1 : 0,
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function label(Model $model): string
    {
        /** @var AktivitasMahasiswa $model */
        return $model->mahasiswa->nim.' · '.$model->jenis->label();
    }
}
