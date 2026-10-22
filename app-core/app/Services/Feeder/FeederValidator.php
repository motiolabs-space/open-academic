<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\JenisEvaluasi;
use App\Enums\StudentStatus;
use App\Models\Akademik\KelasDosen;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\NilaiKomponen;
use App\Models\Feeder\FeederMapping;
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
            'dosen_pengajar' => $this->dosenPengajar($model),
            'komponen_evaluasi' => $this->komponenEvaluasi($model),
            'krs' => $this->krs($model),
            'nilai' => $this->nilai($model),
            'nilai_komponen' => $this->nilaiKomponen($model),
            'lulus_do' => $this->lulusDo($model),
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

    /** @param KelasDosen $kd */
    private function dosenPengajar(Model $kd): Collection
    {
        $isu = collect();

        if (blank($kd->kelasKuliah?->feeder_id)) {
            $isu->push($this->error('kelas_belum_sinkron', 'Kelas kuliah belum terkirim ke Feeder.'));
        }

        // id_dosen is PDDIKTI's identifier, not ours. It arrives only by
        // reading Feeder's lecturer list back (tarik referensi); a lecturer
        // never registered at PDDIKTI admin has none and cannot be reported.
        if (blank($kd->dosen?->feeder_id)) {
            $isu->push($this->error(
                'dosen_belum_dipetakan',
                "Dosen {$kd->dosen?->nama} belum dipetakan ke id_dosen PDDIKTI. "
                .'Tarik referensi (GetListDosen) setelah dosen terdaftar di PDDIKTI admin.',
            ));
        }

        $sksKelas = (int) ($kd->kelasKuliah?->sks ?? 0);

        if ($kd->porsi_sks !== null && (float) $kd->porsi_sks > $sksKelas) {
            $isu->push($this->error('porsi_melebihi_kelas', "Porsi SKS dosen ({$kd->porsi_sks}) melebihi SKS kelas ({$sksKelas})."));
        }

        // Team teaching without a recorded split reports the whole class to
        // every lecturer. That may be what the campus means; it must not be
        // what the campus gets by accident.
        if ($kd->porsi_sks === null && $kd->kelasKuliah !== null && $kd->kelasKuliah->dosen()->count() > 1) {
            $isu->push($this->peringatan(
                'porsi_sks_kosong',
                'Kelas diampu lebih dari satu dosen tetapi porsi SKS tidak diisi; seluruh SKS kelas akan dilaporkan untuk dosen ini.',
            ));
        }

        return $isu;
    }

    /** @param KomponenNilai $k */
    private function komponenEvaluasi(Model $k): Collection
    {
        $isu = collect();
        $kelas = $k->kelasKuliah;

        if (blank($kelas?->feeder_id)) {
            $isu->push($this->error('kelas_belum_sinkron', 'Kelas kuliah belum terkirim ke Feeder.'));
        }

        if ($k->jenis_evaluasi === null) {
            $isu->push($this->error('jenis_evaluasi_kosong', "Komponen \"{$k->nama}\" belum punya jenis evaluasi (partisipatif, proyek, tugas, kuis, UTS, UAS)."));
        } elseif (FeederMapping::toFeeder('jenis_evaluasi', $k->jenis_evaluasi->value) === null) {
            $isu->push($this->error('jenis_evaluasi_belum_dipetakan', "Jenis evaluasi \"{$k->jenis_evaluasi->label()}\" belum dipetakan ke kode PDDIKTI (feeder_mappings, grup jenis_evaluasi)."));
        }

        if ($kelas === null) {
            return $isu;
        }

        // Class-level rules, reported once: on the first component only, so a
        // plan of five components yields one finding, not five copies.
        $semua = $kelas->komponenNilai;

        if ($semua->first()?->is($k)) {
            $total = (int) $semua->sum('bobot');

            if ($total !== 100) {
                $isu->push($this->error('bobot_tidak_seratus', "Bobot komponen kelas berjumlah {$total}, bukan 100."));
            }

            // IKU 7 is decided here, not by the class flag. A flagged class
            // whose plan is all exams is reported as flagged — and not counted.
            if ($kelas->kelasKolaboratif()) {
                $kolaboratif = (int) $semua
                    ->filter(fn (KomponenNilai $c): bool => $c->jenis_evaluasi?->menghitungIku7() ?? false)
                    ->sum('bobot');

                if ($kolaboratif < JenisEvaluasi::AMBANG_IKU7_PERSEN) {
                    $isu->push($this->peringatan(
                        'iku7_bobot_kurang',
                        sprintf(
                            'Kelas ditandai case method / team-based project, tetapi bobot partisipatif + proyek hanya %d%% (syarat IKU 7: %d%%). PDDIKTI tidak akan mengakuinya sebagai kelas kolaboratif.',
                            $kolaboratif,
                            JenisEvaluasi::AMBANG_IKU7_PERSEN,
                        ),
                    ));
                }
            }
        }

        return $isu;
    }

    /** @param NilaiKomponen $n */
    private function nilaiKomponen(Model $n): Collection
    {
        $isu = collect();

        if (blank($n->krsDetail?->krs?->mahasiswa?->feeder_registrasi_id)) {
            $isu->push($this->error('registrasi_belum_sinkron', 'Riwayat pendidikan mahasiswa belum terkirim ke Feeder.'));
        }

        if (blank($n->komponen?->kelasKuliah?->feeder_id)) {
            $isu->push($this->error('kelas_belum_sinkron', 'Kelas kuliah belum terkirim ke Feeder.'));
        }

        if (blank($n->komponen?->feeder_id)) {
            $isu->push($this->error('komponen_belum_sinkron', 'Komponen evaluasi kelas belum terkirim ke Feeder.'));
        }

        if ($n->nilai !== null && ((float) $n->nilai < 0 || (float) $n->nilai > 100)) {
            $isu->push($this->error('angka_di_luar_rentang', 'Nilai komponen di luar rentang 0–100.'));
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

    /** @param Mahasiswa $m */
    private function lulusDo(Model $m): Collection
    {
        $isu = collect();

        if (blank($m->feeder_registrasi_id)) {
            $isu->push($this->error('registrasi_belum_sinkron', 'Riwayat pendidikan mahasiswa belum terkirim ke Feeder.'));
        }

        if (FeederMapping::toFeeder('jenis_keluar', $m->status->value) === null) {
            $isu->push($this->error(
                'jenis_keluar_belum_dipetakan',
                "Status \"{$m->status->label()}\" belum dipetakan ke kode jenis keluar PDDIKTI (feeder_mappings, grup jenis_keluar).",
            ));
        }

        if ($m->status === StudentStatus::Lulus) {
            $y = $m->yudisium;

            if ($y === null || $y->status !== 'ditetapkan') {
                $isu->push($this->error('yudisium_belum_ditetapkan', 'Mahasiswa berstatus Lulus tanpa yudisium yang ditetapkan.'));

                return $isu;
            }

            if ($y->tanggal_lulus === null) {
                $isu->push($this->error('tanggal_keluar_kosong', 'Tanggal lulus pada yudisium belum diisi.'));
            }

            if ((float) $y->ipk > 4.0) {
                $isu->push($this->error('indeks_di_luar_rentang', 'IPK yudisium melebihi 4,00.'));
            }

            if (blank($y->nomor_sk)) {
                $isu->push($this->peringatan('nomor_sk_kosong', 'Nomor SK yudisium kosong; PDDIKTI menerimanya, tetapi reservasi nomor ijazah menanyakannya.'));
            }

            return $isu;
        }

        if ($m->evaluasiStudi->first() === null) {
            $isu->push($this->error('keputusan_tidak_ditemukan', 'Tidak ada keputusan evaluasi studi yang mengakhiri registrasi mahasiswa ini.'));
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
