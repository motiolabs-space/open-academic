<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-factor authentication for staff accounts.
 *
 * Staff hold `nilai.manage`, `keuangan.manage` and `wisuda.manage`. A leaked
 * password there does not expose records — it *changes* them, and an issued
 * graduation cannot be recalled by resetting a password afterwards.
 *
 * Staff only, deliberately. The population is dozens rather than thousands, so
 * the friction lands on the few people holding dangerous authority instead of
 * on every student during KRS week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            /*
             * Encrypted at rest, both of them.
             *
             * The secret is a password equivalent: anyone holding it can mint
             * valid codes forever. A database dump — a backup on a laptop, a
             * replica someone forgot about — would otherwise hand over the
             * second factor along with the first.
             */
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery')->nullable()->after('two_factor_secret');

            /*
             * Null until a code has actually been typed back correctly.
             *
             * A secret that was generated but never confirmed must not lock
             * anybody out: the usual way to break your own account is to scan
             * the QR, lose the phone, and never have proven the pairing worked.
             */
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery');

            /*
             * The step number of the last code accepted.
             *
             * A TOTP code stays valid for its whole 30-second window, so
             * without this a code read over someone's shoulder — or lifted
             * from a phishing page — can be replayed while it is still warm.
             */
            $table->unsignedBigInteger('two_factor_langkah_terakhir')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery',
                'two_factor_confirmed_at',
                'two_factor_langkah_terakhir',
            ]);
        });
    }
};
