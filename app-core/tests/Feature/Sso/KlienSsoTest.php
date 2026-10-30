<?php

declare(strict_types=1);

use App\DTOs\Sso\KlienSsoData;
use App\Enums\SemesterType;
use App\Enums\UserRole;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Models\Sso\KlienOAuth;
use App\Models\Sso\KlienSso;
use App\Models\System\LogAktivitas;
use App\Services\Sso\KlienSsoService;
use App\Support\Navigation;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * The SSO client screen: secrets shown once and stored hashed, revocation that
 * takes refresh tokens with it, a first-party flag that is actually honoured,
 * and only integrasi.kredensial holders at the controls.
 */
beforeEach(function () {
    config(['sso.enabled' => true]);
    $this->seed(RolePermissionSeeder::class);
    siapkanKunciPassport();

    Passport::tokensCan(config('sso.scopes'));
    Passport::setDefaultScope(...config('sso.default_scopes'));

    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    Portal::lupakanTerm();

    $this->tik = Staff::factory()->create();
    $this->tik->assignRole('tik');

    $this->layanan = app(KlienSsoService::class);
});

function klienUji(bool $pihakPertama = false): array
{
    return test()->layanan->daftarkan(new KlienSsoData('Open Campus', ['https://campus.test/callback'], $pihakPertama), test()->tik);
}

/** An access token plus its refresh token for one person, written the way Passport writes them. */
function izinkan(KlienOAuth $klien, string $userId, bool $kedaluwarsa = false): string
{
    $id = Str::random(40);

    DB::table('oauth_access_tokens')->insert([
        'id' => $id, 'user_id' => $userId, 'client_id' => $klien->getKey(), 'name' => null, 'scopes' => '["identitas"]',
        'revoked' => false, 'created_at' => now(), 'updated_at' => now(),
        'expires_at' => $kedaluwarsa ? now()->subMinute() : now()->addHour(),
    ]);
    DB::table('oauth_refresh_tokens')->insert([
        'id' => Str::random(40), 'access_token_id' => $id, 'revoked' => false,
        'expires_at' => $kedaluwarsa ? now()->subMinute() : now()->addMonth(),
    ]);

    return $id;
}

function urlOtorisasiKlien(string $clientId): string
{
    return '/oauth/authorize?'.http_build_query([
        'client_id' => $clientId, 'redirect_uri' => 'https://campus.test/callback',
        'response_type' => 'code', 'scope' => 'identitas', 'state' => 'uji',
    ]);
}

