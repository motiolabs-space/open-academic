<?php

declare(strict_types=1);

namespace App\Services\Sso;

use App\DTOs\Sso\KlienSsoData;
use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\Staff;
use App\Models\Sso\KlienOAuth;
use App\Models\Sso\KlienSso;
use App\Rules\UriCallbackAman;
use App\Services\Auth\PencabutanTokenSso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * Registering SSO clients and managing their secrets, for the screen and for
 * `openacademic:sso-client` alike.
 *
 * Passport hashes client secrets, so the plain text exists once — in the
 * return value of the call that made it — and the screen shows it once. What
 * the audit trail records is the campus's own row (KlienSso): registered,
 * marked first-party, secret rotated, revoked. Never the secret.
 */
class KlienSsoService
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly PencabutanTokenSso $pencabutan,
    ) {}

    /**
     * @return array{klien: KlienOAuth, rahasia: string}
     */
    public function daftarkan(KlienSsoData $data, ?Staff $oleh = null): array
    {
        if (blank($data->nama)) {
            throw new AturanAkademikException(__('sso_klien.galat.nama_kosong'));
        }

        if ($data->redirectUris === []) {
            throw new AturanAkademikException(__('sso_klien.galat.redirect_kosong'));
        }

        foreach ($data->redirectUris as $uri) {
            if (!UriCallbackAman::sah($uri)) {
                throw new AturanAkademikException(__('sso_klien.galat.redirect_tidak_aman', ['uri' => $uri]));
            }
        }

        return DB::transaction(function () use ($data, $oleh): array {
            /** @var KlienOAuth $klien */
            $klien = $this->clients->createAuthorizationCodeGrantClient(
                name: $data->nama,
                redirectUris: $data->redirectUris,
                confidential: true,
            );

            $rahasia = (string) $klien->plainSecret;

            KlienSso::create([
                'oauth_client_id' => $klien->getKey(),
                'is_first_party' => $data->pihakPertama,
                'didaftarkan_oleh_staff_id' => $oleh?->id,
            ]);

            return ['klien' => $klien, 'rahasia' => $rahasia];
        });
    }

    /**
     * A new client secret, returned once.
     *
     * Access tokens already issued stay valid until they expire (an hour by
     * default); refreshing them, and every new code exchange, needs the new
     * secret.
     */
    public function rotasiRahasia(KlienOAuth $klien): string
    {
        $this->pastikanBelumDicabut($klien);

        return DB::transaction(function () use ($klien): string {
            $this->clients->regenerateSecret($klien);
            $rahasia = (string) $klien->plainSecret;

            $catatan = $this->pengaturan($klien);
            $catatan->update(['rahasia_dirotasi_at' => now()]);
            $catatan->recordActivity('sso_secret_rotated', __('sso_klien.log.rahasia_dirotasi'));

            return $rahasia;
        });
    }

    /** Whether the client may skip the consent screen. */
    public function aturPihakPertama(KlienOAuth $klien, bool $pihakPertama): void
    {
        $this->pastikanBelumDicabut($klien);

        $this->pengaturan($klien)->update(['is_first_party' => $pihakPertama]);
    }

    /**
     * Revokes the client and every token issued through it, refresh tokens
     * included (PencabutanTokenSso). Final: a client revoked by mistake is
     * registered again, so the old credentials can never come back.
     *
     * @return int the number of access tokens revoked
     */
    public function cabut(KlienOAuth $klien): int
    {
        $this->pastikanBelumDicabut($klien);

        return DB::transaction(function () use ($klien): int {
            $klien->forceFill(['revoked' => true])->save();
            $jumlah = $this->pencabutan->cabut((string) $klien->getKey());

            $catatan = $this->pengaturan($klien);
            $catatan->update(['dicabut_at' => now(), 'is_first_party' => false]);
            $catatan->recordActivity('sso_client_revoked', __('sso_klien.log.dicabut', ['jumlah' => $jumlah]));

            return $jumlah;
        });
    }

    /**
     * Every client with the number of people who currently allow it.
     *
     * "Currently" means an unrevoked grant that can still be used: an access
     * token not yet expired, or one whose refresh token is still live. Three
     * queries whatever the number of clients.
     *
     * @return Collection<int, array{klien: KlienOAuth, pengguna: int}>
     */
    public function ringkasan(): Collection
    {
        $daftar = KlienOAuth::query()
            ->with('pengaturan.pendaftar')
            ->orderBy('revoked')
            ->orderBy('name')
            ->get();

        $token = Passport::tokenModel();
        $tabelToken = (new $token)->getTable();
        $tabelRefresh = (new (Passport::refreshTokenModel()))->getTable();

        $pengguna = DB::table($tabelToken.' as t')
            ->leftJoin($tabelRefresh.' as r', 'r.access_token_id', '=', 't.id')
            ->whereIn('t.client_id', $daftar->modelKeys())
            ->where('t.revoked', false)
            ->where(fn ($q) => $q->where('t.expires_at', '>', now())
                ->orWhere(fn ($r) => $r->where('r.revoked', false)->where('r.expires_at', '>', now())))
            ->groupBy('t.client_id')
            ->selectRaw('t.client_id, COUNT(DISTINCT t.user_id) as jumlah')
            ->pluck('jumlah', 'client_id');

        return $daftar->map(fn (KlienOAuth $k): array => [
            'klien' => $k,
            'pengguna' => (int) ($pengguna[$k->getKey()] ?? 0),
        ]);
    }

    /** The campus's row for a client, created on first touch for clients registered before it existed. */
    public function pengaturan(KlienOAuth $klien): KlienSso
    {
        return $klien->pengaturan ?? tap(
            KlienSso::create(['oauth_client_id' => $klien->getKey()]),
            fn (KlienSso $baru) => $klien->setRelation('pengaturan', $baru),
        );
    }

    private function pastikanBelumDicabut(KlienOAuth $klien): void
    {
        if ($klien->revoked) {
            throw new AturanAkademikException(__('sso_klien.galat.sudah_dicabut'));
        }
    }
}
