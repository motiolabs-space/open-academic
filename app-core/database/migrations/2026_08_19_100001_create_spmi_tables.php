<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPMI — standar mutu dan Audit Mutu Internal.
 *
 * Bentuknya **audit**, bukan OKR, dan itu perbedaan yang menentikan seluruh
 * tabel di bawah: yang menjadikan sesuatu SPMI adalah temuan yang tidak dapat
 * disunting setelah ditutup dan tindak lanjut yang diverifikasi ulang. Rencana
 * kinerja tidak punya keduanya, dan menyatukan keduanya menghapus persis itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standar_mutu', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('kode', 24)->unique();
            $table->string('nama');

            /*
             * Pernyataan standar — kalimat "siapa harus melakukan apa, kapan".
             *
             * Kolom teks tersendiri, bukan bagian dari nama: sebuah standar
             * dirujuk dengan namanya dan diaudit dengan pernyataannya, dan
             * auditor yang harus menyimpulkan pernyataan dari sebuah nama akan
             * menyimpulkannya berbeda dari auditor berikutnya.
             */
            $table->text('pernyataan');

            $table->string('kategori', 32)->nullable(); // pendidikan | penelitian | pkm | tata kelola

            // Kunci pada config('spmi.ppepp'). Keadaan, bukan tabel: PPEPP
            // adalah putaran yang dilalui satu standar berulang kali.
            $table->string('siklus', 24)->default('penetapan');

            /*
             * Apakah standar ini melampaui SN-Dikti.
             *
             * Dibedakan karena akreditasi menanyakannya secara terpisah, dan
             * kampus yang menandai semuanya "melampaui" sedang menjawab
             * pertanyaan itu dengan tidak jujur kepada dirinya sendiri.
             */
            $table->boolean('melampaui_sndikti')->default(false);

            $table->foreignId('unit_penanggung_jawab_id')->nullable()
                ->constrained('unit_kerja')->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['kategori', 'is_active']);
        });

        Schema::create('indikator_standar', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('standar_mutu_id')->constrained('standar_mutu')->cascadeOnDelete();

            $table->string('nama');
            $table->string('satuan', 24)->nullable();
            $table->decimal('target', 12, 2)->nullable();

            /*
             * Menunjuk katalog indikator rencana kinerja bila angkanya memang
             * dapat dihitung aplikasi ini.
             *
             * Null berarti indikator ini diperiksa auditor dengan mata, bukan
             * dihitung — dan itu mayoritas standar mutu. Menyediakan kolomnya
             * membuat yang dapat dihitung tidak perlu diketik ulang; tidak
             * mewajibkannya membuat sisanya tetap jujur sebagai penilaian
             * manusia.
             */
            $table->string('indikator_kunci', 64)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_mutu', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('nama');
            $table->unsignedSmallInteger('tahun');

            // Unit yang diaudit.
            $table->foreignId('unit_kerja_id')->constrained('unit_kerja')->restrictOnDelete();

            /*
             * Auditor — dosen atau staf, tidak keduanya.
             *
             * Alasan yang sama dengan kepala unit: auditor mutu di kampus lazim
             * seorang dosen, tapi bisa juga staf penjaminan mutu. Memaksakan
             * satu tabel berarti mengarang baris palsu di tabel lainnya.
             */
            $table->foreignId('auditor_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();
            $table->foreignId('auditor_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->date('tanggal_audit');

            // StatusAudit: direncanakan | berlangsung | selesai
            $table->string('status', 16)->default('direncanakan');

            $table->text('ringkasan')->nullable();

            $table->timestamp('ditutup_at')->nullable();

            $table->timestamps();

            $table->index(['tahun', 'status']);
            $table->index(['unit_kerja_id', 'tahun']);
        });

        Schema::create('temuan_audit', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('audit_mutu_id')->constrained('audit_mutu')->cascadeOnDelete();
            $table->foreignId('standar_mutu_id')->nullable()->constrained('standar_mutu')->nullOnDelete();

            // Kunci pada config('spmi.jenis_temuan').
            $table->string('jenis', 16);

            $table->text('uraian');
            $table->text('akar_masalah')->nullable();
            $table->string('bukti_path')->nullable();

            $table->date('tenggat')->nullable();

            // StatusTemuan: terbuka | ditindaklanjuti | ditutup
            $table->string('status', 20)->default('terbuka');

            /*
             * Ditutup satu arah, dan isinya tidak dapat disunting sesudahnya.
             *
             * Inilah yang menjadikan ini SPMI dan bukan daftar tugas. Temuan
             * yang dapat diubah setelah ditutup adalah temuan yang dapat
             * dihaluskan menjelang asesmen lapangan, dan seluruh gunanya justru
             * pada ketidakmungkinan itu.
             */
            $table->timestamp('ditutup_at')->nullable();
            $table->foreignId('ditutup_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();

            $table->index(['audit_mutu_id', 'status']);
            $table->index(['status', 'tenggat']);
        });

        Schema::create('tindak_lanjut_temuan', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('temuan_audit_id')->constrained('temuan_audit')->cascadeOnDelete();

            $table->text('rencana');
            $table->date('target_selesai')->nullable();

            $table->text('realisasi')->nullable();
            $table->date('tanggal_realisasi')->nullable();
            $table->string('bukti_path')->nullable();

            /*
             * Diverifikasi orang lain, bukan oleh yang mengerjakannya.
             *
             * Perbaikan yang diverifikasi sendiri oleh pelaksananya bukan
             * verifikasi; ia hanya pernyataan kedua dari orang yang sama.
             */
            $table->boolean('is_terverifikasi')->default(false);
            $table->foreignId('diverifikasi_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->string('catatan_verifikasi', 500)->nullable();

            $table->foreignId('dicatat_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();

            $table->index(['temuan_audit_id', 'is_terverifikasi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tindak_lanjut_temuan');
        Schema::dropIfExists('temuan_audit');
        Schema::dropIfExists('audit_mutu');
        Schema::dropIfExists('indikator_standar');
        Schema::dropIfExists('standar_mutu');
    }
};
