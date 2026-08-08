<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Nilai;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Services\Feeder\Mappers\FeederMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pre-flight validation: which rows Neo Feeder would reject, found before a
 * single one is sent.
 *
 * This is the difference between an operator spending an afternoon fixing a
 * list, and spending three days discovering problems one failed push at a
 * time, two weeks before a reporting deadline. Errors block a sync; warnings
 * are reported and let it proceed.
 */
class FeederValidator
{
    /**
     * Checks every row of an entity for a term and records what it finds.
     *
     * @return array{batch: string, error: int, warning: int, diperiksa: int}
     */
    public function periksa(string $entity, string $termCode, FeederMapper $mapper): array
    {
        $batch = (string) Str::uuid();
        $rows = $mapper->rows($termCode);

        $temuan = $rows->flatMap(fn (Model $model): Collection => $this
            ->periksaBaris($entity, $model, $mapper)
            ->map(fn (array $isu): array => $isu + [
                'batch_id' => $batch,
                'entity' => $entity,
                'local_type' => $model->getMorphClass(),
                'local_id' => $model->getKey(),
                'local_label' => $mapper->label($model),
                'created_at' => now(),
            ]));

        if ($temuan->isNotEmpty()) {
            FeederValidationIssue::insert($temuan->all());
        }

        return [
            'batch' => $batch,
            'error' => $temuan->where('severity', 'error')->count(),
            'warning' => $temuan->where('severity', 'warning')->count(),
            'diperiksa' => $rows->count(),
        ];
    }

    /**
     * Rules for one row.
     *
     * @return Collection<int, array{rule: string, severity: string, message: string}>
     */
    private function periksaBaris(string $entity, Model $model, FeederMapper $mapper): Collection
    {
        return match ($entity) {
            'mahasiswa' => $this->mahasiswa($model),
            'riwayat_pendidikan' => $this->riwayatPendidikan($model),
            'aktivitas_kuliah' => $this->aktivitasKuliah($model),
            'kelas_kuliah' => $this->kelasKuliah($model),
            'krs' => $this->krs($model),
            'nilai' => $this->nilai($model),
            default => collect(),
        };
    }

    /** @param Mahasiswa $m */
    private function mahasiswa(Model $m): Collection
    {
        $isu = collect();

        // Feeder refuses biodata without a NIK outright — this is the single
        // most common reason a campus's first sync attempt fails.
        if (blank($m->nik)) {
            $isu->push($this->error('nik_kosong', 'NIK belum diisi. Feeder menolak biodata tanpa NIK.'));
        } elseif (!preg_match('/^\d{16}$/', (string) $m->nik)) {
            $isu->push($this->error('nik_tidak_valid', 'NIK harus 16 digit angka.'));
        }

        if (blank($m->tempat_lahir) || $m->tanggal_lahir === null) {
            $isu->push($this->error('kelahiran_kosong', 'Tempat dan tanggal lahir wajib diisi.'));
        }

        if ($m->jenis_kelamin === null) {
            $isu->push($this->error('jenis_kelamin_kosong', 'Jenis kelamin wajib diisi.'));
        }

        if (blank($m->nama_ibu)) {
            $isu->push($this->peringatan('nama_ibu_kosong', 'Nama ibu kandung kosong; sebagian aturan Feeder mewajibkannya.'));
        }

        if (blank($m->agama_kode)) {
            $isu->push($this->peringatan('agama_kosong', 'Agama belum diisi; akan dikirim tanpa id_agama.'));
        }

        return $isu;
    }

    /** @param Mahasiswa $m */
    private function riwayatPendidikan(Model $m): Collection
    {
        $isu = collect();

        if (blank($m->feeder_id)) {
            $isu->push($this->error('biodata_belum_sinkron', 'Biodata mahasiswa belum terkirim ke Feeder.'));
        }

        if (blank($m->prodi?->kode_pddikti)) {
            $isu->push($this->error('prodi_tanpa_id_sms', 'Program studi belum memiliki kode PDDIKTI (id_sms).'));
        }

        if (blank($m->term_masuk)) {
            $isu->push($this->error('periode_masuk_kosong', 'Periode masuk (term) belum ditetapkan.'));
        }

        return $isu;
    }

