<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rencana kinerja — sasaran per unit, bertingkat mengikuti pohon organisasi.
 *
 * Bukan dasbor IKU dan bukan SPMI; keduanya milik Open Campus. Yang ada di sini
 * hanya lapisan perencanaan di atas struktur yang aplikasi ini memang miliki.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_kerja', function (Blueprint $table) {
            /*
             * Unit akademik yang *adalah* sebuah program studi menunjuk ke sana.
             *
             * Tanpa ini indikator yang dihitung tidak dapat dipersempit: data
             * akademik dikelompokkan per prodi, sedangkan pohon unit bersifat
             * administratif. Menyalin nama prodi ke dalam pohon akan membuat dua
             * sumber untuk satu hal; menunjuknya tidak.
             *
             * Null untuk unit yang bukan prodi — sebagian besar pohon.
             */
            $table->foreignId('prodi_id')->nullable()->after('jenis')
                ->constrained('prodi')->nullOnDelete();
        });

        Schema::create('periode_kinerja', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('nama');
            $table->unsignedSmallInteger('tahun');

            // Null berarti periode tahunan; terisi berarti terikat satu semester.
            $table->foreignId('tahun_akademik_id')->nullable()
                ->constrained('tahun_akademik')->nullOnDelete();

            $table->date('mulai');
            $table->date('selesai');

            // StatusPeriodeKinerja: draf | berjalan | dikunci
            $table->string('status', 16)->default('draf');

            $table->timestamp('dikunci_at')->nullable();
            $table->foreignId('dikunci_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();

            $table->index(['tahun', 'status']);
        });

        Schema::create('sasaran_kinerja', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('periode_kinerja_id')->constrained('periode_kinerja')->cascadeOnDelete();

            /*
             * Pemiliknya unit, bukan orang.
             *
             * Dekan berganti tiap empat tahun. Bila sasaran dimiliki orang,
             * sasaran fakultas ikut pindah ke mantan dekan dan penggantinya
             * mulai dari nol. Penanggung jawab diturunkan dari kepala unit saat
             * itu — dan riwayatnya tetap terbaca lewat unit_kerja.
             */
            $table->foreignId('unit_kerja_id')->constrained('unit_kerja')->restrictOnDelete();

            /*
             * Cascade. Null berarti sasaran tingkat teratas.
             *
             * Lingkaran ditolak saat ditulis oleh KinerjaService, bukan di sini:
             * basis data tidak dapat menyatakan "tidak boleh jadi leluhur diri
             * sendiri" secara portabel, dan sebuah cincin membuat setiap
             * penelusuran berjalan selamanya.
             */
            $table->foreignId('parent_id')->nullable()->constrained('sasaran_kinerja')->nullOnDelete();

            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->unsignedTinyInteger('urutan')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['periode_kinerja_id', 'unit_kerja_id']);
        });

        Schema::create('ukuran_kinerja', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('sasaran_kinerja_id')->constrained('sasaran_kinerja')->cascadeOnDelete();

            $table->string('nama');
            $table->string('satuan', 24)->nullable();

            /*
             * SumberRealisasi: dihitung | dilaporkan | eksternal
             *
             * Satu kolom, dan ia yang membedakan modul ini berguna atau sekadar
             * formulir. Ukuran yang realisasinya dihitung tidak dapat dipoles
             * menjelang laporan — tidak karena dilarang izin, melainkan karena
             * nilainya tidak pernah datang dari formulir.
             */
            $table->string('sumber_realisasi', 16)->default('dilaporkan');

            /*
             * Kunci indikator pada config('kinerja.indikator'), wajib untuk
             * sumber `dihitung`.
             *
             * Divalidasi saat ditulis: ukuran `dihitung` dengan kunci yang tidak
             * terdaftar adalah target yang tidak pernah dapat terealisasi, dan
             * tidak ada yang menyadarinya sampai tinjauan.
             */
            $table->string('indikator_kunci', 64)->nullable();

            /*
             * Target dan arah. Disimpan sebagai desimal karena sebagian ukuran
             * adalah IPK dan persentase, bukan cacahan.
             */
            $table->decimal('target', 12, 2);

            // true: makin besar makin baik. false: makin kecil makin baik —
            // angka putus studi, keterlambatan kelulusan.
            $table->boolean('semakin_besar_semakin_baik')->default(true);

            /*
             * Salinan beku, diisi saat periode dikunci.
             *
             * Target dan realisasi pada saat penguncian disalin ke sini. Aturan
             * atau data yang berubah tahun depan tidak boleh menulis ulang
             * capaian tahun ini — pelajaran yang sama dengan BKD, surat terbit,
             * dan evaluasi studi.
             */
            $table->decimal('target_beku', 12, 2)->nullable();
            $table->decimal('realisasi_beku', 12, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sasaran_kinerja_id', 'sumber_realisasi']);
        });

        Schema::create('capaian_kinerja', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('ukuran_kinerja_id')->constrained('ukuran_kinerja')->cascadeOnDelete();

            $table->date('tanggal');
            $table->decimal('nilai', 12, 2);

            // Menyalin sumbernya: sebuah baris harus dapat dibaca ulang tanpa
            // menebak apakah angkanya dihitung atau diketik saat itu.
            $table->string('sumber', 16);

            $table->string('catatan', 500)->nullable();
            $table->foreignId('dicatat_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();

            $table->index(['ukuran_kinerja_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capaian_kinerja');
        Schema::dropIfExists('ukuran_kinerja');
        Schema::dropIfExists('sasaran_kinerja');
        Schema::dropIfExists('periode_kinerja');

        Schema::table('unit_kerja', function (Blueprint $table) {
            $table->dropForeign(['prodi_id']);
            $table->dropColumn('prodi_id');
        });
    }
};
