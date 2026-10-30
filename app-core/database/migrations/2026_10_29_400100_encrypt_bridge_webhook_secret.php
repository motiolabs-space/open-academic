<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A consumer's webhook signing secret, encrypted at rest.
 *
 * It cannot be hashed: every delivery is signed with it, so the plain value
 * has to be recoverable. What can be done is keeping it out of a database dump
 * — BridgeConsumer now casts it `encrypted`, and the rows written before that
 * are encrypted here. The column widens to text because the ciphertext of a
 * 46-character secret no longer fits in 255.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bridge_consumers', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->change();
        });

        DB::table('bridge_consumers')->whereNotNull('webhook_secret')->orderBy('id')
            ->each(fn (object $baris) => DB::table('bridge_consumers')->where('id', $baris->id)
                ->update(['webhook_secret' => Crypt::encryptString((string) $baris->webhook_secret)]));
    }

    public function down(): void
    {
        DB::table('bridge_consumers')->whereNotNull('webhook_secret')->orderBy('id')
            ->each(fn (object $baris) => DB::table('bridge_consumers')->where('id', $baris->id)
                ->update(['webhook_secret' => Crypt::decryptString((string) $baris->webhook_secret)]));

        Schema::table('bridge_consumers', function (Blueprint $table) {
            $table->string('webhook_secret')->nullable()->change();
        });
    }
};
