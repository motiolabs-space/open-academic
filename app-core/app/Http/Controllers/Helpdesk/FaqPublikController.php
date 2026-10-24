<?php

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\Helpdesk\HelpdeskFaq;
use App\Models\Helpdesk\HelpdeskKategori;
use Illuminate\View\View;

/**
 * The public FAQ page.
 *
 * No login, deliberately: the person who most needs "how do I reset my
 * password" is the one who cannot sign in to read it. Only published entries
 * appear, and nothing on the page names anybody.
 */
class FaqPublikController extends Controller
{
    public function __invoke(): View
    {
        $faq = HelpdeskFaq::query()
            ->terbit()
            ->with('kategori')
            ->urut()
            ->get();

        return view('helpdesk.faq', [
            // Grouped by category for the page; uncategorised entries go last
            // under a general heading rather than vanishing.
            'kelompok' => $faq
                ->groupBy(fn (HelpdeskFaq $f): string => $f->kategori?->nama ?? '')
                ->sortKeys()
                ->sortBy(fn ($daftar, string $nama): int => $nama === '' ? 1 : 0),
            'kategori' => HelpdeskKategori::query()->aktif()->urut()->get(),
        ]);
    }
}
