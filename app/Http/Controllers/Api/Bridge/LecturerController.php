<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Http\Resources\Bridge\LecturerResource;
use App\Models\Sdm\Dosen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LecturerController extends Controller
{
    use ResolvesBridgeQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $dosen = Dosen::query()
            ->with('prodi')
            ->when($request->string('prodi')->toString(), fn ($q, string $kode) => $q->whereHas(
                'prodi',
                fn ($sub) => $sub->where('kode', $kode),
            ))
            ->when($request->boolean('praktisi'), fn ($q) => $q->where('is_praktisi', true))
            ->when($request->boolean('aktif', true), fn ($q) => $q->where('is_active', true))
            ->when(
                $request->boolean('sertakan_penugasan'),
                fn ($q) => $q->with(['penugasan' => fn ($sub) => $sub->terverifikasi()]),
            )
            ->orderBy('nama')
            ->paginate($this->perPage($request));

        return LecturerResource::collection($dosen);
    }

    public function show(string $uuid): LecturerResource
    {
        $dosen = Dosen::query()
            ->with(['prodi', 'penugasan.tahunAkademik'])
            ->whereUuid($uuid)
            ->firstOrFail();

        return new LecturerResource($dosen);
    }
}
