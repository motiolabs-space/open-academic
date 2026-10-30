<?php

declare(strict_types=1);

namespace App\Services\Feeder\Mappers;

use App\Enums\KeputusanEvaluasi;
use App\Enums\StudentStatus;
use App\Models\Kemahasiswaan\EvaluasiStudi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Feeder\MedanFeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Mahasiswa Lulus / DO — the record that closes a student's registration at
 * PDDIKTI.
 *
 * Until this row is sent, a graduate stays *Aktif* in the national database
 * no matter what the yudisium here says, and PISN issues no diploma number
 * for a student who is not Lulus at PDDIKTI. The diploma number itself is not
 * sent: PISN writes it to PDDIKTI after this record lands (ADR-036 §2).
 *
 * Two local events feed one Feeder entity: a confirmed yudisium (status
 * Lulus) and a decided study evaluation (drop-out or resignation). The
 * student row is the unit pushed, because Feeder keys this record on the
 * student's registration, not on the yudisium or the evaluation.
 *
 * The exit *kind* goes through the mapping table like every other code. A
 * campus that runs a Feeder build with a different jenis_keluar set changes a
 * row there, not this file.
 */
class LulusDoMapper extends FeederMapper
{
    /** Which decisions end a registration, and as what local status. */
    private const KEPUTUSAN_KELUAR = [
        KeputusanEvaluasi::DropOut,
        KeputusanEvaluasi::MengundurkanDiri,
    ];

    public function act(): string
    {
        return 'InsertMahasiswaLulusDO';
    }

    public function medan(): array
    {
        return [
            'id_registrasi_mahasiswa',
            'id_jenis_keluar',
            'tanggal_keluar',
            'id_periode_keluar',
            'nomor_sk_yudisium',
            'tanggal_sk_yudisium',
            'ipk',
            'judul_skripsi',
            'keterangan',
            ...array_filter([MedanFeeder::tanggalIjazah()]),
        ];
    }

    /**
     * Students whose exit was decided in this term.
     *
     * A yudisium for the term is enough to make the row due, whatever its
     * status: a student already marked Lulus whose yudisium is still
     * "diajukan" must show up as a validation error, not quietly fall out of
     * the list and stay Aktif at PDDIKTI for another semester.
     *
     * @return Builder<Mahasiswa>
     */
    protected function query(string $termCode): Builder
    {
        $keputusan = array_map(fn (KeputusanEvaluasi $k): string => $k->value, self::KEPUTUSAN_KELUAR);

        return Mahasiswa::query()
            ->with([
                'perubahanFeeder' => fn ($q) => $q->terbuka(),
                'prodi',
                'ujiKompetensiLulus.tahunAkademik',
                'yudisium.tahunAkademik',
                'yudisium.pesertaWisuda.periode',
                'evaluasiStudi' => fn ($q) => $q->whereIn('keputusan', $keputusan)->latest('diputuskan_at'),
                // Ids only: the validator needs to know there is at least one.
                'statusPerSemester:id,mahasiswa_id',
            ])
            ->whereIn('status', [
                StudentStatus::Lulus->value,
                StudentStatus::DropOut->value,
                StudentStatus::Keluar->value,
            ])
            ->where(fn (Builder $q) => $q
                ->whereHas('yudisium', fn (Builder $y) => $y
                    ->whereHas('tahunAkademik', fn (Builder $t) => $t->where('kode', $termCode)))
                // A programme with a competency examination graduates its
                // students in the term they pass it, often terms after the
                // yudisium. Without this clause they would only ever be due
                // in a term the operator has long since stopped syncing.
                ->orWhereHas('ujiKompetensiLulus', fn (Builder $u) => $u
                    ->whereHas('tahunAkademik', fn (Builder $t) => $t->where('kode', $termCode)))
                ->orWhereIn('id', EvaluasiStudi::query()
                    ->select('mahasiswa_id')
                    ->whereIn('keputusan', $keputusan)
                    ->whereHas('tahunAkademik', fn (Builder $t) => $t->where('kode', $termCode))))
            ->orderBy('nim');
    }

    /** @param Mahasiswa $model */
    public function payload(Model $model): array
    {
        $yudisium = $model->status === StudentStatus::Lulus ? $model->yudisium : null;
        $evaluasi = $yudisium === null ? $model->evaluasiStudi->first() : null;

        // For a programme with a competency examination the exit term is the
        // term of the passing sitting; yudisium.tanggal_lulus already holds
        // its date (UjiKompetensiService).
        $periodeUkom = $yudisium !== null && $model->prodi?->wajib_ukom
            ? $model->ujiKompetensiLulus?->tahunAkademik?->kode
            : null;

        // Sent only once the campus has named the field after its own
        // Feeder's dictionary (ROADMAP #53); until then a guessed name would
        // be refused, or worse, quietly ignored.
        $medanIjazah = MedanFeeder::tanggalIjazah();
        $tanggalIjazah = $medanIjazah !== null && $yudisium !== null
            ? [$medanIjazah => $this->tanggal(MedanFeeder::tanggalIjazahDari($yudisium))]
            : [];

        return array_filter([
            ...$tanggalIjazah,
            'id_registrasi_mahasiswa' => $model->feeder_registrasi_id,
            'id_jenis_keluar' => $this->kode('jenis_keluar', $model->status->value),
            'tanggal_keluar' => $this->tanggal($yudisium?->tanggal_lulus ?? $evaluasi?->diputuskan_at),
            'id_periode_keluar' => $periodeUkom ?? $yudisium?->tahunAkademik->kode ?? $evaluasi?->tahunAkademik->kode,
            'nomor_sk_yudisium' => $yudisium?->nomor_sk,
            'tanggal_sk_yudisium' => $this->tanggal($yudisium?->tanggal_yudisium),
            'ipk' => $yudisium !== null ? number_format((float) $yudisium->ipk, 2, '.', '') : null,
            'judul_skripsi' => $yudisium?->judul_tugas_akhir,
            // The decision label, never the written reason: that text is for
            // the campus, not the national database.
            'keterangan' => $evaluasi?->keputusan->label(),
        ], fn (mixed $nilai): bool => $nilai !== null && $nilai !== '');
    }

    public function feederId(Model $model): ?string
    {
        return $model->getAttribute('feeder_keluar_id');
    }

    /**
     * Feeder keys this record on the registration it already knows, so the
     * reply carries no new identifier worth storing. The registration id is
     * recorded instead; it is what an update is keyed on.
     */
    public function feederIdBawaan(Model $model): ?string
    {
        return $model->getAttribute('feeder_registrasi_id');
    }

    public function simpanFeederId(Model $model, string $feederId): void
    {
        $model->forceFill([
            'feeder_keluar_id' => $model->getAttribute('feeder_registrasi_id') ?? $feederId,
            'feeder_synced_at' => now(),
        ])->saveQuietly();
    }

    public function label(Model $model): string
    {
        /** @var Mahasiswa $model */
        return $model->nim.' · '.$model->status->label();
    }
}
