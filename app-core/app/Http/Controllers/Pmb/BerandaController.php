<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Services\Pmb\GelombangPmbService;
use Illuminate\View\View;

/**
 * The public admission page: which waves are open, for what, at what price.
 *
 * Public and indexable on purpose — it is the one page in the system a
 * campus wants prospective students to find through a search engine.
 */
class BerandaController extends Controller
{
    public function __construct(private readonly GelombangPmbService $gelombang) {}

    public function __invoke(): View
    {
        return view('pmb.beranda', [
            'daftarGelombang' => $this->gelombang->terbukaDaring(),
        ]);
    }
}
