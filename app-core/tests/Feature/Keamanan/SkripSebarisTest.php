<?php

declare(strict_types=1);

use App\Enums\ApplicantStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\File;

/**
 * Record names never reach a JavaScript context through Blade's `{{ }}`.
 *
 * HTML escaping protects text and attribute values, not a script: inside an
 * `onsubmit="…"` the browser decodes `&#039;` back to a quote before running
 * it. An applicant who registered on the public PMB portal as `x');…('` ran
 * code in the session of the staff member who clicked "Daftarkan".
 */
it('tidak menaruh nama pendaftar di dalam penangan skrip sebaris', function () {
    $this->seed(RolePermissionSeeder::class);

    $term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $prodi = Prodi::factory()->create();
    $gelombang = PmbGelombang::create([
        'tahun_akademik_id' => $term->id,
        'kode' => 'PMB-1',
        'nama' => 'Gelombang I',
        'jalur' => 'reguler',
        'tanggal_mulai' => now()->subMonth(),
        'tanggal_selesai' => now()->addMonth(),
        'biaya_pendaftaran' => 0,
        'is_active' => true,
    ]);

    $muatan = "x');document.title='dibajak';('";
    PmbPendaftar::create([
        'pmb_gelombang_id' => $gelombang->id,
        'nomor_pendaftaran' => 'REG-000001',
        'nama' => $muatan,
        'email' => 'calon@example.test',
        'nik' => '3201234567890001',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2008-05-14',
        'jenis_kelamin' => 'L',
        'prodi_pilihan_1_id' => $prodi->id,
        'prodi_diterima_id' => $prodi->id,
        'status' => ApplicantStatus::Lulus,
    ]);

    $baak = Staff::factory()->create();
    $baak->assignRole('baak');

    $this->actingAs($baak, 'staff')->get('/admin/pmb')
        ->assertOk()
        ->assertDontSee('onsubmit=', false)
        // Plain text in a data attribute: escaped once, never evaluated.
        ->assertSee('data-confirm="Daftarkan '.e($muatan).' sebagai mahasiswa?', false);
});

it('tidak menyisakan skrip atau penangan on* sebaris di view mana pun', function () {
    // The CSP refuses 'unsafe-inline' for scripts on the strength of this
    // scan. A new inline handler would silently stop working in the browser;
    // this makes it fail here instead.
    $pelanggaran = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($berkas) => str_ends_with($berkas->getFilename(), '.blade.php'))
        ->flatMap(function ($berkas) {
            $isi = $berkas->getContents();
            $temuan = [];

            if (preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $isi, $m)) {
                $temuan[] = $berkas->getRelativePathname().': '.implode(', ', array_unique($m[0]));
            }
            if (preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $isi)) {
                $temuan[] = $berkas->getRelativePathname().': <script> sebaris';
            }

            return $temuan;
        })
        ->values()
        ->all();

    expect($pelanggaran)->toBe([]);
});

it('mengirim CSP tanpa unsafe-inline untuk skrip', function () {
    $csp = $this->get(route('login'))->headers->get('Content-Security-Policy');

    $scriptSrc = collect(explode(';', $csp))
        ->map(fn ($d) => trim($d))
        ->first(fn ($d) => str_starts_with($d, 'script-src'));

    expect($scriptSrc)->not->toContain("'unsafe-inline'")
        ->and($csp)->toContain("object-src 'none'");
});

it('mengirim HSTS hanya pada permintaan HTTPS', function () {
    $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');

    $this->get(str_replace('http://', 'https://', route('login')))
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});
