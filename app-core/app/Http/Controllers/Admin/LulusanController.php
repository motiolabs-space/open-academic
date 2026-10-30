<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Kemahasiswaan\FilterLulusan;
use App\DTOs\Kemahasiswaan\HasilImporStatus;
use App\DTOs\Kemahasiswaan\KontakLulusanData;
use App\Enums\SumberStatusPekerjaan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kemahasiswaan\EksporLulusanRequest;
use App\Http\Requests\Kemahasiswaan\FilterLulusanRequest;
use App\Http\Requests\Kemahasiswaan\ImporStatusPekerjaanRequest;
use App\Http\Requests\Kemahasiswaan\KontakLulusanRequest;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Alumni;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\DataLulusanService;
use App\Services\Kemahasiswaan\ImporStatusPekerjaan;
use App\Support\EksporTabel;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Graduates for the national tracer study: their contacts, the list, and the
 * employment status read back (ADR-037 §2). No questionnaire lives here.
 *
 * An import is two steps. The upload is checked and kept in private storage
 * under a token held in this staff member's session; nothing is written to
 * the alumni rows until the same person applies it from the report.
 */
class LulusanController extends Controller
{
    private const SESI_IMPOR = 'impor_status_pekerjaan';

    private const FOLDER_IMPOR = 'impor-status-pekerjaan';

    public function __construct(
        private readonly DataLulusanService $lulusan,
        private readonly ImporStatusPekerjaan $impor,
    ) {}

    public function index(FilterLulusanRequest $request): View
    {
        $filter = FilterLulusan::fromRequest($request);
        $daftar = $this->lulusan->daftar($filter);
        $aktor = Portal::user();

        return view('admin.lulusan', [
            'judul' => __('lulusan.judul'),
            'konteks' => __('lulusan.konteks'),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('lulusan.judul')],

            'filter' => $filter,
            'daftar' => $daftar,
            'daftarTahun' => $this->lulusan->tahunLulus(),
            'daftarProdi' => Prodi::query()->orderBy('nama')->get(['id', 'kode', 'nama', 'jenjang']),
            'sumberOptions' => SumberStatusPekerjaan::options(),
            'formatOptions' => EksporTabel::FORMAT,
            'pratinjau' => $this->pratinjau(),

            'bolehKelola' => Gate::forUser($aktor)->allows('kelolaKontak', Alumni::class),
            'bolehEkspor' => Gate::forUser($aktor)->allows('ekspor', Alumni::class),
            'bolehImpor' => Gate::forUser($aktor)->allows('imporStatus', Alumni::class),
        ]);
    }

    public function ekspor(EksporLulusanRequest $request): StreamedResponse|BinaryFileResponse
    {
        return $this->lulusan->ekspor(
            FilterLulusan::fromRequest($request),
            (string) $request->string('format'),
            $this->staf(),
            trim((string) $request->string('keperluan')),
        );
    }

    public function kontak(KontakLulusanRequest $request, Alumni $alumni): RedirectResponse
    {
        $alumni = $this->lulusan->verifikasiKontak($alumni, KontakLulusanData::fromRequest($request), $this->staf());

        return back()->with('sukses', __('lulusan.verifikasi.tersimpan', ['nama' => $alumni->mahasiswa->nama]));
    }

    public function periksaImpor(ImporStatusPekerjaanRequest $request): RedirectResponse
    {
        $this->buangPratinjau();

        $token = (string) Str::uuid();
        Storage::disk('local')->put(self::FOLDER_IMPOR."/{$token}.csv", (string) $request->file('berkas')->get());

        $request->session()->put(self::SESI_IMPOR, ['token' => $token, 'sumber' => $request->sumber()->value]);

        return back();
    }

    public function terapkanImpor(): RedirectResponse
    {
        $this->authorize('imporStatus', Alumni::class);

        $sesi = session(self::SESI_IMPOR);
        $isi = $this->isiPratinjau();

        if ($isi === null) {
            return back()->with('galat', __('lulusan.impor.kedaluwarsa'));
        }

        $sumber = SumberStatusPekerjaan::from($sesi['sumber']);
        $hasil = $this->impor->terapkan($isi, $sumber);

        $this->buangPratinjau();

        return back()->with('sukses', __('lulusan.impor.diterapkan', [
            'jumlah' => $hasil->diterapkan,
            'sumber' => $sumber->label(),
        ]));
    }

    public function batalImpor(): RedirectResponse
    {
        $this->authorize('imporStatus', Alumni::class);
        $this->buangPratinjau();

        return back();
    }

    /** The checked-but-not-applied upload of this staff member, if any. */
    private function pratinjau(): ?HasilImporStatus
    {
        $isi = $this->isiPratinjau();

        return $isi === null ? null : $this->impor->periksa($isi, SumberStatusPekerjaan::from(session(self::SESI_IMPOR)['sumber']));
    }

    private function isiPratinjau(): ?string
    {
        $sesi = session(self::SESI_IMPOR);

        if (!is_array($sesi) || !Str::isUuid($sesi['token'] ?? null)) {
            return null;
        }

        $path = self::FOLDER_IMPOR."/{$sesi['token']}.csv";

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->get($path) : null;
    }

    private function buangPratinjau(): void
    {
        $sesi = session()->pull(self::SESI_IMPOR);

        if (is_array($sesi) && Str::isUuid($sesi['token'] ?? null)) {
            Storage::disk('local')->delete(self::FOLDER_IMPOR."/{$sesi['token']}.csv");
        }
    }

    private function staf(): Staff
    {
        $staf = Portal::user();

        abort_unless($staf instanceof Staff, 403);

        return $staf;
    }
}
