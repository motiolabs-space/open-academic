<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KKN and PKL as managed field programmes (#37).
 *
 * Every participant is an `aktivitas_mahasiswa` row — the same record an MBKM
 * internship is — so a KKN reaches PDDIKTI through the activity, member,
 * supervisor and credit-conversion chain that already exists (#22, #23)
 * rather than through a parallel one. What the activity row has no place for
 * gets a table of its own, each hanging off the activity or off the programme
 * the activities belong to:
 *
 *  - `kegiatan_lapangan`          the programme run (a KKN period, a PKL batch)
 *  - `kegiatan_lapangan_lokasi`   the villages or partner institutions
 *  - `kegiatan_lapangan_kelompok` the groups, their field and partner supervisors
 *  - `kegiatan_lapangan_logbook`  the student's periodic log and reports
 *
 * The activity row carries the link to its programme and group, plus the two
 * marks the field supervisor and the partner give. The final mark becomes a
 * course grade only through a KRS line that names the activity — the
 * conversion path PDDIKTI already receives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_lapangan', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->string('jenis', 48); // StudentActivityType, one of kegiatanLapangan()
            $table->string('nama');
            $table->string('nomor_sk', 100)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            $table->boolean('pendaftaran_dibuka')->default(false);
            $table->unsignedSmallInteger('min_sks')->default(0);

            // Group-forming rules, copied from config/kegiatan_lapangan.php when
            // the programme is created and editable per programme afterwards.
            $table->unsignedTinyInteger('ukuran_kelompok')->default(10);
            $table->boolean('campur_prodi')->default(true);
            $table->boolean('campur_jenis_kelamin')->default(true);

            // Share of the final mark given by the partner, in percent.
            $table->unsignedTinyInteger('bobot_mitra')->default(40);

            $table->text('keterangan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_akademik_id', 'jenis'], 'keg_lap_term_jenis_idx');
        });

        Schema::create('kegiatan_lapangan_lokasi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('kegiatan_lapangan_id')->constrained('kegiatan_lapangan')->restrictOnDelete();

            $table->string('jenis', 16); // JenisLokasiKegiatan
            $table->string('nama');
            $table->string('alamat')->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('kabupaten', 100)->nullable();
            $table->string('provinsi', 100)->nullable();

            // How many groups the location can take.
            $table->unsignedTinyInteger('kuota_kelompok')->default(1);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('kegiatan_lapangan_kelompok', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('kegiatan_lapangan_id')->constrained('kegiatan_lapangan')->restrictOnDelete();
            $table->foreignId('lokasi_id')->nullable()->constrained('kegiatan_lapangan_lokasi')->restrictOnDelete();

            $table->unsignedSmallInteger('nomor');
            $table->string('nama');

            // The field supervisor is a lecturer and reaches PDDIKTI as the
            // activity's supervisor; the partner supervisor is the person at
            // the village or institution and never does.
            $table->foreignId('dosen_pembimbing_id')->nullable()->constrained('dosen')->restrictOnDelete();
            $table->string('pembimbing_mitra_nama')->nullable();
            $table->string('pembimbing_mitra_jabatan')->nullable();
            $table->string('pembimbing_mitra_telepon', 32)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kegiatan_lapangan_id', 'nomor'], 'keg_lap_kelompok_nomor_unique');
        });

        Schema::table('aktivitas_mahasiswa', function (Blueprint $table) {
            $table->foreignId('kegiatan_lapangan_id')->nullable()->after('feeder_flagship_id')
                ->constrained('kegiatan_lapangan')->restrictOnDelete();
            $table->foreignId('kegiatan_lapangan_kelompok_id')->nullable()->after('kegiatan_lapangan_id')
                ->constrained('kegiatan_lapangan_kelompok')->restrictOnDelete();
            $table->boolean('is_ketua_kelompok')->default(false)->after('kegiatan_lapangan_kelompok_id');

            $table->decimal('nilai_pembimbing', 5, 2)->nullable()->after('is_ketua_kelompok');
            $table->decimal('nilai_mitra', 5, 2)->nullable()->after('nilai_pembimbing');
            $table->decimal('nilai_akhir', 5, 2)->nullable()->after('nilai_mitra');
            $table->timestamp('dinilai_at')->nullable()->after('nilai_akhir');
        });

        Schema::create('kegiatan_lapangan_logbook', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('aktivitas_mahasiswa_id')->constrained('aktivitas_mahasiswa')->restrictOnDelete();

            $table->string('jenis', 16); // JenisLogbook
            $table->date('tanggal');
            $table->string('judul');
            $table->text('uraian')->nullable();
            $table->string('berkas_path')->nullable();
            $table->string('berkas_nama')->nullable();

            $table->timestamp('diperiksa_at')->nullable();
            $table->foreignId('diperiksa_by_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();
            $table->text('catatan_pembimbing')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['aktivitas_mahasiswa_id', 'tanggal'], 'keg_lap_logbook_aktivitas_tgl_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_lapangan_logbook');

        Schema::table('aktivitas_mahasiswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kegiatan_lapangan_kelompok_id');
            $table->dropConstrainedForeignId('kegiatan_lapangan_id');
            $table->dropColumn(['is_ketua_kelompok', 'nilai_pembimbing', 'nilai_mitra', 'nilai_akhir', 'dinilai_at']);
        });

        Schema::dropIfExists('kegiatan_lapangan_kelompok');
        Schema::dropIfExists('kegiatan_lapangan_lokasi');
        Schema::dropIfExists('kegiatan_lapangan');
    }
};
