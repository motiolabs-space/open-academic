<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\FilterKalender;
use App\DTOs\Akademik\PeristiwaKalender;
use App\Models\System\CalendarFeedToken;
use App\Services\Branding\BrandingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Personal iCalendar feeds a person can subscribe to from Google or Outlook.
 *
 * The URL is the credential: calendar clients poll it unattended and cannot
 * sign in, so whoever holds the link reads the feed. Three things follow.
 *
 * - The feed carries nothing that identifies its owner: no name, no NIM, no
 *   email. It is the owner's *view* of the calendar — their programme's
 *   entries and their own exam sessions with course, time and room — so a
 *   leaked link tells a stranger when some class sits its UTS, not whose
 *   calendar it was.
 * - Only the token's hash is stored, and the plain URL is shown once.
 * - One live link per person. Issuing a new one revokes the old, and the
 *   owner can revoke without replacing — the remedy for a link pasted into
 *   the wrong place.
 */
class LanggananKalenderService
{
    private const PANJANG_TOKEN = 48;

    /** The feed covers this much of the past, so last month's exams do not vanish from the subscriber's calendar. */
    private const MUNDUR_BULAN = 3;

    private const MAJU_BULAN = 12;

    /** Clients poll every few minutes; last use is recorded at most this often, not on every poll. */
    private const CATAT_PEMAKAIAN_MENIT = 60;

    public function __construct(
        private readonly KalenderService $kalender,
        private readonly BrandingService $branding,
    ) {}

    public function aktif(Model $pemilik): ?CalendarFeedToken
    {
        return CalendarFeedToken::query()
            ->where('owner_type', $pemilik->getMorphClass())
            ->where('owner_id', $pemilik->getKey())
            ->aktif()
            ->latest('id')
            ->first();
    }

    /**
     * Issues a new feed token, revoking any live one.
     *
     * @return string the plain token — returned once, never stored
     */
    public function terbitkan(Model $pemilik): string
    {
        $token = Str::random(self::PANJANG_TOKEN);

        DB::transaction(function () use ($pemilik, $token): void {
            $this->cabut($pemilik);

            CalendarFeedToken::create([
                'owner_type' => $pemilik->getMorphClass(),
                'owner_id' => $pemilik->getKey(),
                'token_hash' => CalendarFeedToken::hash($token),
            ]);
        });

        return $token;
    }

    /** @return int how many live tokens were revoked */
    public function cabut(Model $pemilik): int
    {
        return CalendarFeedToken::query()
            ->where('owner_type', $pemilik->getMorphClass())
            ->where('owner_id', $pemilik->getKey())
            ->aktif()
            ->update(['revoked_at' => now()]);
    }

    /**
     * The person a live token belongs to.
     *
     * Null for an unknown or revoked token, and for an owner who can no longer
     * sign in — a deactivated account's feed stops with its account, without
     * anybody having to remember the link exists.
     */
    public function pemilik(string $token): ?Model
    {
        if (strlen($token) !== self::PANJANG_TOKEN) {
            return null;
        }

        $baris = CalendarFeedToken::query()
            ->with('owner')
            ->where('token_hash', CalendarFeedToken::hash($token))
            ->aktif()
            ->first();

        $pemilik = $baris?->owner;

        if ($pemilik === null || $pemilik->getAttribute('is_active') === false) {
            return null;
        }

        if ($baris->last_used_at === null || $baris->last_used_at->lt(now()->subMinutes(self::CATAT_PEMAKAIAN_MENIT))) {
            $baris->forceFill(['last_used_at' => now()])->save();
        }

        return $pemilik;
    }

    /** The feed behind a token, or null when the token opens nothing. */
    public function ics(string $token): ?string
    {
        $pemilik = $this->pemilik($token);

        if ($pemilik === null) {
            return null;
        }

        $hariIni = Carbon::today();

        $filter = new FilterKalender(
            $hariIni->copy()->subMonths(self::MUNDUR_BULAN)->startOfMonth(),
            $hariIni->copy()->addMonths(self::MAJU_BULAN)->endOfMonth(),
        );

        // Drafts never: a subscribed calendar is somebody's phone, and an
        // unpublished holiday that lands there cannot be taken back.
        return $this->render($this->kalender->untuk($pemilik, $filter, termasukDraf: false));
    }

    /**
     * RFC 5545 text for a list of entries.
     *
     * Whole-day entries are DATE values with an exclusive end, as the standard
     * requires; timed ones are converted to UTC so no VTIMEZONE block is needed.
     *
     * @param Collection<int, PeristiwaKalender> $peristiwa
     */
    public function render(Collection $peristiwa): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'open-academic';
        $stempel = now()->utc()->format('Ymd\THis\Z');

        $baris = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Open Academic//Kalender Akademik//ID',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->teks(__('kalender.ics.nama', ['kampus' => $this->branding->institutionShortName()])),
            'X-WR-TIMEZONE:'.config('app.timezone'),
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ];

        foreach ($peristiwa as $p) {
            $baris[] = 'BEGIN:VEVENT';
            $baris[] = 'UID:'.$p->kunci.'@'.$host;
            $baris[] = 'DTSTAMP:'.$stempel;

            if ($p->berwaktu()) {
                $baris[] = 'DTSTART:'.$this->waktuUtc($p->mulai, $p->jamMulai);
                $baris[] = 'DTEND:'.$this->waktuUtc($p->mulai, $p->jamSelesai);
            } else {
                $baris[] = 'DTSTART;VALUE=DATE:'.$p->mulai->format('Ymd');
                $baris[] = 'DTEND;VALUE=DATE:'.$p->selesai->copy()->addDay()->format('Ymd');
                $baris[] = 'TRANSP:TRANSPARENT';
            }

            $baris[] = 'SUMMARY:'.$this->teks($p->prodiNama === null ? $p->judul : $p->judul.' · '.$p->prodiNama);

            $keterangan = array_filter([$p->keterangan, __('kalender.ics.sumber', ['sumber' => $p->jenis->sumber()])]);
            $baris[] = 'DESCRIPTION:'.$this->teks(implode("\n", $keterangan));

            if ($p->lokasi !== null) {
                $baris[] = 'LOCATION:'.$this->teks($p->lokasi);
            }

            $baris[] = 'CATEGORIES:'.$this->teks($p->jenis->label());
            $baris[] = 'END:VEVENT';
        }

        $baris[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $b): string => $this->lipat($b), $baris))."\r\n";
    }

    private function waktuUtc(Carbon $tanggal, string $jam): string
    {
        return Carbon::parse($tanggal->toDateString().' '.$jam, config('app.timezone'))
            ->utc()
            ->format('Ymd\THis\Z');
    }

    /** TEXT value escaping, RFC 5545 §3.3.11. */
    private function teks(string $nilai): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $nilai,
        );
    }

    /**
     * Folds a content line at 75 octets, RFC 5545 §3.1.
     *
     * Octets, not characters, and never inside a multi-byte character: an
     * Indonesian course title with an en dash would otherwise be split into
     * invalid UTF-8 halfway through the dash.
     */
    private function lipat(string $baris): string
    {
        $potongan = [];
        $batas = 75;

        while (strlen($baris) > $batas) {
            $bagian = mb_strcut($baris, 0, $batas, 'UTF-8');
            $potongan[] = $bagian;
            $baris = substr($baris, strlen($bagian));

            // Continuation lines start with a space, which counts against them.
            $batas = 74;
        }

        $potongan[] = $baris;

        return implode("\r\n ", $potongan);
    }
}
