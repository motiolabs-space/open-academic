<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who typed a ticket in, when it was not the applicant.
 *
 * A student at the front desk, a lecturer on the phone: the ticket is theirs —
 * they are the applicant, they are notified, they see it in their portal —
 * but the officer who wrote it down is named here, so "I never filed that" has
 * an answer. Null for every ticket filed by its own applicant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_tiket', function (Blueprint $table) {
            $table->foreignId('dicatat_oleh_staff_id')->nullable()->after('pemohon_id')
                ->constrained('staff', indexName: 'helpdesk_tiket_pencatat_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tiket', function (Blueprint $table) {
            $table->dropForeign('helpdesk_tiket_pencatat_fk');
            $table->dropColumn('dicatat_oleh_staff_id');
        });
    }
};
