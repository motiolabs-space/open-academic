<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Http\Resources\Bridge\GraduateResource;
use App\Models\Kemahasiswaan\Yudisium;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GraduateController extends Controller
{
    use ResolvesBridgeQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $lulusan = Yudisium::query()
            ->with(['mahasiswa.prodi', 'mahasiswa.alumni', 'tahunAkademik'])
            ->where('status', 'ditetapkan')
            ->when($request->string('semester')->toString(), fn ($q, string $kode) => $q->whereHas(
                'tahunAkademik',
                fn ($sub) => $sub->where('kode', $kode),
            ))
            ->when($request->string('prodi')->toString(), fn ($q, string $kode) => $q->whereHas(
                'mahasiswa.prodi',
                fn ($sub) => $sub->where('kode', $kode),
            ))
            ->when($request->string('tahun_lulus')->toString(), fn ($q, string $tahun) => $q->whereYear('tanggal_lulus', $tahun))
            ->orderByDesc('tanggal_lulus')
            ->paginate($this->perPage($request));

        return GraduateResource::collection($lulusan);
    }
}
