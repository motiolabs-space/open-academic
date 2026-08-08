<?php

declare(strict_types=1);

namespace App\Providers;

use App\Notifications\Channels\DatabaseKategoriChannel;
use App\Services\Notifikasi\Contracts\WhatsAppGatewayInterface;
use App\Services\Notifikasi\LogWhatsAppGateway;
use App\Services\Notifikasi\Preferensi;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Shared so its per-request memo actually helps.
         *
         * Notification::via() runs once per recipient. Announcing a term's
         * invoices to five thousand students would otherwise add five thousand
         * preference lookups on top of the sends themselves.
         */
        $this->app->singleton(Preferensi::class);

        /*
         * WhatsApp: the seam, not an integration.
         *
         * No provider adapter ships here — every provider has its own account
         * model, template approval, and per-message price, and a guessed
         * adapter is one nobody can run. A campus writes one against
         * WhatsAppGatewayInterface and binds it in place of this line.
         *
         * An unknown driver name fails at resolution rather than silently
         * falling back to logging: "configured but not actually sending" is the
         * state that goes unnoticed until results day.
         */
        $this->app->bind(WhatsAppGatewayInterface::class, function (): WhatsAppGatewayInterface {
            $driver = (string) config('notifikasi.whatsapp.driver');

            return match ($driver) {
                'nonaktif', 'log' => new LogWhatsAppGateway,
                default => throw new InvalidArgumentException(
                    "Driver WhatsApp \"{$driver}\" tidak dikenal. Open Academic tidak menyertakan "
                        .'adaptor penyedia mana pun — daftarkan implementasi WhatsAppGatewayInterface '
                        .'Anda sendiri, atau setel NOTIFIKASI_WHATSAPP_DRIVER=nonaktif.',
                ),
            };
        });
    }

    public function boot(): void
    {
        $this->hardenModels();
        $this->hardenPasswords();
        $this->hardenUrls();
        $this->kanalNotifikasi();
        $this->batasVerifikasi();
    }

    /**
     * Rate limit for the public document-verification page.
     *
     * The manual lookup takes a guess, and the answer is authoritative — which
     * is exactly what makes guessing worth somebody's time. Per IP, which is
     * crude but is all an anonymous endpoint has to work with.
     */
    private function batasVerifikasi(): void
    {
        RateLimiter::for('verifikasi', fn (Request $request) => Limit::perMinute(
            (int) config('surat.verifikasi.batas_per_menit'),
        )->by($request->ip()));
    }

    /**
     * Replaces the stock database channel with one that also writes the
     * category to its own column.
     *
     * See DatabaseKategoriChannel: filtering the JSON payload is not portable,
     * and the notification list filters by category.
     */
    private function kanalNotifikasi(): void
    {
        Notification::resolved(function (ChannelManager $manager): void {
            $manager->extend('database', fn (): DatabaseKategoriChannel => new DatabaseKategoriChannel);
        });
    }

    /**
     * Turns silent data bugs into loud failures — outside production.
     *
     * `preventLazyLoading` is the one that matters: an N+1 does not break a
     * demo campus of fifty students, it breaks a real one of eight thousand,
     * long after the code was written. Making it fail in development is the
     * only reliable way to catch it while the query is still cheap to fix.
     *
     * In production these stay off: a page that renders one extra query is
     * better than a page that 500s at a registrar's desk.
     */
    private function hardenModels(): void
    {
        Model::preventLazyLoading(!$this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());
    }

    /**
     * Password policy for the whole application.
     *
     * Campus accounts are a standing target: a single reused password on a
     * staff account exposes every student record the account can reach.
     */
    private function hardenPasswords(): void
    {
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));
    }

    private function hardenUrls(): void
    {
        // Behind a TLS-terminating proxy Laravel would otherwise generate
        // http:// links, which browsers block as mixed content.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