    /** @param StatusMahasiswa $s */
    private function aktivitasKuliah(Model $s): Collection
    {
        $isu = collect();

        if (blank($s->mahasiswa?->feeder_registrasi_id)) {
            $isu->push($this->error('registrasi_belum_sinkron', 'Riwayat pendidikan mahasiswa belum terkirim ke Feeder.'));
        }

        if ((float) $s->ipk > 4.0 || (float) $s->ips > 4.0) {
            $isu->push($this->error('indeks_di_luar_rentang', 'IPS/IPK melebihi 4,00.'));
        }

        if (!$s->is_final) {
            $isu->push($this->peringatan('semester_belum_final', 'Nilai semester ini belum difinalisasi; IPS/IPK masih dapat berubah.'));
        }

        return $isu;
    }

    /** @param KelasKuliah $k */
    private function kelasKuliah(Model $k): Collection
    {
        $isu = collect();

        $pengampu = $k->dosenPengampu->first();

        if ($pengampu === null) {
            $isu->push($this->error('tanpa_pengampu', 'Kelas belum memiliki dosen pengampu.'));
        } elseif (blank($pengampu->nidn)) {
            // Practitioners routinely have no NIDN; Feeder still needs one for
            // the class record, so this must surface before the deadline.
            $isu->push($this->error(
                'pengampu_tanpa_nidn',
                "Dosen pengampu {$pengampu->nama} belum memiliki NIDN/NIDK.",
            ));
        }

        if (blank($k->prodi?->kode_pddikti)) {
            $isu->push($this->error('prodi_tanpa_id_sms', 'Program studi belum memiliki kode PDDIKTI (id_sms).'));
        }

        if ((int) $k->sks <= 0) {
            $isu->push($this->error('sks_nol', 'Jumlah SKS kelas belum ditetapkan.'));
        }

        return $isu;
    }

    /** @param KrsDetail $d */
    private function krs(Model $d): Collection
    {
        $isu = collect();

        if (blank($d->krs?->mahasiswa?->feeder_registrasi_id)) {
            $isu->push($this->error('registrasi_belum_sinkron', 'Riwayat pendidikan mahasiswa belum terkirim ke Feeder.'));
        }

        if (blank($d->kelasKuliah?->feeder_id)) {
            $isu->push($this->error('kelas_belum_sinkron', 'Kelas kuliah belum terkirim ke Feeder.'));
        }

        return $isu;
    }

    /** @param Nilai $n */
    private function nilai(Model $n): Collection
    {
        $isu = collect();

        if (blank($n->kelasKuliah?->feeder_id)) {
            $isu->push($this->error('kelas_belum_sinkron', 'Kelas kuliah belum terkirim ke Feeder.'));
        }

        if ($n->nilai_huruf === null) {
            $isu->push($this->error('huruf_kosong', 'Nilai huruf belum ditetapkan.'));
        }

        if ($n->nilai_angka !== null && ((float) $n->nilai_angka < 0 || (float) $n->nilai_angka > 100)) {
            $isu->push($this->error('angka_di_luar_rentang', 'Nilai angka di luar rentang 0–100.'));
        }

        return $isu;
    }

    /** @return array{rule: string, severity: string, message: string} */
    private function error(string $rule, string $message): array
    {
        return ['rule' => $rule, 'severity' => 'error', 'message' => $message];
    }

    /** @return array{rule: string, severity: string, message: string} */
    private function peringatan(string $rule, string $message): array
    {
        return ['rule' => $rule, 'severity' => 'warning', 'message' => $message];
    }
}
