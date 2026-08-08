<?php

declare(strict_types=1);

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['sso.enabled' => true]);

    $this->seed(RolePermissionSeeder::class);

    Passport::tokensCan(config('sso.scopes'));

    Artisan::call('passport:keys', ['--force' => true, '--quiet' => true]);

    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Open Campus',
        redirectUris: ['https://campus.test/callback'],
        confidential: true,
    );
});

/**
 * Walks the real authorization-code grant and returns the access token.
 *
 * Deliberately not Passport's createToken(): that issues a personal access
 * token, which needs a provider bound to one model — and the whole point here
 * is that a subject may come from any of three tables. Going through the actual
 * grant is slower but proves the chain a consumer will really use.
 */
function tokenLewatGrant(object $test, Authenticatable $aktor, array $scopes = ['identitas']): string
{
    $guard = match (true) {
        $aktor instanceof Mahasiswa => 'mahasiswa',
        $aktor instanceof Dosen => 'dosen',
        $aktor instanceof Staff => 'staff',
    };

    $client = $test->client;

    $test->actingAs($aktor, $guard)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://campus.test/callback',
        'response_type' => 'code',
        'scope' => implode(' ', $scopes),
        'state' => 'uji',
    ]))->assertOk();

    $lokasi = $test->actingAs($aktor, $guard)->post('/oauth/authorize', [
        'state' => 'uji',
        'client_id' => $client->getKey(),
        'auth_token' => session('authToken'),
    ])->headers->get('Location');

    parse_str(parse_url($lokasi, PHP_URL_QUERY) ?? '', $kueri);

    return $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://campus.test/callback',
        'code' => $kueri['code'],
    ])->json('access_token');
}

it('mengenali mahasiswa dari subject UUID', function () {
    $mahasiswa = Mahasiswa::factory()->create(['nim' => '2026001']);
    $mahasiswa->assignRole('mahasiswa');

    $data = $this->withToken(tokenLewatGrant($this, $mahasiswa))
        ->getJson('/api/sso/userinfo')
        ->assertOk()
        ->json();

    expect($data['sub'])->toBe($mahasiswa->uuid)
        ->and($data['peran'])->toBe('mahasiswa')
        ->and($data['nomor_induk'])->toBe('2026001')
        ->and($data['nama'])->toBe($mahasiswa->nama);
});

it('mengenali dosen dari subject UUID', function () {
    $dosen = Dosen::factory()->create(['nidn' => '0012345678']);
    $dosen->assignRole('dosen');

    $data = $this->withToken(tokenLewatGrant($this, $dosen))
        ->getJson('/api/sso/userinfo')
        ->assertOk()
        ->json();

    expect($data['peran'])->toBe('dosen')
        ->and($data['nomor_induk'])->toBe('0012345678');
});

it('mengenali staf dari subject UUID', function () {
    $staff = Staff::factory()->create(['nip' => '198001012005011001']);
    $staff->assignRole('super-admin');

    $data = $this->withToken(tokenLewatGrant($this, $staff))
        ->getJson('/api/sso/userinfo')
        ->assertOk()
        ->json();

    expect($data['peran'])->toBe('staff')
        ->and($data['nomor_induk'])->toBe('198001012005011001');
});

it('tidak pernah membocorkan data pribadi sensitif', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $data = $this->withToken(tokenLewatGrant($this, $mahasiswa))
        ->getJson('/api/sso/userinfo')
        ->assertOk()
        ->json();

    // Aturan yang sama dengan Campus Bridge: kontrak yang memuatnya akan
    // dipakai orang, jadi jangan pernah dimuat sejak awal.
    //
    // Diperiksa per kunci, bukan sebagai substring pada seluruh badan respons:
    // "nik" muncul di dalam kata "Teknik", dan pemeriksaan substring akan
    // gagal pada prodi yang namanya sah.
    foreach (['nik', 'alamat', 'nama_ibu', 'nama_ayah', 'penghasilan', 'tempat_lahir'] as $terlarang) {
        expect($data)->not->toHaveKey($terlarang);
    }
});

it('menolak token tanpa scope identitas', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $this->withToken(tokenLewatGrant($this, $mahasiswa, ['keuangan.baca']))
        ->getJson('/api/sso/userinfo')
        ->assertForbidden();
});

it('menolak permintaan tanpa token', function () {
    $this->getJson('/api/sso/userinfo')->assertUnauthorized();
});

it('menolak token milik akun yang dinonaktifkan', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $token = tokenLewatGrant($this, $mahasiswa);

    // Menonaktifkan akun harus langsung memutus akses konsumen, bukan menunggu
    // tokennya kedaluwarsa sendiri.
    $mahasiswa->update(['is_active' => false]);

    $this->withToken($token)->getJson('/api/sso/userinfo')->assertUnauthorized();
});
