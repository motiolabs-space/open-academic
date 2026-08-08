<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student master list.
 *
 * The PDDIKTI readiness column is the point of this screen: an operator should
 * be able to see, before a reporting deadline, which records would be rejected
 * by Neo Feeder — not discover it mid-sync.
 */
class MahasiswaController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Mahasiswa::class);

        $mahasiswa = Mahasiswa::query()
            ->with(['prodi', 'dosenWali'])
            ->cari($request->string('cari'), ['nama', 'nim'])
            ->when($request->string('prodi')->toString(), fn ($q, string $prodi) => $q->where('prodi_id', $prodi))
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status))
            ->orderBy('nim')
            ->paginate(25)
            ->withQueryString();

        return view('admin.mahasiswa', [
            'judul' => 'Data Mahasiswa',
            'konteks' => $mahasiswa->total().' mahasiswa terdaftar',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Data Mahasiswa'],
            'mahasiswa' => $mahasiswa,
            'daftarProdi' => Prodi::orderBy('nama')->get(),
            'daftarStatus' => StudentStatus::options(),
            'filter' => $request->only(['cari', 'prodi', 'status']),
        ]);
    }
}
