<?php

declare(strict_types=1);

namespace App\Services\Notifikasi;

use App\Enums\KategoriNotifikasi;
use App\Models\System\PreferensiNotifikasi;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Which channels a given person receives a given category on.
 *
 * The rule the rest of the system leans on: **a mandatory category always keeps
 * its in-app record**, whatever the stored preference says. Someone who muted
 * everything last year must still be able to point at the notice saying their
 * study plan was rejected.
 */
class Preferensi
{
    /**
     * Memoised per request.
     *
     * via() is called once per notifiable per notification. Announcing a term's
     * invoices to five thousand students would otherwise be five thousand
     * preference lookups on top of the sends themselves.
     *
     * @var array<string, PreferensiNotifikasi|null>
     */
    private array $memo = [];

    /** @return array<int, string> Laravel notification channel names */
    public function kanalUntuk(object $penerima, KategoriNotifikasi $kategori): array
    {
        $pilihan = $this->baris($penerima, $kategori);

        $kanal = [];

        // Mandatory categories ignore the stored choice for the in-app record.
        if ($kategori->wajib() || ($pilihan?->aplikasi ?? true)) {
            $kanal[] = 'database';
        }

        if (($pilihan?->email ?? true) && filled($penerima->email ?? null)) {
            $kanal[] = 'mail';
        }

        /*
         * WhatsApp needs two separate yeses: a driver configured, and this
         * category named in notifikasi.whatsapp.kategori.
         *
         * Both default to off. A campus that installs a provider has not
         * thereby decided that every grade release should reach a phone at
         * 23:00 — that is a second decision, and it costs money and attention.
         *
         * It follows the email preference rather than having a third switch:
         * both are delivery, and the in-app record is what is authoritative.
         */
        if ($this->whatsAppAktif($kategori)
            && ($pilihan?->email ?? true)
            && filled($penerima->telepon ?? null)
        ) {
            $kanal[] = WhatsAppChannel::class;
        }

        return $kanal;
    }

    /**
     * Every category with the person's effective choice, for the settings
     * screen.
     *
     * @return Collection<int, array{kategori: KategoriNotifikasi, aplikasi: bool, email: bool, terkunci: bool}>
     */
    public function ringkasan(object $penerima): Collection
    {
        $tersimpan = PreferensiNotifikasi::query()
            ->where('notifiable_type', $penerima->getMorphClass())
            ->where('notifiable_id', $penerima->getKey())
            ->get()
            ->keyBy(fn (PreferensiNotifikasi $p): string => $p->kategori->value);

        return collect(KategoriNotifikasi::cases())->map(fn (KategoriNotifikasi $k): array => [
            'kategori' => $k,
            'aplikasi' => $k->wajib() ? true : (bool) ($tersimpan[$k->value]->aplikasi ?? true),
            'email' => (bool) ($tersimpan[$k->value]->email ?? true),

            // Rendered as a disabled control rather than hidden: a person is
            // entitled to see that the campus does not let this one be silenced,
            // and why.
            'terkunci' => $k->wajib(),
        ]);
    }

    /**
     * Stores a choice.
     *
     * The in-app flag for a mandatory category is forced back on rather than
     * rejected — a form that half-saves is worse than one that quietly declines
     * the impossible part and says so on the screen.
     */
    public function simpan(Model $penerima, KategoriNotifikasi $kategori, bool $aplikasi, bool $email): void
    {
        PreferensiNotifikasi::updateOrCreate(
            [
                'notifiable_type' => $penerima->getMorphClass(),
                'notifiable_id' => $penerima->getKey(),
                'kategori' => $kategori->value,
            ],
            [
                'aplikasi' => $kategori->wajib() ? true : $aplikasi,
                'email' => $email,
            ],
        );

        unset($this->memo[$this->kunci($penerima, $kategori)]);
    }

    private function baris(object $penerima, KategoriNotifikasi $kategori): ?PreferensiNotifikasi
    {
        $kunci = $this->kunci($penerima, $kategori);

        return $this->memo[$kunci] ??= PreferensiNotifikasi::query()
            ->where('notifiable_type', $penerima->getMorphClass())
            ->where('notifiable_id', $penerima->getKey())
            ->where('kategori', $kategori->value)
            ->first();
    }

    private function kunci(object $penerima, KategoriNotifikasi $kategori): string
    {
        return $penerima->getMorphClass().':'.$penerima->getKey().':'.$kategori->value;
    }

    private function whatsAppAktif(KategoriNotifikasi $kategori): bool
    {
        return config('notifikasi.whatsapp.driver') !== 'nonaktif'
            && in_array($kategori->value, (array) config('notifikasi.whatsapp.kategori'), true);
    }
}
