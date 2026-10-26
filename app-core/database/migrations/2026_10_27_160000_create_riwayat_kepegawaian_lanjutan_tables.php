<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of the personnel file, and a review step for all of it.
 *
 * Eight more typed histories — training, work experience, study leave, official
 * travel, structural posts, inpassing, contracts, retirement — matching the six
 * from 2026_08_18_100003. Same reason as before for a table each: a generic
 * "riwayat" with a JSON column cannot be validated, indexed, or mapped to a
 * SISTER field.
 *
 * Every one of the fourteen gains the same five columns: who filled it in is a
 * lecturer, who vouches for it is a staff member, and the two are recorded
 * separately. The six older tables are backfilled as approved — every row in
 * them was written by staff or a seeder, since no lecturer screen existed.
 */
return new class extends Migration
{
    /** Tables that existed before this migration and gain the review columns. */
    private const LAMA = [
        'keluarga_dosen' => 'kel_dosen',
        'pangkat_dosen' => 'pkt_dosen',
        'mutasi_dosen' => 'mts_dosen',
        'penghargaan_sanksi_dosen' => 'phs_dosen',
        'bahasa_dosen' => 'bhs_dosen',
        'organisasi_dosen' => 'org_dosen',
    ];

    public function up(): void
    {
        foreach (self::LAMA as $tabel => $singkat) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel, $singkat) {
                $this->kolomValidasi($table, $tabel, $singkat);
            });

            DB::table($tabel)->update(['status' => 'disetujui']);
        }

        Schema::create('diklat_dosen', function (Blueprint $table) {
            $this->identitas($table);

            $table->string('nama');
            $table->string('jenis', 24); // JenisDiklat
            $table->string('penyelenggara')->nullable();
            $table->string('tingkat', 24)->nullable(); // TingkatKegiatan
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->unsignedSmallInteger('jumlah_jam')->nullable();
            $table->string('nomor_sertifikat', 128)->nullable();

            $this->kolomValidasi($table, 'diklat_dosen', 'dkl_dosen');
            $table->index(['dosen_id', 'tanggal_mulai'], 'dkl_dosen_tanggal_idx');
        });

        Schema::create('pengalaman_kerja_dosen', function (Blueprint $table) {
            $this->identitas($table);

            $table->string('instansi');
            $table->string('jabatan');
            $table->string('bidang')->nullable();
            $table->date('tanggal_mulai');

            // Null while the lecturer still holds it — a practitioner who
            // keeps an industry post alongside teaching is the IKU 4 case.
            $table->date('tanggal_selesai')->nullable();
            $table->string('keterangan', 500)->nullable();

            $this->kolomValidasi($table, 'pengalaman_kerja_dosen', 'pgk_dosen');
            $table->index(['dosen_id', 'tanggal_mulai'], 'pgk_dosen_tanggal_idx');
        });

        Schema::create('tugas_belajar_dosen', function (Blueprint $table) {
            $this->identitas($table);

            /*
             * Tugas belajar and izin belajar are different acts, not two
             * spellings of one. Study leave releases the lecturer from teaching
             * and so from BKD; permission to study alongside work does not.
             * BkdService reads only the first.
             */
            $table->string('jenis', 24); // JenisStudiLanjut
            $table->string('jenjang', 4); // EducationLevel
            $table->string('perguruan_tinggi');
            $table->string('program_studi')->nullable();
            $table->string('negara', 64)->default('Indonesia');
            $table->string('sumber_biaya')->nullable();
            $table->string('nomor_sk', 128)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->date('tanggal_mulai');

            // Null means still on leave: an open end is how "on study leave"
            // is asked of the table, so it must not default to a guess.
            $table->date('tanggal_selesai')->nullable();

            $this->kolomValidasi($table, 'tugas_belajar_dosen', 'tbl_dosen');
            $table->index(['dosen_id', 'tanggal_mulai'], 'tbl_dosen_tanggal_idx');
        });

        Schema::create('tugas_dinas_dosen', function (Blueprint $table) {
            $this->identitas($table);

            $table->string('tujuan');
            $table->string('instansi_tujuan')->nullable();
            $table->string('keperluan', 500);
            $table->string('nomor_surat_tugas', 128)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_luar_negeri')->default(false);

            $this->kolomValidasi($table, 'tugas_dinas_dosen', 'tds_dosen');
            $table->index(['dosen_id', 'tanggal_mulai'], 'tds_dosen_tanggal_idx');
        });

        Schema::create('jabatan_struktural_dosen', function (Blueprint $table) {
            $this->identitas($table);

            $table->string('nama_jabatan');

            // The unit the post heads, when it is one of ours. A seat on an
            // external board has no row in unit_kerja and needs none.
            $table->foreignId('unit_kerja_id')->nullable()
                ->constrained('unit_kerja', indexName: 'jst_dosen_unit_fk')->nullOnDelete();

            $table->string('nomor_sk', 128)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->date('tmt');
            $table->date('tmt_selesai')->nullable();

            $this->kolomValidasi($table, 'jabatan_struktural_dosen', 'jst_dosen');
            $table->index(['dosen_id', 'tmt'], 'jst_dosen_tmt_idx');
        });

        Schema::create('inpassing_dosen', function (Blueprint $table) {
            $this->identitas($table);

            // The civil-service grade a non-civil-servant lecturer is held
            // equivalent to — same vocabulary as pangkat_dosen.
            $table->string('pangkat', 64);
            $table->string('golongan', 8);
            $table->unsignedInteger('angka_kredit_ratus')->nullable();
            $table->string('nomor_sk', 128);
            $table->date('tanggal_sk')->nullable();
            $table->date('tmt');

            $this->kolomValidasi($table, 'inpassing_dosen', 'inp_dosen');
            $table->index(['dosen_id', 'tmt'], 'inp_dosen_tmt_idx');
        });

        Schema::create('kontrak_dosen', function (Blueprint $table) {
            $this->identitas($table);

            $table->string('nomor_kontrak', 128);
            $table->string('jenis', 64)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('keterangan', 500)->nullable();

            $this->kolomValidasi($table, 'kontrak_dosen', 'ktr_dosen');
            $table->index(['dosen_id', 'tanggal_selesai'], 'ktr_dosen_selesai_idx');
        });

        Schema::create('pensiun_dosen', function (Blueprint $table) {
            $this->identitas($table);

            $table->string('jenis', 24); // JenisPensiun
            $table->date('tmt');
            $table->string('nomor_sk', 128)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->string('keterangan', 500)->nullable();

            $this->kolomValidasi($table, 'pensiun_dosen', 'pns_dosen');
            $table->index(['status', 'tmt'], 'pns_dosen_status_tmt_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            'pensiun_dosen', 'kontrak_dosen', 'inpassing_dosen', 'jabatan_struktural_dosen',
            'tugas_dinas_dosen', 'tugas_belajar_dosen', 'pengalaman_kerja_dosen', 'diklat_dosen',
        ] as $tabel) {
            Schema::dropIfExists($tabel);
        }

        foreach (self::LAMA as $tabel => $singkat) {
            Schema::table($tabel, function (Blueprint $table) use ($singkat) {
                $table->dropForeign($singkat.'_validator_fk');
                $table->dropIndex($singkat.'_status_idx');
                $table->dropColumn(['status', 'alasan_tolak', 'divalidasi_oleh', 'divalidasi_at', 'dokumen_path']);
            });
        }
    }

    private function identitas(Blueprint $table): void
    {
        $table->id();
        $table->uuid()->unique();
        $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();
        $table->timestamps();
        $table->softDeletes();
    }

    /**
     * The review columns, identical on every history table.
     *
     * Index and constraint names are given explicitly and kept short: the
     * generated `{table}_{columns}_index` passes MySQL's 64-character limit on
     * the longer table names, and SQLite would never say so.
     */
    private function kolomValidasi(Blueprint $table, string $tabel, string $singkat): void
    {
        // Defaults to awaiting review, so a row written by a path that forgot
        // to choose is never mistaken for one somebody checked.
        $table->string('status', 16)->default('diajukan');
        $table->string('alasan_tolak', 500)->nullable();
        $table->foreignId('divalidasi_oleh')->nullable()
            ->constrained('staff', indexName: $singkat.'_validator_fk')->nullOnDelete();
        $table->timestamp('divalidasi_at')->nullable();
        $table->string('dokumen_path')->nullable();

        $table->index(['dosen_id', 'status'], $singkat.'_status_idx');
    }
};