describe('KlienSsoService', function () {
    it('mendaftarkan klien dengan rahasia yang tersimpan ter-hash dan tidak tercatat', function () {
        ['klien' => $klien, 'rahasia' => $rahasia] = klienUji();

        $tersimpan = DB::table('oauth_clients')->where('id', $klien->getKey())->value('secret');

        expect($klien)->toBeInstanceOf(KlienOAuth::class)
            ->and(strlen($rahasia))->toBe(40)
            ->and($tersimpan)->not->toBe($rahasia)
            ->and(Hash::check($rahasia, $tersimpan))->toBeTrue()
            ->and(KlienSso::where('oauth_client_id', $klien->getKey())->value('didaftarkan_oleh_staff_id'))->toBe($this->tik->id)
            ->and(json_encode(LogAktivitas::query()->get()->map->getAttributes()))->not->toContain($rahasia);
    });

    it('menolak redirect non-HTTPS selain localhost', function () {
        expect(fn () => $this->layanan->daftarkan(new KlienSsoData('X', ['http://campus.test/cb'])))
            ->toThrow(AturanAkademikException::class, 'http://campus.test/cb');

        $lokal = $this->layanan->daftarkan(new KlienSsoData('Lokal', ['http://localhost:3000/cb']));
        expect($lokal['klien']->redirect_uris)->toBe(['http://localhost:3000/cb']);
    });

    it('merotasi rahasia: yang lama tidak lagi cocok, rotasi tercatat tanpa nilainya', function () {
        ['klien' => $klien, 'rahasia' => $lama] = klienUji();

        $baru = $this->layanan->rotasiRahasia($klien);
        $tersimpan = DB::table('oauth_clients')->where('id', $klien->getKey())->value('secret');

        expect(Hash::check($baru, $tersimpan))->toBeTrue()
            ->and(Hash::check($lama, $tersimpan))->toBeFalse()
            ->and(KlienSso::first()->rahasia_dirotasi_at)->not->toBeNull()
            ->and(LogAktivitas::where('event', 'sso_secret_rotated')->exists())->toBeTrue()
            ->and(json_encode(LogAktivitas::query()->get()->map->getAttributes()))->not->toContain($baru);
    });

    it('mencabut klien beserta token akses dan refresh token-nya, dan tidak dapat dihidupkan lagi', function () {
        ['klien' => $klien] = klienUji();
        izinkan($klien, (string) Str::uuid());
        izinkan($klien, (string) Str::uuid());

        expect($this->layanan->cabut($klien))->toBe(2)
            ->and($klien->refresh()->revoked)->toBeTrue()
            ->and(DB::table('oauth_access_tokens')->where('revoked', false)->count())->toBe(0)
            ->and(DB::table('oauth_refresh_tokens')->where('revoked', false)->count())->toBe(0)
            ->and(LogAktivitas::where('event', 'sso_client_revoked')->exists())->toBeTrue()
            ->and(fn () => $this->layanan->rotasiRahasia($klien))->toThrow(AturanAkademikException::class, 'sudah dicabut')
            ->and(fn () => $this->layanan->cabut($klien))->toThrow(AturanAkademikException::class, 'sudah dicabut');
    });

    it('menghitung pengguna yang izinnya masih dapat dipakai, sekali per orang', function () {
        ['klien' => $klien] = klienUji();
        $orang = (string) Str::uuid();
        izinkan($klien, $orang);
        izinkan($klien, $orang);   // diperbarui: dua token, satu orang
        izinkan($klien, (string) Str::uuid());
        izinkan($klien, (string) Str::uuid(), kedaluwarsa: true);

        expect($this->layanan->ringkasan()->first()['pengguna'])->toBe(2);
    });

    it('membuat catatan kampus untuk klien yang didaftarkan sebelum layar ini ada', function () {
        $lama = app(ClientRepository::class)
            ->createAuthorizationCodeGrantClient('Lama', ['https://lama.test/cb']);

        $this->layanan->aturPihakPertama($lama, true);

        expect(KlienSso::where('oauth_client_id', $lama->getKey())->value('is_first_party'))->toBeTrue();
    });
});

describe('pihak pertama', function () {
    it('melewati layar persetujuan hanya untuk klien pihak pertama', function () {
        $mahasiswa = Mahasiswa::factory()->create();
        $mahasiswa->assignRole('mahasiswa');

        ['klien' => $biasa] = klienUji();
        ['klien' => $milikKampus] = klienUji(pihakPertama: true);

        $this->actingAs($mahasiswa, 'mahasiswa')->get(urlOtorisasiKlien($biasa->getKey()))->assertOk();

        $langsung = $this->actingAs($mahasiswa, 'mahasiswa')->get(urlOtorisasiKlien($milikKampus->getKey()));
        $langsung->assertRedirect();
        expect($langsung->headers->get('Location'))->toStartWith('https://campus.test/callback?code=');
    });

    it('menghormati SSO_FIRST_PARTY yang sudah didokumentasikan', function () {
        ['klien' => $klien] = klienUji();
        config(['sso.first_party' => [(string) $klien->getKey()]]);

        expect($klien->refresh()->skipsAuthorization(Mahasiswa::factory()->create(), ['identitas']))->toBeTrue();
    });
});

