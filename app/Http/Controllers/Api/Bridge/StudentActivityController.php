<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Http\Resources\Bridge\StudentActivityResource;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentActivityController extends Controller
{
    use ResolvesBridgeQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $term = $this->term($request, wajib: true);

        $aktivitas = AktivitasMahasiswa::query()
            ->with(['mahasiswa.prodi', 'tahunAkademik', 'pembimbing'])
            ->where('tahun_akademik_id', $term->id)
            ->when($request->string('jenis')->toString(), fn ($q, string $jenis) => $q->where('jenis', $jenis))
            ->when($request->string('prodi')->toString(), fn ($q, string $kode) => $q->whereHas(
                'mahasiswa.prodi',
                fn ($sub) => $sub->where('kode', $kode),
            ))

            // Unverified records exist and are visible on request, but the
            // default is the verified set: an indicator built on unchecked
            // self-reports is not evidence.
            ->when(
                !$request->boolean('sertakan_belum_verifikasi'),
                fn ($q) => $q->terverifikasi(),
            )

            ->when($request->integer('min_sks'), fn ($q, int $sks) => $q->where('sks_konversi', '>=', $sks))
            ->orderByDesc('tanggal_mulai')
            ->paginate($this->perPage($request));

        return StudentActivityResource::collection($aktivitas);
    }
}
