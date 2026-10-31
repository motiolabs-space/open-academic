<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JenisRegistrasiUlang;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\RegistrasiUlangRequest;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\RegistrasiUlangService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Re-NIM, readmission and reactivation, from the student's page.
 *
 * One form for the three kinds: the registrar picks what happened, and the
 * service decides whether it is allowed from the student's current status.
 */
class RegistrasiUlangController extends Controller
{
    public function __construct(private readonly RegistrasiUlangService $registrasi) {}

    public function create(Mahasiswa $mahasiswa): View
    {
        $this->authorize('registrasiUlang', $mahasiswa);

        $mahasiswa->load(['prodi', 'peristiwaRegistrasi.mahasiswaBaru', 'registrasiSebelumnya.mahasiswaLama']);

        return view('admin.registrasi-ulang', [
            'judul' => __('registrasi_ulang.judul'),
            'konteks' => $mahasiswa->nim.' · '.$mahasiswa->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Data Mahasiswa' => route('admin.mahasiswa'),
                $mahasiswa->nim => route('admin.mahasiswa.show', $mahasiswa),
                __('registrasi_ulang.judul'),
            ],
            'mahasiswa' => $mahasiswa,
            'jenisBoleh' => collect(JenisRegistrasiUlang::cases())
                ->filter(fn (JenisRegistrasiUlang $j): bool => in_array($mahasiswa->status, $j->statusAsal(), true))
                ->mapWithKeys(fn (JenisRegistrasiUlang $j): array => [$j->value => $j->label()])
                ->all(),
            'daftarProdi' => Prodi::query()->orderBy('nama')->get(['id', 'uuid', 'nama', 'jenjang']),
            'daftarTerm' => TahunAkademik::query()->orderByDesc('kode')->limit(8)->get(['id', 'uuid', 'kode', 'nama']),
            'termAktif' => Portal::term(),
        ]);
    }

    public function store(RegistrasiUlangRequest $request, Mahasiswa $mahasiswa): RedirectResponse
    {
        $staf = Portal::user();
        $hasil = $this->registrasi->catat($mahasiswa, $request->toData(), $staf instanceof Staff ? $staf : null);

        $kembali = redirect()->route('admin.mahasiswa.show', $hasil['mahasiswa']);

        if ($hasil['kata_sandi'] !== null) {
            // Shown once, like every other new account's temporary password.
            $kembali->with('kata_sandi_baru', [
                'nama' => $hasil['mahasiswa']->nama,
                'identitas' => $hasil['mahasiswa']->nim,
                'kata_sandi' => $hasil['kata_sandi'],
            ]);
        }

        return $kembali->with('sukses', __('registrasi_ulang.pesan.'.$hasil['riwayat']->jenis->value, [
            'lama' => $hasil['riwayat']->nim_lama,
            'baru' => $hasil['riwayat']->nim_baru ?? $hasil['riwayat']->nim_lama,
        ]));
    }
}
