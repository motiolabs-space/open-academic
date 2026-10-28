<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Akademik\BentrokJadwal;
use App\DTOs\Akademik\PenyusunanUjianData;
use App\Enums\JenisUjianKelas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Akademik\JadwalUjianRequest;
use App\Http\Requests\Akademik\SusunJadwalUjianRequest;
use App\Models\Akademik\JadwalUjian;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Akademik\JadwalUjianService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Arranging mid-term and final examinations, and publishing them.
 *
 * The list leads with the clashes, not the sessions: a timetable with one
 * student booked into two rooms looks exactly like a correct one until that
 * student is standing in a corridor.
 */
class JadwalUjianController extends Controller
{
    public function __construct(private readonly JadwalUjianService $ujian) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', JadwalUjian::class);

        $term = $this->term($request);
        $jenis = JenisUjianKelas::tryFrom($request->string('jenis')->toString());
        $prodiId = $request->filled('prodi') ? $request->integer('prodi') : null;

        $daftar = $this->ujian->daftar($term, $jenis, $prodiId);
        $bentrok = $this->ujian->laporanBentrok($term, $jenis, $prodiId);

        $penghalang = collect($bentrok)->flatten()->filter(fn (BentrokJadwal $b): bool => $b->menghalangi);

        return view('admin.jadwal-ujian.index', [
            'judul' => __('jadwal_ujian.judul'),
            'konteks' => __('jadwal_ujian.konteks', ['term' => $term->nama, 'jumlah' => $daftar->count()]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('jadwal_ujian.judul')],
            'term' => $term,
            'jenis' => $jenis,
            'prodiId' => $prodiId,
            'daftar' => $daftar,

            // The clash report needs every session of the term, so the list is
            // built whole; only its rendering is paged. Unpaged, a term's UTS
            // and UAS on a thousand-class campus is two thousand table rows.
            'halaman' => new LengthAwarePaginator(
                $daftar->forPage($nomor = Paginator::resolveCurrentPage(), 50)->values(),
                $daftar->count(),
                50,
                $nomor,
                ['path' => $request->url(), 'query' => $request->query()],
            ),
            'bentrok' => $bentrok,
            'jumlahPenghalang' => $penghalang->count(),
            'jumlahDraf' => $daftar->reject->sudahTerbit()->count(),
            'tanpaJadwal' => $jenis !== null ? $this->ujian->jumlahKelasTanpaJadwal($term, $jenis, $prodiId) : null,
            'daftarTerm' => TahunAkademik::terbaru()->pluck('nama', 'id'),
            'daftarProdi' => Prodi::orderBy('nama')->pluck('nama', 'id'),
            'jenisPilihan' => JenisUjianKelas::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', JadwalUjian::class);

        $term = $this->term($request);

        return $this->formulir(null, $term, $request->string('kelas')->toString());
    }

    public function store(JadwalUjianRequest $request): RedirectResponse
    {
        $kelas = $request->kelas();
        $hasil = $this->ujian->simpan($kelas, $request->isian());

        return $this->kembaliDenganHasil($hasil['jadwal'], $hasil['bentrok'], __('jadwal_ujian.sukses.simpan', [
            'jenis' => $hasil['jadwal']->jenis->singkatan(),
            'kelas' => $kelas->namaLengkap(),
        ]));
    }

    public function show(JadwalUjian $jadwal): View
    {
        $this->authorize('view', $jadwal);

        $jadwal->load(['kelasKuliah.mataKuliah', 'kelasKuliah.dosen', 'kelasKuliah.prodi', 'ruang.ruang', 'pengawas.dosen', 'pengawas.staff', 'pengawas.jadwalUjianRuang.ruang', 'tahunAkademik']);

        $bentrok = $this->ujian->laporanBentrok($jadwal->tahunAkademik)[$jadwal->id] ?? collect();

        return view('admin.jadwal-ujian.show', [
            'judul' => $jadwal->jenis->singkatan().' · '.$jadwal->kelasKuliah->mataKuliah->nama,
            'konteks' => $jadwal->kelasKuliah->namaLengkap().' · '.$jadwal->rentangWaktu(),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('jadwal_ujian.judul') => route('admin.jadwal-ujian.index', ['term' => $jadwal->tahun_akademik_id]),
                $jadwal->kelasKuliah->namaLengkap(),
            ],
            'jadwal' => $jadwal,
            'bentrok' => $bentrok,
            'peserta' => $this->ujian->peserta($jadwal),
            'tahanTunggakan' => (bool) config('dokumen.kartu_ujian.tahan_bila_menunggak'),
            'minimum' => (int) config('academic.attendance.min_percent_for_final_exam'),
        ]);
    }

