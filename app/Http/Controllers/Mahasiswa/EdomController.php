<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Edom\EdomPeriode;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Edom\EdomService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The student's side of the teaching evaluation.
 *
 * The screen states plainly that answers cannot be traced back, because a
 * student who does not believe that writes what they think is safe rather than
 * what they think — and the whole instrument then measures caution.
 */
class EdomController extends Controller
{
    public function __construct(private readonly EdomService $edom) {}

    public function index(): View
    {
        $mahasiswa = $this->mahasiswa();
        $periode = EdomPeriode::berjalan();

        return view('mahasiswa.edom', [
            'judul' => 'Evaluasi Dosen (EDOM)',
            'konteks' => $periode?->tahunAkademik->nama ?? 'Belum dibuka',
            'breadcrumb' => ['Portal' => route('mahasiswa.dashboard'), 'EDOM'],
            'periode' => $periode,
            'terbuka' => $periode?->terbuka() ?? false,
            'tertunda' => $periode === null ? collect() : $this->edom->tertunda($periode, $mahasiswa),
            'pertanyaan' => $periode?->pertanyaan ?? collect(),
            'gerbang' => config('edom.gerbang'),
            'kebijakanKomentar' => config('edom.komentar'),
        ]);
    }

    public function kirim(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa();
        $periode = EdomPeriode::berjalan();

        abort_if($periode === null, 404, 'Tidak ada periode EDOM yang berjalan.');

        $data = $request->validate([
            'kelas_kuliah_id' => ['required', 'integer', Rule::exists('kelas_kuliah', 'id')],
            'dosen_id' => ['required', 'integer', Rule::exists('dosen', 'id')],
            'jawaban' => ['required', 'array', 'min:1'],
            'jawaban.*.pertanyaan_id' => ['required', 'integer'],
            'jawaban.*.nilai' => ['nullable', 'integer', 'min:1', 'max:5'],
            'jawaban.*.teks' => ['nullable', 'string', 'max:2000'],
        ]);

        // Membership and teaching are re-checked inside the service against the
        // signed-in student, so an edited identifier reaches a refusal rather
        // than somebody else's lecturer.
        $this->edom->isi(
            $periode,
            $mahasiswa,
            KelasKuliah::findOrFail($data['kelas_kuliah_id']),
            Dosen::findOrFail($data['dosen_id']),
            $data['jawaban'],
        );

        return back()->with('sukses',
            'Terima kasih. Jawaban Anda tersimpan tanpa identitas dan tidak dapat ditelusuri kembali.');
    }

    private function mahasiswa(): Mahasiswa
    {
        $mahasiswa = Portal::user();

        abort_unless($mahasiswa instanceof Mahasiswa, 403);

        return $mahasiswa;
    }
}
