<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\KategoriEdom;
use App\Enums\TipeJawabanEdom;
use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Edom\EdomPartisipasi;
use App\Models\Edom\EdomPeriode;
use App\Models\Edom\EdomPertanyaan;
use App\Services\Edom\HasilEdom;
use App\Services\Edom\KelolaEdom;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Running the evaluation: the window, the questions, and the results.
 *
 * Reading is separated from running by permission — `edom.view` sees results,
 * `edom.manage` opens periods and writes questions. Leadership gets the first
 * and not the second, so nobody can reword the instrument they are measured by.
 */
class EdomController extends Controller
{
    public function __construct(
        private readonly KelolaEdom $kelola,
        private readonly HasilEdom $hasil,
    ) {}

    public function index(Request $request): View
    {
        $this->izin('edom.view');

        $periode = $request->filled('periode')
            ? EdomPeriode::with(['tahunAkademik', 'pertanyaan'])->where('uuid', $request->string('periode'))->firstOrFail()
            : EdomPeriode::with(['tahunAkademik', 'pertanyaan'])->orderByDesc('id')->first();

        return view('admin.edom', [
            'judul' => 'Evaluasi Dosen (EDOM)',
            'konteks' => $periode?->tahunAkademik->nama ?? 'Belum ada periode',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'EDOM'],

            'periode' => $periode,
            'semua' => EdomPeriode::with('tahunAkademik')->orderByDesc('id')->get(),
            'tahunPilihan' => TahunAkademik::orderByDesc('kode')->pluck('nama', 'id')->all(),
            'kategoriPilihan' => KategoriEdom::options(),
            'tipePilihan' => TipeJawabanEdom::options(),
            'bolehKelola' => Portal::user()?->hasPermissionTo('edom.manage', 'staff') ?? false,

            // Participation is the number an administrator acts on mid-window —
            // it is the only one that is complete before the threshold is met,
            // and it says whether a reminder is worth sending.
            'partisipasi' => $periode === null ? 0 : EdomPartisipasi::where('edom_periode_id', $periode->id)->count(),
            'ringkasan' => $periode === null ? collect() : $this->hasil->ringkasanDosen($periode),
            'perKelas' => $periode === null ? collect() : $this->hasil->partisipasiKelas($periode),
        ]);
    }

    /**
     * One class in detail, including comments where the campus routes them here.
     *
     * A separate screen rather than an expandable row, because opening free-text
     * answers is a deliberate act and should look like one.
     */
    public function kelas(Request $request, EdomPeriode $periode, KelasKuliah $kelas): View
    {
        $this->izin('edom.view');

        $dosen = $kelas->dosen()->findOrFail($request->integer('dosen'));

        return view('admin.edom-kelas', [
            'judul' => 'Hasil EDOM Kelas',
            'konteks' => $kelas->mataKuliah->nama.' · '.$dosen->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'EDOM' => route('admin.edom.index', ['periode' => $periode->uuid]),
                'Kelas',
            ],
            'periode' => $periode,
            'kelas' => $kelas,
            'dosen' => $dosen,

            // Comments reach this screen only when the campus has chosen to send
            // them to the programme rather than to the lecturer.
            'hasil' => $this->hasil->kelas(
                $periode,
                $kelas->id,
                $dosen,
                bolehKomentar: config('edom.komentar') === 'prodi',
            ),
            'kebijakanKomentar' => config('edom.komentar'),
        ]);
    }

    public function simpanPeriode(Request $request): RedirectResponse
    {
        $this->izin('edom.manage');

        $data = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'nama' => ['required', 'string', 'max:120'],
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date', 'after_or_equal:mulai'],
            'min_responden' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $periode = $this->kelola->buatPeriode(
            TahunAkademik::findOrFail($data['tahun_akademik_id']),
            $data['nama'],
            $data['mulai'],
            $data['selesai'],
            (int) $data['min_responden'],
        );

        return redirect()
            ->route('admin.edom.index', ['periode' => $periode->uuid])
            ->with('sukses', 'Periode dibuat. Tambahkan pertanyaan sebelum membukanya.');
    }

    public function ubahStatus(Request $request, EdomPeriode $periode): RedirectResponse
    {
        $this->izin('edom.manage');

        if ($request->boolean('aktif')) {
            $this->kelola->aktifkan($periode);

            return back()->with('sukses', 'Periode dibuka. Mahasiswa sudah dapat mengisi.');
        }

        $this->kelola->nonaktifkan($periode);

        return back()->with('sukses', 'Periode ditutup.');
    }

    public function tambahPertanyaan(Request $request, EdomPeriode $periode): RedirectResponse
    {
        $this->izin('edom.manage');

        $data = $request->validate([
            'kategori' => ['required', Rule::enum(KategoriEdom::class)],
            'teks' => ['required', 'string', 'max:500'],
            'tipe' => ['required', Rule::enum(TipeJawabanEdom::class)],
        ]);

        $this->kelola->tambahPertanyaan(
            $periode,
            KategoriEdom::from($data['kategori']),
            $data['teks'],
            TipeJawabanEdom::from($data['tipe']),
        );

        return back()->with('sukses', 'Pertanyaan ditambahkan.');
    }

    public function hapusPertanyaan(EdomPertanyaan $pertanyaan): RedirectResponse
    {
        $this->izin('edom.manage');

        $this->kelola->hapusPertanyaan($pertanyaan);

        return back()->with('sukses', 'Pertanyaan dihapus.');
    }

    public function salinPertanyaan(Request $request, EdomPeriode $periode): RedirectResponse
    {
        $this->izin('edom.manage');

        $data = $request->validate([
            'dari' => ['required', 'integer', Rule::exists('edom_periode', 'id')],
        ]);

        $jumlah = $this->kelola->salinPertanyaan(
            EdomPeriode::findOrFail($data['dari']),
            $periode,
        );

        return back()->with('sukses', $jumlah.' pertanyaan disalin.');
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
