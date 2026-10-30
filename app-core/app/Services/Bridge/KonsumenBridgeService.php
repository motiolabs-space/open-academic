<?php

declare(strict_types=1);

namespace App\Services\Bridge;

use App\DTOs\Bridge\KonsumenBridgeData;
use App\DTOs\Bridge\WebhookData;
use App\Exceptions\AturanAkademikException;
use App\Models\Bridge\BridgeConsumer;
use App\Rules\UriCallbackAman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Registering Campus Bridge consumers and handing out their credentials.
 *
 * Until October 2026 this was an artisan command and a seeder. Moving it to a
 * screen changes who presses the button, not what the button is allowed to
 * reveal, so three rules hold here for every caller:
 *
 *  - a token or a signing secret exists in plain text exactly once, in the
 *    return value of the call that made it. Sanctum stores only the token's
 *    SHA-256; the webhook secret has to stay recoverable to sign with, so it
 *    is encrypted at rest instead;
 *  - neither ever reaches the activity log. The log records that a token was
 *    issued or a secret rotated, by whom, and the token's name — the events an
 *    auditor asks about — through recordActivity() with no attribute values;
 *  - a scope the configuration does not know is refused, not stored. An
 *    unknown scope grants nothing today and something tomorrow, when a route
 *    with that name is added.
 */
class KonsumenBridgeService
{
    public function daftarkan(KonsumenBridgeData $data): BridgeConsumer
    {
        $this->pastikanScope($data->scopes);

        if (BridgeConsumer::withTrashed()->where('slug', $data->slug)->exists()) {
            throw new AturanAkademikException(__('bridge.galat.slug_dipakai'));
        }

        return BridgeConsumer::create([
            'nama' => $data->nama,
            'slug' => $data->slug,
            'deskripsi' => $data->deskripsi,
            'base_url' => $data->baseUrl,
            'scopes' => $data->scopes,
            'is_active' => $data->aktif,
        ]);
    }

    /**
     * Name, description, scopes and the on/off switch. The slug stays.
     *
     * Narrowing scopes takes effect on the next request without reissuing
     * anything: EnsureBridgeScope reads the consumer's list, not the token's.
     */
    public function perbarui(BridgeConsumer $konsumen, KonsumenBridgeData $data): BridgeConsumer
    {
        $this->pastikanScope($data->scopes);

        $konsumen->update([
            'nama' => $data->nama,
            'deskripsi' => $data->deskripsi,
            'base_url' => $data->baseUrl,
            'scopes' => $data->scopes,
            'is_active' => $data->aktif,
        ]);

        return $konsumen;
    }

    /**
     * Issues a token. The plain text is in the returned object and nowhere
     * else, ever.
     */
    public function terbitkanToken(BridgeConsumer $konsumen, string $nama): NewAccessToken
    {
        if (!$konsumen->is_active) {
            throw new AturanAkademikException(__('bridge.galat.konsumen_nonaktif'));
        }

        $nama = trim($nama) === '' ? 'default' : trim($nama);

        return DB::transaction(function () use ($konsumen, $nama): NewAccessToken {
            // Abilities mirror the scopes for the record; the consumer's own
            // list stays authoritative, as in BridgeTokenCommand.
            $token = $konsumen->createToken($nama, $konsumen->scopes ?? []);

            $konsumen->recordActivity('token_issued', __('bridge.log.token_diterbitkan', ['nama' => $nama]));

            return $token;
        });
    }

    /** Revokes one token. Looked up through the consumer, never by id alone. */
    public function cabutToken(BridgeConsumer $konsumen, int $tokenId): void
    {
        /** @var PersonalAccessToken $token */
        $token = $konsumen->tokens()->whereKey($tokenId)->firstOrFail();

        DB::transaction(function () use ($konsumen, $token): void {
            $token->delete();

            $konsumen->recordActivity('token_revoked', __('bridge.log.token_dicabut', ['nama' => $token->name]));
        });
    }

    /** @return int the number of tokens revoked */
    public function cabutSemuaToken(BridgeConsumer $konsumen): int
    {
        return DB::transaction(function () use ($konsumen): int {
            $jumlah = $konsumen->tokens()->delete();

            if ($jumlah > 0) {
                $konsumen->recordActivity('token_revoked', __('bridge.log.token_dicabut_semua', ['jumlah' => $jumlah]));
            }

            return $jumlah;
        });
    }

    /**
     * Where events go and which ones. Clearing the URL stops deliveries; the
     * secret is kept, so re-entering the URL does not silently change the key
     * the consumer verifies with.
     */
    public function aturWebhook(BridgeConsumer $konsumen, WebhookData $data): BridgeConsumer
    {
        $this->pastikanUrl($data->url);

        $tidakDikenal = array_diff($data->events, (array) config('bridge.events'));

        if ($tidakDikenal !== []) {
            throw new AturanAkademikException(__('bridge.galat.event_tidak_dikenal', ['event' => implode(', ', $tidakDikenal)]));
        }

        $konsumen->update([
            'webhook_url' => $data->url,
            'webhook_events' => $data->events,
        ]);

        return $konsumen;
    }

    /**
     * A new signing secret, returned once.
     *
     * The old one stops working immediately — deliveries already queued are
     * signed when they are sent, with whatever key is current then. The
     * consumer has to be given the new value before the next event.
     */
    public function rotasiRahasiaWebhook(BridgeConsumer $konsumen): string
    {
        $rahasia = 'whsec_'.Str::random(40);

        DB::transaction(function () use ($konsumen, $rahasia): void {
            $konsumen->forceFill(['webhook_secret' => $rahasia])->save();

            $konsumen->recordActivity('webhook_secret_rotated', __('bridge.log.rahasia_dirotasi'));
        });

        return $rahasia;
    }

    /** @param array<int, string> $scopes */
    private function pastikanScope(array $scopes): void
    {
        $tidakDikenal = array_diff($scopes, array_keys((array) config('bridge.scopes')));

        if ($tidakDikenal !== []) {
            throw new AturanAkademikException(__('bridge.galat.scope_tidak_dikenal', ['scope' => implode(', ', $tidakDikenal)]));
        }
    }

    private function pastikanUrl(?string $url): void
    {
        if ($url !== null && !UriCallbackAman::sah($url)) {
            throw new AturanAkademikException(__('bridge.galat.url_tidak_aman'));
        }
    }
}
