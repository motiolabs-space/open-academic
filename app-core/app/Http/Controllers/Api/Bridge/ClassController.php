<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Http\Resources\Bridge\ClassResource;
use App\Models\Akademik\KelasKuliah;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassController extends Controller
{
    use ResolvesBridgeQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $term = $this->term($request, wajib: true);

        $kelas = KelasKuliah::query()
            ->with(['mataKuliah', 'prodi', 'tahunAkademik', 'dosen', 'jadwal.ruang'])
            ->where('tahun_akademik_id', $term->id)
            ->when($request->string('prodi')->toString(), fn ($q, string $kode) => $q->whereHas(
                'prodi',
                fn ($sub) => $sub->where('kode', $kode),
            ))

            // The IKU 7 filter: collaborative teaching methods only.
            ->when($request->boolean('kolaboratif'), fn ($q) => $q->where(
                fn ($sub) => $sub->where('is_case_method', true)->orWhere('is_team_based_project', true),
            ))

            // The IKU 4 filter: classes co-taught by a practitioner.
            ->when($request->boolean('praktisi'), fn ($q) => $q->whereHas(
                'dosen',
                fn ($sub) => $sub->where('kelas_dosen.peran', 'praktisi'),
            ))

            ->orderBy('id')
            ->paginate($this->perPage($request));

        return ClassResource::collection($kelas);
    }

    public function show(string $uuid): ClassResource
    {
        $kelas = KelasKuliah::query()
            ->with(['mataKuliah', 'prodi', 'tahunAkademik', 'dosen', 'jadwal.ruang'])
            ->whereUuid($uuid)
            ->firstOrFail();

        return new ClassResource($kelas);
    }
}
