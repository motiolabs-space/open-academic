<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online admission: applicants register themselves (roadmap #32, ADR-024).
 *
 * The account is its own table rather than a password column on
 * `pmb_pendaftar`. One person may apply in more than one wave over a year, an
 * applicant row created by the admissions desk has no account at all, and —
 * the reason that decides it — an account table is what lets the `pendaftar`
 * guard exist without ever resolving to a row the student portal knows about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pmb_akun', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('nama');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            $table->timestamps();
        });

        // Separate from password_reset_tokens, which is keyed by email and
        // shared by the three campus brokers. An applicant may well type an
        // address that also belongs to a student or a staff member; one table
        // would let a reset requested on one side overwrite the other's token.
        Schema::create('pmb_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('pmb_gelombang', function (Blueprint $table) {
            // Off by default: a wave the admissions desk opened before this
            // feature existed must not become a public registration form on
            // upgrade.
            $table->boolean('is_daring')->default(false)->after('is_active');

            // Keys of config('berkas.pmb') every applicant in the wave uploads.
            $table->json('dokumen_wajib')->nullable()->after('is_daring');

            $table->text('keterangan')->nullable()->after('dokumen_wajib');

            // Results are hidden from applicants until this is set; see
            // PengumumanPmbService.
            $table->timestamp('diumumkan_at')->nullable()->after('keterangan');
        });

        // Which programmes a wave offers, and on what terms.
        Schema::create('pmb_gelombang_prodi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pmb_gelombang_id')->constrained('pmb_gelombang')->restrictOnDelete();
            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();

            // Seats: the most applicants the programme accepts in this wave.
            $table->unsignedSmallInteger('daya_tampung')->nullable();

            // Overrides the wave's fee; null means "the wave's".
            $table->unsignedBigInteger('biaya_formulir')->nullable();

            // Only read when automatic acceptance is switched on.
            $table->decimal('ambang_lulus', 5, 2)->nullable();

            // Added to the wave's list, never subtracted from it.
            $table->json('dokumen_wajib')->nullable();

            $table->timestamps();

            $table->unique(['pmb_gelombang_id', 'prodi_id'], 'pmb_gelombang_prodi_unik');
        });

        Schema::create('pmb_jadwal_seleksi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('pmb_gelombang_id')->constrained('pmb_gelombang')->restrictOnDelete();

            $table->string('nama');
            $table->dateTime('mulai');
            $table->dateTime('selesai')->nullable();
            $table->string('lokasi')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['pmb_gelombang_id', 'mulai'], 'pmb_jadwal_seleksi_gelombang_mulai');
        });

        Schema::table('pmb_pendaftar', function (Blueprint $table) {
            // Null for applicants entered by the admissions desk.
            $table->foreignId('pmb_akun_id')->nullable()->after('pmb_gelombang_id')
                ->constrained('pmb_akun')->restrictOnDelete();

            // The rest of what MahasiswaMapper sends as biodata, collected now
            // so an accepted applicant is reportable on the first sync.
            $table->string('agama_kode', 8)->nullable()->after('jenis_kelamin');
            $table->string('nama_ibu')->nullable()->after('agama_kode');
            $table->string('nama_ayah')->nullable()->after('nama_ibu');
            $table->string('kelurahan', 96)->nullable()->after('alamat');
            $table->string('kecamatan', 96)->nullable()->after('kelurahan');
            $table->string('kabupaten', 96)->nullable()->after('kecamatan');
            $table->string('provinsi', 96)->nullable()->after('kabupaten');
            $table->string('kode_pos', 8)->nullable()->after('provinsi');

            // Transfer and RPL waves only; carried into the transfer intake
            // form as its starting values.
            $table->string('pt_asal')->nullable()->after('tahun_lulus_sekolah');
            $table->string('prodi_asal')->nullable()->after('pt_asal');

            // A marker for the KIP Kuliah office, not an award. Whether the
            // applicant receives KIP is decided outside this system.
            $table->boolean('is_calon_kip')->default(false)->after('prodi_asal');
            $table->string('nomor_kip', 32)->nullable()->after('is_calon_kip');

            // The fee owed, frozen when the application was created so a
            // later change to the wave's price does not reprice it.
            $table->unsignedBigInteger('biaya_formulir')->default(0)->after('nomor_kip');

            // When the applicant submitted; the biodata is locked from here.
            $table->timestamp('diajukan_at')->nullable()->after('biaya_formulir');

            $table->index(['pmb_akun_id', 'pmb_gelombang_id'], 'pmb_pendaftar_akun_gelombang');
        });

        // The form fee's own ledger. See ADR-024 for why this is not a row in
        // `tagihan`: every invariant on that table assumes a student.
        Schema::create('pmb_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('pmb_pendaftar_id')->constrained('pmb_pendaftar')->restrictOnDelete();

            $table->unsignedBigInteger('nominal');
            $table->string('channel', 32)->default('transfer');
            $table->string('referensi', 64)->nullable();
            $table->string('status', 16)->default('settlement'); // PaymentStatus

            $table->foreignId('dicatat_oleh_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            $table->timestamp('dibatalkan_at')->nullable();
            $table->string('alasan_batal')->nullable();

            $table->timestamps();

            $table->index(['pmb_pendaftar_id', 'status'], 'pmb_pembayaran_pendaftar_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pmb_pembayaran');

        Schema::table('pmb_pendaftar', function (Blueprint $table) {
            $table->dropIndex('pmb_pendaftar_akun_gelombang');
            $table->dropConstrainedForeignId('pmb_akun_id');
            $table->dropColumn([
                'agama_kode', 'nama_ibu', 'nama_ayah', 'kelurahan', 'kecamatan', 'kabupaten',
                'provinsi', 'kode_pos', 'pt_asal', 'prodi_asal', 'is_calon_kip', 'nomor_kip',
                'biaya_formulir', 'diajukan_at',
            ]);
        });

        Schema::dropIfExists('pmb_jadwal_seleksi');
        Schema::dropIfExists('pmb_gelombang_prodi');

        Schema::table('pmb_gelombang', function (Blueprint $table) {
            $table->dropColumn(['is_daring', 'dokumen_wajib', 'keterangan', 'diumumkan_at']);
        });

        Schema::dropIfExists('pmb_password_reset_tokens');
        Schema::dropIfExists('pmb_akun');
    }
};
