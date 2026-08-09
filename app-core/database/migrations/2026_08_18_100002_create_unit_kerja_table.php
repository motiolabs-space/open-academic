<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Unit kerja — the org chart, replacing a free-text column.
 *
 * `staff.unit` held strings somebody typed: "BAAK", "Baak", "Bag. Akademik".
 * Nothing could roll up, nothing could be delegated to, and a report by unit
 * counted three units where there was one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('kode', 24)->unique();
            $table->string('nama');

            // JenisUnitKerja: struktural | akademik | penunjang
            $table->string('jenis', 24)->default('struktural');

            /*
             * The tree. Null is a top-level unit — typically the rectorate.
             *
             * Cycles are refused at write time by UnitKerjaService, not here: a
             * database cannot express "no ancestor may be myself" portably, and
             * a ring makes every traversal in the application infinite.
             */
            $table->foreignId('parent_id')->nullable()->constrained('unit_kerja')->nullOnDelete();

            /*
             * Whoever heads the unit — either a member of staff or a lecturer,
             * never both.
             *
             * Two nullable columns rather than one polymorphic pair, because a
             * dean is a lecturer and a bureau head is administrative staff, and
             * forcing them into one table would mean inventing a fake row in
             * the other for half the org chart. The "at most one" rule lives in
             * the service, where it can explain itself.
             */
            $table->foreignId('kepala_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('kepala_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();

            $table->string('keterangan', 500)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'is_active']);
        });

        Schema::table('staff', function (Blueprint $table) {
            /*
             * The text column stays.
             *
             * It is what the old rows actually said, and dropping it would
             * throw away the only evidence of how somebody was filed before the
             * backfill guessed. Reads go through unit_kerja_id from here.
             */
            $table->foreignId('unit_kerja_id')->nullable()->after('unit')
                ->constrained('unit_kerja')->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Turns the distinct strings already in `staff.unit` into real units.
     *
     * Deliberately flat and deliberately dumb: it does not try to guess that
     * "Bag. Akademik" and "BAAK" are the same office. A wrong guess here is
     * invisible and permanent, whereas a duplicate row is visible on the screen
     * and takes one click to merge.
     */
    private function backfill(): void
    {
        $nama = DB::table('staff')
            ->whereNotNull('unit')
            ->where('unit', '!=', '')
            ->distinct()
            ->pluck('unit');

        foreach ($nama as $satu) {
            $kode = Str::of($satu)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(20, '');

            $id = DB::table('unit_kerja')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'kode' => (string) $kode !== '' ? (string) $kode : 'UNIT-'.Str::random(6),
                'nama' => $satu,
                'jenis' => 'struktural',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('staff')->where('unit', $satu)->update(['unit_kerja_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['unit_kerja_id']);
            $table->dropColumn('unit_kerja_id');
        });

        Schema::dropIfExists('unit_kerja');
    }
};
