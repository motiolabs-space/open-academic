<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Bridge\StudentResource;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentController extends Controller
{
    use ResolvesBridgeQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $mahasiswa = Mahasiswa::query()
            ->with(['prodi.fakultas', 'dosenWali'])
            ->when($request->string('prodi')->toString(), fn ($q, string $kode) => $q->whereHas(
                'prodi',
                fn ($sub) => $sub->where('kode', $kode),
            ))
            ->when($request->string('angkatan')->toString(), fn ($q, string $tahun) => $q->where('angkatan', $tahun))
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status))
            ->cari($request->string('cari'), ['nama', 'nim'])

            // A consumer computing indicators needs the per-term history; one
            // rendering a directory does not, and paying for it either way is
            // how a listing endpoint becomes slow.
            ->when($request->boolean('sertakan_riwayat'), fn ($q) => $q->with('statusPerSemester.tahunAkademik'))

            ->orderBy('nim')
            ->paginate($this->perPage($request));

        return StudentResource::collection($mahasiswa);
    }

    public function show(string $uuid): StudentResource
    {
        $mahasiswa = Mahasiswa::query()
            ->with(['prodi.fakultas', 'dosenWali', 'statusPerSemester.tahunAkademik'])
            ->whereUuid($uuid)
            ->firstOrFail();

        return new StudentResource($mahasiswa);
    }

    /** Aggregate counts, so a dashboard does not page through everyone to count. */
    public function statistics(): array
    {
        $perStatus = Mahasiswa::query()
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return [
            'data' => [
                'total' => (int) $perStatus->sum(),
                'per_status' => collect(StudentStatus::cases())
                    ->mapWithKeys(fn (StudentStatus $s): array => [
                        $s->value => (int) ($perStatus[$s->value] ?? 0),
                    ])
                    ->all(),
                'per_prodi' => Prodi::query()
                    ->withCount(['mahasiswa as aktif' => fn ($q) => $q->where('status', StudentStatus::Aktif->value)])
                    ->get()
                    ->mapWithKeys(fn ($p): array => [$p->kode => $p->aktif])
                    ->all(),
            ],
        ];
    }
}
