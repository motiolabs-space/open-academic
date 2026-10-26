<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The proposal document as a stage before a final-project title is approved (#38).
 *
 * Switched on per programme (`prodi.wajib_proposal_ta`, off by default). A
 * project records whether the stage applied to it at the moment it was
 * proposed (`tugas_akhir.wajib_proposal`), so turning the switch on never
 * reaches back into projects already under way: they were proposed under the
 * old rule, and stay valid under it.
 *
 * Versions and reviews are append-only. A revision is a new version row, a
 * decision is a new review row; neither is ever overwritten, so the trail of
 * what the student submitted and what they were told survives the outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->boolean('wajib_proposal_ta')->default(false)->after('wajib_ukom');
        });

        Schema::table('tugas_akhir', function (Blueprint $table) {
            $table->boolean('wajib_proposal')->default(false)->after('status');
        });

        Schema::create('tugas_akhir_proposal', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tugas_akhir_id')->constrained('tugas_akhir')->restrictOnDelete();
            $table->unsignedSmallInteger('versi');

            // The title as it stood in this version; the project's own title
            // follows the latest one.
            $table->string('judul', 500);
            $table->text('ringkasan')->nullable();
            $table->string('berkas_path');
            $table->string('berkas_nama')->nullable();

            $table->foreignId('calon_pembimbing_dosen_id')->nullable()->constrained('dosen')->restrictOnDelete();

            $table->string('status', 16)->index(); // StatusProposal
            $table->timestamp('diputus_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tugas_akhir_id', 'versi'], 'ta_proposal_versi_unique');
        });

        Schema::create('tugas_akhir_proposal_telaah', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tugas_akhir_proposal_id')->constrained('tugas_akhir_proposal')->restrictOnDelete();

            // Exactly one of the two: a prospective supervisor or the
            // programme's coordinator acting as staff.
            $table->foreignId('dosen_id')->nullable()->constrained('dosen')->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->restrictOnDelete();

            $table->string('keputusan', 16); // KeputusanTelaah
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_akhir_proposal_telaah');
        Schema::dropIfExists('tugas_akhir_proposal');

        Schema::table('tugas_akhir', function (Blueprint $table) {
            $table->dropColumn('wajib_proposal');
        });

        Schema::table('prodi', function (Blueprint $table) {
            $table->dropColumn('wajib_proposal_ta');
        });
    }
};
