<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\DTOs\Sdm\KegiatanDosenData;
use App\Enums\BentukKeterlibatanKebijakan;
use App\Enums\EducationLevel;
use App\Enums\IndeksPublikasi;
use App\Enums\JabatanFungsional;
use App\Enums\JenisBuktiKebijakan;
use App\Enums\JenisLuaran;
use App\Enums\JenisSertifikasi;
use App\Enums\KuartilPublikasi;
use App\Enums\LecturerAssignmentType;
use App\Enums\PeranKegiatan;
use App\Enums\TingkatKebijakan;
use App\Enums\TingkatKegiatan;
use App\Enums\UnsurBkd;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\CatatKegiatanRequest;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\RiwayatPendidikanDosen;
use App\Models\Sdm\SertifikasiDosen;
use App\Services\Berkas\BerkasService;
use App\Services\Sdm\AngkaKreditService;
use App\Services\Sdm\PenugasanDosenService;
use App\Services\Sdm\PortofolioService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A lecturer's own record: degrees, ranks, certificates, and activities.
 *
 * Everything here is scoped to the signed-in lecturer with no identifier in any
 * URL, so there is no shape of request that writes to a colleague's file.
 *
 * The activities section is the one that earns its place. Research, community
 * service, and supporting duties never pass through a SIAKAD, so without a
 * screen to record them a BKD report can only ever show teaching — which is
 * precisely the report that fails.
 */
class PortofolioController extends Controller
{
    public function __construct(
        private readonly PortofolioService $portofolio,
        private readonly BerkasService $berkas,
        private readonly AngkaKreditService $angkaKredit,
        private readonly PenugasanDosenService $kegiatan,
    ) {}

    public function index(): View
    {
        $dosen = $this->dosen();
        $term = Portal::term();

        return view('dosen.portofolio', [
            'judul' => 'Portofolio & Kepegawaian',
            'konteks' => $dosen->namaLengkap(),
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), 'Portofolio'],

            'dosen' => $dosen,
            'term' => $term,

            'pendidikan' => $dosen->riwayatPendidikan()->get(),
            'jabatan' => $dosen->riwayatJabatan()->get(),
            'sertifikasi' => $dosen->sertifikasi()->get(),

            'kegiatan' => PenugasanDosen::query()
                ->where('dosen_id', $dosen->id)
                ->where('tahun_akademik_id', $term->id)
                ->orderByDesc('tanggal_mulai')
                ->get(),

            'jenjangPilihan' => EducationLevel::options(),
            'jabatanPilihan' => JabatanFungsional::options(),
            'sertifikasiPilihan' => JenisSertifikasi::options(),
            'jenisPilihan' => LecturerAssignmentType::options(),

            // Teaching is derived from the class list, so offering it here would
            // only invite the same class to be counted twice.
            'unsurPilihan' => collect(UnsurBkd::options())
                ->reject(fn ($label, $nilai): bool => $nilai === UnsurBkd::Pendidikan->value)
                ->all(),

            'peranPilihan' => PeranKegiatan::options(),
            'tingkatPilihan' => TingkatKegiatan::options(),
            'luaranPilihan' => JenisLuaran::options(),
            'luaranPublikasi' => collect(JenisLuaran::cases())
                ->filter(fn (JenisLuaran $j): bool => $j->publikasi())
                ->map(fn (JenisLuaran $j): string => $j->value)
                ->values()
                ->all(),
            'indeksPilihan' => IndeksPublikasi::options(),
            'kuartilPilihan' => KuartilPublikasi::options(),
            'kebijakanBentukPilihan' => BentukKeterlibatanKebijakan::options(),
            'kebijakanTingkatPilihan' => TingkatKebijakan::options(),
            'kebijakanBuktiPilihan' => JenisBuktiKebijakan::options(),
        ]);
    }

    public function simpanPendidikan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jenjang' => ['required', Rule::enum(EducationLevel::class)],
            'perguruan_tinggi' => ['required', 'string', 'max:255'],
            'program_studi' => ['nullable', 'string', 'max:255'],
            'bidang_ilmu' => ['nullable', 'string', 'max:255'],
            'negara' => ['required', 'string', 'max:64'],
            'tahun_masuk' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'tahun_lulus' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'gelar' => ['nullable', 'string', 'max:32'],
            'nomor_ijazah' => ['nullable', 'string', 'max:64'],
            'dokumen' => $this->berkas->aturan('dokumen', wajib: false),
        ]);

        $dosen = $this->dosen();

        RiwayatPendidikanDosen::create([
            'dosen_id' => $dosen->id,
            ...collect($data)->except('dokumen')->all(),
            'dokumen_path' => $request->hasFile('dokumen')
                ? $this->berkas->simpan($request->file('dokumen'), 'portofolio/'.$dosen->uuid)
                : null,
        ]);

        // A master's or doctorate is Kum A on the credit ledger.
        $this->angkaKredit->catatPendidikan($dosen);

        return back()->with('sukses', 'Riwayat pendidikan tersimpan.');
    }

    public function simpanJabatan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jabatan' => ['required', Rule::enum(JabatanFungsional::class)],
            'tmt' => ['required', 'date'],
            'angka_kredit' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'nomor_sk' => ['nullable', 'string', 'max:96'],
            'tanggal_sk' => ['nullable', 'date'],
            'berlaku' => ['nullable', 'boolean'],
            'dokumen' => $this->berkas->aturan('dokumen', wajib: false),
        ]);

        $dosen = $this->dosen();

        $this->portofolio->catatJabatan(
            $dosen,
            JabatanFungsional::from($data['jabatan']),
            $data['tmt'],
            (float) ($data['angka_kredit'] ?? 0),
            $data['nomor_sk'] ?? null,
            $data['tanggal_sk'] ?? null,
            $request->hasFile('dokumen')
                ? $this->berkas->simpan($request->file('dokumen'), 'portofolio/'.$dosen->uuid)
                : null,
            (bool) ($data['berlaku'] ?? true),
        );

        return back()->with('sukses', 'Jabatan fungsional tersimpan.');
    }

    public function simpanSertifikasi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::enum(JenisSertifikasi::class)],
            'nama' => ['required', 'string', 'max:255'],
            'nomor' => ['nullable', 'string', 'max:96'],
            'penyelenggara' => ['nullable', 'string', 'max:255'],
            'bidang' => ['nullable', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after:tanggal'],
            'dokumen' => $this->berkas->aturan('dokumen', wajib: false),
        ]);

        $dosen = $this->dosen();

        SertifikasiDosen::create([
            'dosen_id' => $dosen->id,
            ...collect($data)->except('dokumen')->all(),
            'dokumen_path' => $request->hasFile('dokumen')
                ? $this->berkas->simpan($request->file('dokumen'), 'portofolio/'.$dosen->uuid)
                : null,
        ]);

        return back()->with('sukses', 'Sertifikasi tersimpan.');
    }

    public function simpanKegiatan(CatatKegiatanRequest $request): RedirectResponse
    {
        $this->kegiatan->catat(
            $this->dosen(),
            Portal::term(),
            KegiatanDosenData::fromRequest($request),
            $request->file('dokumen'),
        );

        return back()->with('sukses',
            'Kegiatan tercatat. Unggah bukti bila belum, lalu ajukan BKD bila semester ini sudah lengkap.');
    }

    private function dosen(): Dosen
    {
        $dosen = Portal::user();

        abort_unless($dosen instanceof Dosen, 403);

        return $dosen;
    }
}
