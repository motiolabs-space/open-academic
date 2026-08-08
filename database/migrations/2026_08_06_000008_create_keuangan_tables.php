<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fee matrix. A row matches an invoice line when every non-null
        // dimension it declares matches the student; the most specific
        // matching row wins.
        Schema::create('tarif', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('prodi_id')->nullable()->constrained('prodi')->cascadeOnDelete();
            $table->year('angkatan')->nullable();
            $table->string('jalur_masuk', 48)->nullable();
            $table->string('golongan_ukt', 16)->nullable(); // I .. VIII

            $table->string('komponen', 32); // ukt|spp|praktikum|registrasi|wisuda|lainnya
            $table->string('nama');

            // Rupiah are stored as integers — no float ever touches money.
            $table->unsignedBigInteger('nominal');

            $table->char('term_berlaku_dari', 5)->nullable();
            $table->char('term_berlaku_sampai', 5)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['prodi_id', 'angkatan', 'komponen']);
        });

        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('nomor', 32)->unique();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->string('keterangan');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('terbayar')->default(0);

            $table->string('status', 16)->default('belum_bayar'); // InvoiceStatus
            $table->date('jatuh_tempo');

            // Grace period granted by the finance office; while it is in the
            // future the KRS lock is lifted despite an unpaid balance.
            $table->date('dispensasi_sampai')->nullable();
            $table->string('dispensasi_catatan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['mahasiswa_id', 'tahun_akademik_id']);
            $table->index(['tahun_akademik_id', 'status']);
        });

        Schema::create('tagihan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('tarif_id')->nullable()->constrained('tarif')->nullOnDelete();

            $table->string('nama');
            $table->unsignedBigInteger('nominal');

            $table->timestamps();
        });

        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tagihan_id')->constrained('tagihan')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            $table->string('nomor_transaksi', 64)->unique();
            $table->string('gateway', 32)->default('midtrans');
            $table->string('channel', 32)->nullable(); // bca_va|qris|gopay|tunai

            $table->unsignedBigInteger('nominal');
            $table->string('status', 16)->default('pending'); // PaymentStatus

            $table->string('va_number', 64)->nullable();
            $table->text('qr_url')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Raw gateway callback, kept verbatim for reconciliation disputes.
            $table->json('raw_response')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tagihan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('tagihan_item');
        Schema::dropIfExists('tagihan');
        Schema::dropIfExists('tarif');
    }
};