    public function edit(JadwalUjian $jadwal): View
    {
        $this->authorize('update', $jadwal);

        return $this->formulir($jadwal, $jadwal->tahunAkademik, null);
    }

    public function update(JadwalUjianRequest $request, JadwalUjian $jadwal): RedirectResponse
    {
        $hasil = $this->ujian->perbarui($jadwal, $request->isian());

        return $this->kembaliDenganHasil($hasil['jadwal'], $hasil['bentrok'], __('jadwal_ujian.sukses.ubah', [
            'jenis' => $jadwal->jenis->singkatan(),
            'kelas' => $jadwal->kelasKuliah->namaLengkap(),
        ]));
    }

    public function destroy(JadwalUjian $jadwal): RedirectResponse
    {
        $this->authorize('delete', $jadwal);

        $term = $jadwal->tahun_akademik_id;
        $this->ujian->hapus($jadwal);

        return redirect()
            ->route('admin.jadwal-ujian.index', ['term' => $term])
            ->with('sukses', __('jadwal_ujian.sukses.hapus'));
    }

    public function terbitkan(Request $request): RedirectResponse
    {
        $this->authorize('publish', JadwalUjian::class);

        $validated = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', 'exists:tahun_akademik,id'],
            'jenis' => ['required', Rule::enum(JenisUjianKelas::class)],
            'prodi_id' => ['nullable', 'integer'],
        ]);

        $jumlah = $this->ujian->terbitkan(
            TahunAkademik::findOrFail($validated['tahun_akademik_id']),
            JenisUjianKelas::from($validated['jenis']),
            isset($validated['prodi_id']) ? (int) $validated['prodi_id'] : null,
        );

        return back()->with('sukses', __('jadwal_ujian.sukses.terbit', ['jumlah' => $jumlah]));
    }

    /** The bulk form, and — once its frame is filled in — the preview beneath it. */
    public function susun(Request $request): View
    {
        $this->authorize('create', JadwalUjian::class);

        $term = $this->term($request);

        return view('admin.jadwal-ujian.susun', $this->dataSusun($term) + [
            'parameter' => [
                'tahun_akademik_id' => $term->id,
                'jenis' => JenisUjianKelas::Uts->value,
                'prodi_id' => null,
                'tanggal_mulai' => null,
                'tanggal_selesai' => null,
                'sesi' => array_map(static fn (array $s): array => ['mulai' => $s[0], 'selesai' => $s[1]], PenyusunanUjianData::SESI_BAWAAN),
                'ruang_ids' => [],
                'sertakan_sabtu' => 0,
                'pengampu_mengawas' => 1,
            ],
            'rencana' => null,
        ]);
    }

    public function pratinjau(SusunJadwalUjianRequest $request): View
    {
        $data = $request->penyusunan();
        $term = TahunAkademik::findOrFail($data->tahunAkademikId);

        return view('admin.jadwal-ujian.susun', $this->dataSusun($term) + [
            'parameter' => $data->toArray(),
            'rencana' => $this->ujian->susun($data),
        ]);
    }

    public function simpanSusun(SusunJadwalUjianRequest $request): RedirectResponse
    {
        $data = $request->penyusunan();
        $tersimpan = $this->ujian->simpanRencana($data);

        return redirect()
            ->route('admin.jadwal-ujian.index', [
                'term' => $data->tahunAkademikId,
                'jenis' => $data->jenis->value,
                'prodi' => $data->prodiId,
            ])
            ->with('sukses', __('jadwal_ujian.sukses.susun', ['jumlah' => $tersimpan->count()]));
    }

    private function term(Request $request): TahunAkademik
    {
        return ($request->filled('term') ? TahunAkademik::find($request->integer('term')) : null)
            ?? Portal::term()
            ?? abort(404);
    }

    /** @param Collection<int, BentrokJadwal> $bentrok */
    private function kembaliDenganHasil(JadwalUjian $jadwal, Collection $bentrok, string $pesan): RedirectResponse
    {
        $redirect = redirect()->route('admin.jadwal-ujian.show', $jadwal);

        // A draft saved with clashes is allowed, but the clashes stay in view:
        // the registrar chose to save it, and that choice should stay visible.
        if ($bentrok->contains(fn (BentrokJadwal $b): bool => $b->menghalangi)) {
            return $redirect->with('peringatan', $pesan.' '.__('jadwal_ujian.keterangan.peringatan_bentrok', [
                'daftar' => $bentrok->pluck('pesan')->implode('; '),
            ]));
        }

        if ($bentrok->isNotEmpty()) {
            return $redirect->with('peringatan', $pesan.' '.$bentrok->pluck('pesan')->implode('; ').'.');
        }

        return $redirect->with('sukses', $pesan);
    }

    private function formulir(?JadwalUjian $jadwal, TahunAkademik $term, ?string $kelasUuid): View
    {
        $jadwal?->load(['kelasKuliah.mataKuliah', 'ruang', 'pengawas']);

        $pengawas = Dosen::query()->orderBy('nama')->get()->toBase()
            ->mapWithKeys(fn (Dosen $d): array => ['dosen:'.$d->id => $d->namaLengkap()])
            ->merge(Staff::query()->orderBy('nama')->get()->toBase()
                ->mapWithKeys(fn (Staff $s): array => ['staff:'.$s->id => $s->namaLengkap().' (staf)']));

        return view('admin.jadwal-ujian.form', [
            'judul' => $jadwal === null ? __('jadwal_ujian.judul_baru') : __('jadwal_ujian.judul_ubah'),
            'konteks' => $jadwal?->kelasKuliah->namaLengkap() ?? $term->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('jadwal_ujian.judul') => route('admin.jadwal-ujian.index', ['term' => $term->id]),
                $jadwal === null ? __('jadwal_ujian.judul_baru') : __('jadwal_ujian.judul_ubah'),
            ],
            'jadwal' => $jadwal,
            'term' => $term,
            'kelasTerpilih' => $kelasUuid,
            'daftarKelas' => $jadwal === null
                ? KelasKuliah::with('mataKuliah')->where('tahun_akademik_id', $term->id)->get()
                    ->sortBy(fn (KelasKuliah $k): string => (string) $k->mataKuliah?->kode)
                    ->mapWithKeys(fn (KelasKuliah $k): array => [$k->uuid => $k->mataKuliah?->kode.' · '.$k->namaLengkap()])
                : collect(),
            'daftarRuang' => Ruang::aktif()->orderBy('kode')->get()
                ->mapWithKeys(fn (Ruang $r): array => [$r->id => $r->kode.' · '.$r->nama.' ('.$r->kapasitas.')']),
            'daftarPengawas' => $pengawas,
            'jenisPilihan' => JenisUjianKelas::options(),
        ]);
    }

    /** @return array<string, mixed> */
    private function dataSusun(TahunAkademik $term): array
    {
        return [
            'judul' => __('jadwal_ujian.judul_susun'),
            'konteks' => $term->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('jadwal_ujian.judul') => route('admin.jadwal-ujian.index', ['term' => $term->id]),
                __('jadwal_ujian.judul_susun'),
            ],
            'term' => $term,
            'daftarTerm' => TahunAkademik::terbaru()->pluck('nama', 'id'),
            'daftarProdi' => Prodi::orderBy('nama')->pluck('nama', 'id'),
            'daftarRuang' => Ruang::aktif()->where('kapasitas', '>', 0)->orderBy('kode')->get(),
            'jenisPilihan' => JenisUjianKelas::options(),
        ];
    }
}
