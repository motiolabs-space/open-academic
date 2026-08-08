<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Enums\EducationLevel;
use App\Enums\JabatanFungsional;
use App\Enums\JenisLuaran;
use App\Enums\JenisSertifikasi;
use App\Enums\LecturerAssignmentType;
use App\Enums\PeranKegiatan;
use App\Enums\TingkatKegiatan;
use App\Enums\UnsurBkd;
use App\Http\Controllers\Controller;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\RiwayatPendidikanDosen;
use App\Models\Sdm\SertifikasiDosen;
use App\Services\Berkas\BerkasService;
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

    public function simpanKegiatan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::enum(LecturerAssignmentType::class)],
            'unsur' => ['required', Rule::enum(UnsurBkd::class), 'not_in:'.UnsurBkd::Pendidikan->value],
            'judul' => ['required', 'string', 'max:255'],
            'peran' => ['nullable', Rule::enum(PeranKegiatan::class)],
            'tingkat' => ['nullable', Rule::enum(TingkatKegiatan::class)],
            'mitra_nama' => ['nullable', 'string', 'max:255'],
            'mitra_jenis' => ['nullable', 'string', 'max:48'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'sks_ekuivalen' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'luaran_jenis' => ['nullable', Rule::enum(JenisLuaran::class)],
            'luaran_identitas' => ['nullable', 'string', 'max:255'],
            'luaran_tahun' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'dokumen' => $this->berkas->aturan('dokumen', wajib: false),
        ]);

        $dosen = $this->dosen();

        PenugasanDosen::create([
            'dosen_id' => $dosen->id,
            'tahun_akademik_id' => Portal::term()->id,
            ...collect($data)->except('dokumen')->all(),
            'dokumen_path' => $request->hasFile('dokumen')
                ? $this->berkas->simpan($request->file('dokumen'), 'portofolio/'.$dosen->uuid)
                : null,

            /*
             * Never verified on creation, whoever is signed in.
             *
             * This row becomes a BKD line and IKU evidence. A self-reported
             * activity that arrives pre-verified is an indicator resting on an
             * unchecked claim, which is the one thing the verification screen
             * exists to prevent.
             */
            'is_verified' => false,
        ]);

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
