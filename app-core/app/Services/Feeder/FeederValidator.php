<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\JalurPerubahanFeeder;
use App\Enums\JenisDaftar;
use App\Enums\JenisEvaluasi;
use App\Enums\JenisPerubahanFeeder;
use App\Enums\StatusAdopsiFeeder;
use App\Enums\StatusLampauFeeder;
use App\Enums\StudentStatus;
use App\Models\Akademik\KelasDosen;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\KurikulumMataKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\NilaiKomponen;
use App\Models\Akademik\PeriodePerkuliahan;
use App\Models\Akademik\SkalaNilaiProdi;
use App\Models\Feeder\FeederChangeRequest;
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederRef;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\TugasAkhir\TugasAkhir;
use App\Services\Feeder\Mappers\BimbingMbkmMapper;
use App\Services\Feeder\Mappers\BimbingTugasAkhirMapper;
use App\Services\Feeder\Mappers\FeederMapper;
use App\Services\Feeder\Mappers\TugasAkhirMapper;
use App\Services\Feeder\Mappers\UjiTugasAkhirMapper;
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
    /** @var array<string, array<string, true>> values shared by more than one student, per column */
    private array $kontakGanda = [];

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
            ->periksaBaris($entity, $model)
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
     * The same rules for one record, without recording anything.
     *
     * For screens that want to say "Feeder would refuse this" at the moment a
     * record is entered, using the very rules a sync will apply rather than a
     * second copy that drifts.
     *
     * @return Collection<int, array{rule: string, severity: string, message: string}>
     */
    public function isuUntuk(string $entity, Model $model): Collection
    {
        return $this->periksaBaris($entity, $model);
    }

    /**
     * Rules for one row.
     *
     * @return Collection<int, array{rule: string, severity: string, message: string}>
     */
    private function periksaBaris(string $entity, Model $model): Collection
    {
        return match ($entity) {
            'skala_nilai_prodi' => $this->skalaNilaiProdi($model),
            'periode_perkuliahan' => $this->periodePerkuliahan($model),
            'kurikulum' => $this->kurikulum($model),
            'mata_kuliah' => $this->mataKuliah($model),
            'matkul_kurikulum' => $this->matkulKurikulum($model),
            'mahasiswa' => $this->mahasiswa($model),
            'riwayat_pendidikan' => $this->riwayatPendidikan($model),
            'aktivitas_kuliah' => $this->aktivitasKuliah($model),
            'kelas_kuliah' => $this->kelasKuliah($model),
            'dosen_pengajar' => $this->dosenPengajar($model),
            'komponen_evaluasi' => $this->komponenEvaluasi($model),
            'krs' => $this->krs($model),
            'nilai' => $this->nilai($model),
            'nilai_komponen' => $this->nilaiKomponen($model),
            'aktivitas_mbkm' => $this->aktivitasMbkm($model),
            'anggota_mbkm' => $this->anggota($model->feeder_id, $model->mahasiswa),
            'bimbing_mbkm' => $this->pembimbing($model->feeder_id, $model->pembimbing, BimbingMbkmMapper::KATEGORI),
            'tugas_akhir' => $this->tugasAkhir($model),
            'anggota_tugas_akhir' => $this->anggota($model->feeder_id, $model->mahasiswa),
            'bimbing_tugas_akhir' => $this->pembimbing($model->tugasAkhir?->feeder_id, $model->dosen, BimbingTugasAkhirMapper::KATEGORI),
            'uji_tugas_akhir' => $this->pembimbing($model->ujian?->tugasAkhir?->feeder_id, $model->dosen, UjiTugasAkhirMapper::KATEGORI),
            'prestasi' => $this->prestasi($model),
            'transfer_kredit' => $this->transferKredit($model),
            'konversi_mbkm' => $this->konversiMbkm($model),
            'flagship_mbkm' => $this->flagshipMbkm($model),
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

        $isu = $isu->merge($this->kontakMahasiswa($m));

        // PDDIKTI may already hold this student. Inserting the biodata now
        // would register them a second time — or be refused for the NIK —
        // and either way the decision belongs to a person, not to a sync.
        if (blank($m->feeder_id) && $this->kandidatBelumDiputuskan($m)) {
            $isu->push($this->error('kandidat_feeder_belum_diadopsi', __('feeder.adopsi.validasi.belum_diputuskan')));
        } elseif (blank($m->feeder_id) && $this->klaimBelumDiadopsi($m)) {
            // Claimed from PDDIKTI's past students, so PDDIKTI holds them,
            // but adoption has not given them the record's identifiers yet.
            $isu->push($this->error('klaim_lampau_belum_diadopsi', __('feeder.lampau.validasi.belum_diadopsi')));
        }

        // Identity columns PDDIKTI froze when this biodata was first accepted
        // have changed here. Re-sending the biodata would be refused, or —
        // worse — accepted on an older Feeder build and silently diverge from
        // what PDDIKTI admin later decides. The request is on the console.
        $terkunci = $this->kolomTerkunciBerubah($m, JenisPerubahanFeeder::Pdm);

        if ($terkunci !== []) {
            $isu->push($this->error(
                'kolom_terkunci_berubah',
                'Kolom identitas yang dikunci PDDIKTI berubah sejak tersinkron: '.implode(', ', $terkunci)
                .'. Biodata tidak dikirim ulang; ajukan PDM lewat PDDIKTI admin dan tandai selesai pada panel Perubahan Data.',
            ));
        }

        return $isu;
    }

    /**
     * Contact details on the biodata (ADR-036 §3).
     *
     * Neo Feeder 3.0.1 made the student's mobile number mandatory, and 3.1
     * is reported to require an e-mail address and to refuse a number or an
     * address another student already uses. A missing number blocks; a
     * malformed or shared one is a warning until a Feeder build here refuses
     * it. The e-mail address is the login here, so it is never empty and
     * never shared — only its shape is checked.
     *
     * @return Collection<int, array{rule: string, severity: string, message: string}>
     */
    private function kontakMahasiswa(Mahasiswa $m): Collection
    {
        $isu = collect();
        $digit = preg_replace('/\D/', '', (string) $m->telepon) ?? '';

        if (blank($m->telepon)) {
            $isu->push($this->error('telepon_kosong', __('feeder.validasi.telepon_kosong')));
        } elseif (strlen($digit) < 9 || strlen($digit) > 15) {
            $isu->push($this->peringatan('telepon_tidak_valid', __('feeder.validasi.telepon_tidak_valid', ['telepon' => $m->telepon])));
        } elseif ($this->dipakaiLebihDariSatu('telepon', (string) $m->telepon)) {
            $isu->push($this->peringatan('telepon_ganda', __('feeder.validasi.telepon_ganda')));
        }

        if (filter_var($m->email, FILTER_VALIDATE_EMAIL) === false) {
            $isu->push($this->peringatan('email_tidak_valid', __('feeder.validasi.email_tidak_valid', ['email' => $m->email])));
        }

        return $isu;
    }

    /**
     * Whether another student holds the same value. One grouped query per
     * column per validator run, not one per row.
     */
    private function dipakaiLebihDariSatu(string $kolom, string $nilai): bool
    {
        $this->kontakGanda[$kolom] ??= Mahasiswa::query()
            ->whereNotNull($kolom)
            ->groupBy($kolom)
            ->havingRaw('count(*) > 1')
            ->pluck($kolom)
            ->mapWithKeys(fn ($v): array => [(string) $v => true])
            ->all();

        return isset($this->kontakGanda[$kolom][$nilai]);
    }

    /** Whether an adoption run left this student ambiguous, and nobody has decided since. */
    private function kandidatBelumDiputuskan(Mahasiswa $m): bool
    {
        return $m->relationLoaded('adopsiFeeder')
            ? $m->adopsiFeeder->contains(fn ($a): bool => $a->status === StatusAdopsiFeeder::Ambigu)
            : $m->adopsiFeeder()->status(StatusAdopsiFeeder::Ambigu)->exists();
    }

    /**
     * Whether a past-student record was claimed for this student and nobody
     * has since declared them to be someone else.
     */
    private function klaimBelumDiadopsi(Mahasiswa $m): bool
    {
        $diklaim = $m->relationLoaded('klaimLampauFeeder')
            ? $m->klaimLampauFeeder->contains(fn ($k): bool => $k->status === StatusLampauFeeder::Diklaim)
            : $m->klaimLampauFeeder()->status(StatusLampauFeeder::Diklaim)->exists();

        if (!$diklaim) {
            return false;
        }

        return $m->relationLoaded('adopsiFeeder')
            ? !$m->adopsiFeeder->contains(fn ($a): bool => $a->status === StatusAdopsiFeeder::Dipisahkan)
            : !$m->adopsiFeeder()->status(StatusAdopsiFeeder::Dipisahkan)->exists();
    }

    /**
     * Labels of the locked columns with an open PDM/PJK request, for the
     * columns that must go through PDDIKTI admin. A request the web service
     * can carry is not a reason to block: sending it is the correction.
     *
     * @return array<int, string>
     */
    private function kolomTerkunciBerubah(Mahasiswa $m, JenisPerubahanFeeder $jenis): array
    {
        if (blank($jenis === JenisPerubahanFeeder::Pdm ? $m->feeder_id : $m->feeder_keluar_id)) {
            return [];
        }

        $terbuka = $m->relationLoaded('perubahanFeeder')
            ? $m->perubahanFeeder->filter(fn (FeederChangeRequest $p): bool => $p->status->terbuka())
            : $m->perubahanFeeder()->terbuka()->get();

        return $terbuka
            ->filter(fn (FeederChangeRequest $p): bool => $p->jenis === $jenis && $p->jalur === JalurPerubahanFeeder::PddiktiAdmin)
            ->map(fn (FeederChangeRequest $p): string => $p->labelKolom())
            ->unique()
            ->values()
            ->all();
    }

    /** @param SkalaNilaiProdi $s */
    private function skalaNilaiProdi(Model $s): Collection
    {
        $isu = collect();

        if (blank($s->prodi?->kode_pddikti)) {
            $isu->push($this->error('prodi_tanpa_id_sms', 'Program studi belum memiliki kode PDDIKTI (id_sms).'));
        }

        if ((float) $s->bobot_minimum > (float) $s->bobot_maksimum) {
            $isu->push($this->error('rentang_terbalik', "Batas bawah huruf {$s->nilai_huruf} ({$s->bobot_minimum}) melebihi batas atasnya ({$s->bobot_maksimum})."));
        }

        if ((float) $s->nilai_indeks > 4.0) {
            $isu->push($this->error('indeks_di_luar_rentang', "Nilai indeks huruf {$s->nilai_huruf} melebihi 4,00."));
        }

        if ($s->berlaku_sampai !== null && $s->berlaku_mulai !== null && $s->berlaku_sampai->lt($s->berlaku_mulai)) {
            $isu->push($this->error('masa_berlaku_terbalik', "Skala huruf {$s->nilai_huruf} berakhir sebelum mulai berlaku."));
        }

        return $isu;
    }

    /** @param PeriodePerkuliahan $p */
    private function periodePerkuliahan(Model $p): Collection
    {
        $isu = collect();

        if (blank($p->prodi?->kode_pddikti)) {
            $isu->push($this->error('prodi_tanpa_id_sms', 'Program studi belum memiliki kode PDDIKTI (id_sms).'));
        }

        if ($p->tanggal_awal === null || $p->tanggal_akhir === null) {
            $isu->push($this->error('tanggal_periode_kosong', 'Tanggal awal dan akhir perkuliahan wajib diisi.'));
        } elseif ($p->tanggal_akhir->lt($p->tanggal_awal)) {
            $isu->push($this->error('tanggal_periode_terbalik', 'Perkuliahan berakhir sebelum dimulai.'));
        }

        if ((int) $p->jumlah_minggu_pertemuan <= 0) {
            $isu->push($this->error('minggu_pertemuan_nol', 'Jumlah minggu pertemuan belum ditetapkan.'));
        }

        if (!$p->metode_tatap_muka && !$p->metode_daring && !$p->metode_campuran) {
            $isu->push($this->peringatan('metode_kosong', 'Tidak satu pun metode kuliah ditandai; PDDIKTI menerimanya, tetapi laporan prodi membacanya sebagai tidak ada perkuliahan.'));
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

        $jenis = $m->jenis_daftar ?? JenisDaftar::Baru;

        if (FeederMapping::toFeeder('jenis_daftar', $jenis->value) === null) {
            $isu->push($this->error(
                'jenis_daftar_belum_dipetakan',
                "Jenis pendaftaran \"{$jenis->label()}\" belum dipetakan ke kode PDDIKTI. Tarik referensi (GetJenisPendaftaran) lalu petakan di feeder_mappings, grup jenis_daftar.",
            ));
        }

        // A transfer without its origin is refused by Feeder, and a transfer
        // whose recognised credits are short is refused again at the
        // diploma-number portal. Both are visible here first.
        if ($jenis->butuhAsal()) {
            if (blank($m->sks_diakui)) {
                $isu->push($this->error('sks_diakui_kosong', "Mahasiswa {$jenis->label()} wajib membawa jumlah SKS yang diakui."));
            }

            if (blank($m->pt_asal) || blank($m->prodi_asal)) {
                $isu->push($this->error('asal_kosong', "Mahasiswa {$jenis->label()} wajib membawa perguruan tinggi dan program studi asal."));
            }
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

        if ($s->status->kodeLokal() && FeederMapping::toFeeder('status_mahasiswa', $s->status->value) === null) {
            $isu->push($this->error(
                'status_belum_dipetakan',
                __('ukom.feeder.status_belum_dipetakan', ['status' => $s->status->label()]),
            ));
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

        if (blank($k->mataKuliah?->feeder_id)) {
            $isu->push($this->peringatan('matkul_belum_sinkron', 'Mata kuliah belum terkirim ke Feeder; kode lokal dikirim sebagai id_matkul dan akan ditolak bila Feeder tidak mengenalnya.'));
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

            // Whether a flagged class is collaborative in substance is decided
            // here, not by the flag. A flagged class whose plan is all exams
            // is reported. The rule key keeps its old name: it is an
            // identifier stored on issues, not a label (ADR-036 §1).
            if ($kelas->kelasKolaboratif()) {
                $kolaboratif = (int) $semua
                    ->filter(fn (KomponenNilai $c): bool => $c->jenis_evaluasi?->menghitungKolaboratif() ?? false)
                    ->sum('bobot');

                if ($kolaboratif < JenisEvaluasi::AMBANG_KOLABORATIF_PERSEN) {
                    $isu->push($this->peringatan(
                        'iku7_bobot_kurang',
                        __('iku.validator_bobot_kurang', [
                            'bobot' => $kolaboratif,
                            'ambang' => JenisEvaluasi::AMBANG_KOLABORATIF_PERSEN,
                        ]),
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

        $terkunci = $this->kolomTerkunciBerubah($m, JenisPerubahanFeeder::Pjk);

        if ($terkunci !== []) {
            $isu->push($this->error(
                'kolom_terkunci_berubah',
                'Kolom keluar yang dikunci PDDIKTI berubah sejak tersinkron: '.implode(', ', $terkunci)
                .'. Catatan Lulus/DO tidak dikirim ulang; ajukan PJK lewat PDDIKTI admin dan tandai selesai pada panel Perubahan Data.',
            ));
        }

        // Neo Feeder 3.1 refuses to close a registration that has no
        // enrolment record at all (ADR-036 §3). Counted locally: a term not
        // yet pushed may still go in the same run, a term that does not exist
        // here never will.
        $akm = $m->relationLoaded('statusPerSemester')
            ? $m->statusPerSemester->count()
            : $m->statusPerSemester()->count();

        if ((int) $akm === 0) {
            $isu->push($this->error('akm_kosong', __('feeder.validasi.akm_kosong')));
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
                $isu->push($this->peringatan('nomor_sk_kosong', 'Nomor SK yudisium kosong; PDDIKTI menerimanya, tetapi penerbitan nomor ijazah PISN meminta SK yudisium sebagai bukti kelulusan.'));
            }

            // A health programme's graduate is not Lulus until the competency
            // examination is passed. A Lulus status without that result was set
            // by hand, or the programme was flagged after the fact; either
            // way PDDIKTI must not be told the degree is complete.
            if ($m->prodi?->wajib_ukom && $m->ujiKompetensiLulus === null) {
                $isu->push($this->error('ukom_belum_lulus', __('ukom.feeder.lulus_tanpa_ukom')));
            }

            return $isu;
        }

        if ($m->evaluasiStudi->first() === null) {
            $isu->push($this->error('keputusan_tidak_ditemukan', 'Tidak ada keputusan evaluasi studi yang mengakhiri registrasi mahasiswa ini.'));
        }

        return $isu;
    }

    /** @param AktivitasMahasiswa $a */
    private function aktivitasMbkm(Model $a): Collection
    {
        $isu = collect();

        if (blank($a->mahasiswa?->feeder_registrasi_id)) {
            $isu->push($this->error('registrasi_belum_sinkron', 'Riwayat pendidikan mahasiswa belum terkirim ke Feeder.'));
        }

        if (blank($a->mahasiswa?->prodi?->kode_pddikti)) {
            $isu->push($this->error('prodi_tanpa_id_sms', 'Program studi belum memiliki kode PDDIKTI (id_sms).'));
        }

        if (FeederMapping::toFeeder('jenis_aktivitas', $a->jenis->value) === null) {
            $isu->push($this->error(
                'jenis_aktivitas_belum_dipetakan',
                "Jenis aktivitas \"{$a->jenis->label()}\" belum dipetakan ke kode PDDIKTI. Tarik referensi (GetJenisAktivitasMahasiswa) lalu petakan di feeder_mappings, grup jenis_aktivitas.",
            ));
        }

        if (blank($a->judul)) {
            $isu->push($this->error('judul_kosong', 'Judul aktivitas kosong.'));
        }

        return $isu;
    }

    /** @param TugasAkhir $t */
    private function tugasAkhir(Model $t): Collection
    {
        $isu = collect();

        if (blank($t->mahasiswa?->feeder_registrasi_id)) {
            $isu->push($this->error('registrasi_belum_sinkron', 'Riwayat pendidikan mahasiswa belum terkirim ke Feeder.'));
        }

        if (blank($t->mahasiswa?->prodi?->kode_pddikti)) {
            $isu->push($this->error('prodi_tanpa_id_sms', 'Program studi belum memiliki kode PDDIKTI (id_sms).'));
        }

        if (FeederMapping::toFeeder('jenis_aktivitas', TugasAkhirMapper::JENIS) === null) {
            $isu->push($this->error(
                'jenis_aktivitas_belum_dipetakan',
                'Jenis aktivitas "tugas_akhir" belum dipetakan ke kode PDDIKTI (feeder_mappings, grup jenis_aktivitas).',
            ));
        }

        if ($t->pembimbing()->count() === 0) {
            $isu->push($this->peringatan('tanpa_pembimbing', 'Tugas akhir belum memiliki pembimbing; aktivitas terkirim tanpa catatan bimbingan.'));
        }

        return $isu;
    }

    /** The student as member of an activity already known to Feeder. */
    private function anggota(?string $idAktivitas, ?Mahasiswa $m): Collection
    {
        $isu = collect();

        if (blank($idAktivitas)) {
            $isu->push($this->error('aktivitas_belum_sinkron', 'Aktivitas belum terkirim ke Feeder.'));
        }

        if (blank($m?->feeder_registrasi_id)) {
            $isu->push($this->error('registrasi_belum_sinkron', 'Riwayat pendidikan mahasiswa belum terkirim ke Feeder.'));
        }

        return $isu;
    }

    /** A lecturer supervising or examining an activity already known to Feeder. */
    private function pembimbing(?string $idAktivitas, ?Dosen $dosen, string $kategori): Collection
    {
        $isu = collect();

        if (blank($idAktivitas)) {
            $isu->push($this->error('aktivitas_belum_sinkron', 'Aktivitas belum terkirim ke Feeder.'));
        }

        if (blank($dosen?->feeder_id)) {
            $isu->push($this->error(
                'dosen_belum_dipetakan',
                "Dosen {$dosen?->nama} belum dipetakan ke id_dosen PDDIKTI. Tarik referensi (GetListDosen) setelah dosen terdaftar di PDDIKTI admin.",
            ));
        }

        if (FeederMapping::toFeeder('kategori_kegiatan', $kategori) === null) {
            $isu->push($this->error(
                'kategori_kegiatan_belum_dipetakan',
                "Kategori kegiatan \"{$kategori}\" belum dipetakan ke kode PDDIKTI. Tarik referensi (GetKategoriKegiatan) lalu petakan di feeder_mappings, grup kategori_kegiatan.",
            ));
        }

        return $isu;
    }

    /** @param Kurikulum $k */
    private function kurikulum(Model $k): Collection
    {
        $isu = collect();

        if (blank($k->prodi?->kode_pddikti)) {
            $isu->push($this->error('prodi_tanpa_id_sms', 'Program studi belum memiliki kode PDDIKTI (id_sms).'));
        }

        if ((int) $k->sks_lulus <= 0) {
            $isu->push($this->error('sks_lulus_nol', 'Jumlah SKS lulus kurikulum belum ditetapkan.'));
        }

        return $isu;
    }

    /** @param MataKuliah $mk */
    private function mataKuliah(Model $mk): Collection
    {
        $isu = collect();

        if (blank($mk->prodi?->kode_pddikti)) {
            $isu->push($this->error('prodi_tanpa_id_sms', 'Program studi belum memiliki kode PDDIKTI (id_sms).'));
        }

        if ((int) $mk->sks <= 0) {
            $isu->push($this->error('sks_nol', 'SKS mata kuliah belum ditetapkan.'));
        }

        $jenis = $mk->kurikulum->first()?->pivot?->jenis ?? 'wajib';

        if (FeederMapping::toFeeder('jenis_mata_kuliah', $jenis) === null) {
            $isu->push($this->error('jenis_matkul_belum_dipetakan', "Jenis mata kuliah \"{$jenis}\" belum dipetakan ke kode PDDIKTI (feeder_mappings, grup jenis_mata_kuliah)."));
        }

        return $isu;
    }

    /** @param KurikulumMataKuliah $km */
    private function matkulKurikulum(Model $km): Collection
    {
        $isu = collect();

        if (blank($km->kurikulum?->feeder_id)) {
            $isu->push($this->error('kurikulum_belum_sinkron', 'Kurikulum belum terkirim ke Feeder.'));
        }

        if (blank($km->mataKuliah?->feeder_id)) {
            $isu->push($this->error('matkul_belum_sinkron', 'Mata kuliah belum terkirim ke Feeder.'));
        }

        return $isu;
    }

    /** @param PoinMahasiswa $p */
    private function prestasi(Model $p): Collection
    {
        $isu = collect();

        if (blank($p->mahasiswa?->feeder_id)) {
            $isu->push($this->error('biodata_belum_sinkron', 'Biodata mahasiswa belum terkirim ke Feeder.'));
        }

        if (FeederMapping::toFeeder('jenis_prestasi', (string) $p->kategori?->kode) === null) {
            $isu->push($this->error('jenis_prestasi_belum_dipetakan', "Kategori prestasi \"{$p->kategori?->nama}\" belum dipetakan ke jenis prestasi PDDIKTI (feeder_mappings, grup jenis_prestasi; referensi GetJenisPrestasi)."));
        }

        if (FeederMapping::toFeeder('tingkat_prestasi', (string) $p->kategori?->tingkat) === null) {
            $isu->push($this->error('tingkat_prestasi_belum_dipetakan', "Tingkat \"{$p->kategori?->tingkat}\" belum dipetakan ke tingkat prestasi PDDIKTI (grup tingkat_prestasi; referensi GetTingkatPrestasi)."));
        }

        return $isu;
    }

    /** @param KonversiKredit $k */
    private function transferKredit(Model $k): Collection
    {
        $isu = collect();

        if (blank($k->mahasiswa?->feeder_registrasi_id)) {
            $isu->push($this->error('registrasi_belum_sinkron', 'Riwayat pendidikan mahasiswa belum terkirim ke Feeder.'));
        }

        if (blank($k->mataKuliah?->feeder_id)) {
            $isu->push($this->error('matkul_belum_sinkron', 'Mata kuliah tujuan belum terkirim ke Feeder.'));
        }

        if (blank($k->nilai_huruf)) {
            $isu->push($this->error('huruf_kosong', 'Nilai huruf yang diakui belum ditetapkan.'));
        }

        return $isu;
    }

    /** @param KrsDetail $d */
    private function konversiMbkm(Model $d): Collection
    {
        $isu = collect();

        if (blank($d->aktivitas?->feeder_anggota_id)) {
            $isu->push($this->error('anggota_belum_sinkron', 'Keanggotaan aktivitas MBKM belum terkirim ke Feeder (entitas anggota_mbkm).'));
        }

        if (blank($d->kelasKuliah?->mataKuliah?->feeder_id)) {
            $isu->push($this->error('matkul_belum_sinkron', 'Mata kuliah belum terkirim ke Feeder.'));
        }

        if ($d->nilai?->nilai_huruf === null) {
            $isu->push($this->error('huruf_kosong', 'Nilai huruf belum ditetapkan.'));
        }

        return $isu;
    }

    /**
     * A flagship claim rests on an activity and a member PDDIKTI already
     * holds, and on a programme PDDIKTI still lists. A code that vanished
     * from the latest reference pull is a programme the ministry retired or
     * renumbered; claiming it would be refused, so it is caught here.
     *
     * @param AktivitasMahasiswa $a
     */
    private function flagshipMbkm(Model $a): Collection
    {
        $isu = collect();

        if (blank($a->feeder_id)) {
            $isu->push($this->error('aktivitas_belum_sinkron', __('feeder.flagship.validasi.aktivitas_belum_sinkron')));
        }

        if (blank($a->feeder_anggota_id)) {
            $isu->push($this->error('anggota_belum_sinkron', __('feeder.flagship.validasi.anggota_belum_sinkron')));
        }

        if (!FeederRef::query()->type('program_flagship')->where('code', $a->flagship_kode)->exists()) {
            $isu->push($this->error('program_flagship_tidak_dikenal', __('feeder.flagship.validasi.program_tidak_dikenal', [
                'kode' => $a->flagship_kode,
                'act' => config('feeder.references.program_flagship'),
            ])));
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
