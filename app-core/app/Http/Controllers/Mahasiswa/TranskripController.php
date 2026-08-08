<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Nilai;
use App\Services\Akademik\TranskripService;
use App\Support\Portal;
use Symfony\Component\HttpFoundation\Response;

class TranskripController extends Controller
{
    public function __construct(private readonly TranskripService $transkrip) {}

    public function __invoke(): Response
    {
        $this->authorize('viewAny', Nilai::class);

        $mahasiswa = Portal::user();

        return $this->transkrip->pdf($mahasiswa)
            ->download($this->transkrip->namaBerkas($mahasiswa));
    }
}
