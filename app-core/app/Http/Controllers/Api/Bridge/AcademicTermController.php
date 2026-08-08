<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Http\Resources\Bridge\AcademicTermResource;
use App\Models\Akademik\TahunAkademik;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AcademicTermController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AcademicTermResource::collection(TahunAkademik::terbaru()->get());
    }

    public function current(): AcademicTermResource
    {
        $term = TahunAkademik::aktif();

        abort_if($term === null, 404, 'Belum ada semester yang ditetapkan aktif.');

        return new AcademicTermResource($term);
    }
}
