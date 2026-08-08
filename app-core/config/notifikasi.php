<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pengingat Tenggat
    |--------------------------------------------------------------------------
    |
    | Days relative to the deadline on which a reminder fires. Positive is
    | before, zero is the day itself, negative is after.
    |
    | Fewer entries than feel natural, on purpose. A channel that speaks every
    | day teaches people to stop reading it, and the message that mattered is
    | then ignored along with the rest. Each entry here should answer "what
    | would somebody do differently on hearing this today?"
    |
    | Deduplication is per (person, deadline, offset), so a reminder fires once
    | however often the scheduler runs — see the notifikasi_kunci table.
    |
    */

    'pengingat' => [
        // Invoices: a nudge with a week to arrange money, one on the day, and
        // one a week after it lapsed.
        'tagihan' => [7, 1, 0, -7],

        // Study plans: only students who have submitted nothing. Missing this
        // window costs a semester, so the last one is the day before.
        'krs' => [7, 3, 1],

        // Post-defence revisions, which people believe they have finished.
        'revisi' => [7, 1, 0, -7],

        // Pending consultation sign-offs, digested. Weekly, not daily: it is a
        // queue, not an event.
        'bimbingan_menunggu_hari' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    |
    | The channel Indonesian campuses actually reach students on. No provider is
    | wired here — see WhatsAppGatewayInterface and docs/NOTIFIKASI.md.
    |
    | "log" writes what would have been sent to the application log, which is
    | enough to develop against and is the safe default: a half-configured
    | gateway that silently drops messages looks identical to one that works.
    |
    */

    'whatsapp' => [
        // "nonaktif" (bawaan) | "log" | nama driver penyedia Anda sendiri.
        'driver' => env('NOTIFIKASI_WHATSAPP_DRIVER', 'nonaktif'),

        'nomor_pengirim' => env('NOTIFIKASI_WHATSAPP_PENGIRIM'),

        /*
         * Which categories go out over WhatsApp. Empty means none.
         *
         * A separate opt-in from the driver, because the two decisions are
         * separate: having a provider configured is not the same as having
         * decided that every grade release should reach a student's phone at
         * 23:00. Each message here costs the campus money and costs the
         * recipient attention.
         *
         * A sensible starting set is ['keuangan', 'pengingat'] — the ones with
         * a deadline attached.
         */
        'kategori' => array_filter(explode(',', (string) env('NOTIFIKASI_WHATSAPP_KATEGORI', ''))),
    ],

];
