<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `dispensasi_tagihan` is every payment dispensation on an invoice: asked for
 * by the student or granted outright by the finance office, decided, and
 * possibly revoked.
 *
 * `tagihan.dispensasi_sampai` stays the one column the KRS gate and the late
 * fee read; this table is the record of who put a date there, until when,
 * and why — and of every request that was turned down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensasi_tagihan', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tagihan_id')->constrained('tagihan')->restrictOnDelete();
            $table->string('status', 16); // StatusDispensasi

            // Asked for, then decided. A grant made directly by the finance
            // office has no requester.
            $table->date('sampai');
            $table->string('alasan', 500);
            $table->foreignId('diajukan_by_mahasiswa_id')->nullable()->constrained('mahasiswa')->restrictOnDelete();

            $table->foreignId('diputus_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diputus_at')->nullable();
            $table->string('catatan_keputusan', 500)->nullable();

            $table->foreignId('dicabut_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('dicabut_at')->nullable();
            $table->string('alasan_cabut', 500)->nullable();

            $table->timestamps();

            $table->index(['tagihan_id', 'status'], 'dispensasi_tagihan_tagihan_status_index');
            $table->index(['status', 'sampai'], 'dispensasi_tagihan_status_sampai_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensasi_tagihan');
    }
};
