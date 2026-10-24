<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The external IdP's stable subject identifier, once an account is linked.
 *
 * Email is used exactly once, to link. After that the subject is what names
 * the person: campuses rename mailboxes and reissue addresses to new hires,
 * and an account that kept following the address would follow it to the
 * wrong person.
 *
 * Staff and lecturers only. Students are deliberately not candidates for
 * federated sign-in (see config/idp.php), so their table gets no column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('idp_subject')->nullable()->unique()->after('remember_token');
        });

        Schema::table('dosen', function (Blueprint $table) {
            $table->string('idp_subject')->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['idp_subject']);
            $table->dropColumn('idp_subject');
        });

        Schema::table('dosen', function (Blueprint $table) {
            $table->dropUnique(['idp_subject']);
            $table->dropColumn('idp_subject');
        });
    }
};