describe('layar', function () {
    it('mendaftarkan klien lewat formulir dan menampilkan rahasianya sekali', function () {
        $this->actingAs($this->tik, 'staff');

        $this->post(route('admin.sso-klien.store'), [
            'nama' => 'Open Campus', 'redirect_uris' => "https://campus.test/callback\nhttps://campus.test/callback2",
        ])->assertRedirect(route('admin.sso-klien.index'));

        $klien = KlienOAuth::firstOrFail();
        expect($klien->redirect_uris)->toBe(['https://campus.test/callback', 'https://campus.test/callback2']);

        $pertama = $this->get(route('admin.sso-klien.index'))->assertOk();
        $rahasia = Str::between($pertama->getContent(), 'select-all break-all', '</code>');
        $rahasia = trim(Str::after($rahasia, '>'));

        expect(Hash::check($rahasia, DB::table('oauth_clients')->value('secret')))->toBeTrue()
            ->and($pertama->headers->get('Cache-Control'))->toContain('no-store');

        $this->get(route('admin.sso-klien.index'))->assertOk()->assertDontSee($rahasia)->assertSee('Open Campus');
    });

    it('menolak redirect HTTP lewat formulir dengan menyebut URI-nya', function () {
        $this->actingAs($this->tik, 'staff')
            ->post(route('admin.sso-klien.store'), ['nama' => 'X', 'redirect_uris' => 'http://jahat.test/cb'])
            ->assertSessionHasErrors('redirect_uris');

        expect(KlienOAuth::count())->toBe(0);
    });

    it('mencabut, merotasi, dan menandai pihak pertama dari layar', function () {
        ['klien' => $klien] = klienUji();
        izinkan($klien, (string) Str::uuid());
        $this->actingAs($this->tik, 'staff');

        $this->post(route('admin.sso-klien.pihak-pertama', $klien), ['pihak_pertama' => '1'])->assertSessionHas('sukses');
        expect($klien->refresh()->pihakPertama())->toBeTrue();

        $this->post(route('admin.sso-klien.rotasi', $klien))->assertSessionHas('rahasia_sekali');

        $this->delete(route('admin.sso-klien.cabut', $klien))->assertSessionHas('sukses');
        expect($klien->refresh()->revoked)->toBeTrue()
            ->and(DB::table('oauth_refresh_tokens')->where('revoked', false)->count())->toBe(0);
    });

    it('membatasi layar pada pemegang integrasi.kredensial', function () {
        ['klien' => $klien] = klienUji();

        $baak = Staff::factory()->create();
        $baak->assignRole('baak');

        $this->actingAs($baak, 'staff')->get(route('admin.sso-klien.index'))->assertForbidden();
        $this->actingAs($baak, 'staff')->post(route('admin.sso-klien.store'), ['nama' => 'X', 'redirect_uris' => 'https://x.test/cb'])->assertForbidden();
        $this->actingAs($baak, 'staff')->post(route('admin.sso-klien.rotasi', $klien))->assertForbidden();
        $this->actingAs($baak, 'staff')->delete(route('admin.sso-klien.cabut', $klien))->assertForbidden();

        expect($klien->refresh()->revoked)->toBeFalse();
    });

    it('membuka daftar klien dengan jumlah kueri tetap berapa pun banyaknya klien', function () {
        klienUji();

        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->tik, 'staff')->get(route('admin.sso-klien.index'))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $awal = $hitung();
        foreach (range(1, 6) as $i) {
            ['klien' => $k] = klienUji();
            izinkan($k, (string) Str::uuid());
        }

        expect($hitung())->toBeLessThanOrEqual($awal)->toBeLessThanOrEqual(16);
    });
});

describe('perintah dan navigasi', function () {
    it('mencatat klien yang didaftarkan lewat artisan dan menolak redirect HTTP di sana juga', function () {
        $this->artisan('openacademic:sso-client', ['nama' => 'Dari CLI', '--redirect' => ['https://cli.test/cb']])->assertExitCode(0);
        $this->artisan('openacademic:sso-client', ['nama' => 'Jahat', '--redirect' => ['http://jahat.test/cb']])->assertExitCode(1);

        $klien = KlienOAuth::where('name', 'Dari CLI')->firstOrFail();

        expect(KlienSso::where('oauth_client_id', $klien->getKey())->exists())->toBeTrue()
            ->and(KlienOAuth::where('name', 'Jahat')->exists())->toBeFalse();
    });

    it('menyembunyikan menu Klien SSO bila SSO dimatikan', function () {
        $label = fn (): array => collect(Navigation::for(UserRole::Staff))
            ->pluck('items')->flatten(1)->pluck('label')->all();

        expect($label())->toContain(__('sso_klien.nav'));

        config(['sso.enabled' => false]);

        expect($label())->not->toContain(__('sso_klien.nav'));
    });
});
