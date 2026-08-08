<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JenisBeasiswa;
use App\Enums\StatusPenerima;
use App\Http\Controllers\Controller;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\BeasiswaPenerima;
use App\Services\Keuangan\BeasiswaService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Scholarship schemes and their recipients.
 *
 * The quota is shown beside every scheme rather than enforced only on submit: a
 * scheme funded for twenty students that already has twenty is a fact the person
 * allocating needs while they are choosing, not after they are refused.
 */
class BeasiswaController extends Controller
{
    public function __construct(private readonly BeasiswaService $beasiswa) {}

    public function index(Request $request): View
    {
        $this->izin('keuangan.view');

        $skema = Beasiswa::query()
            ->withCount(['penerima as penerima_aktif_count' => fn ($q) => $q
                ->where('status', StatusPenerima::Aktif->value)])
            ->orderBy('nama')
            ->get();

        return view('admin.beasiswa', [
            'judul' => 'Beasiswa & Keringanan',
            'konteks' => $skema->sum('penerima_aktif_count').' penerima aktif',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Beasiswa'],
            'skema' => $skema,
            'penerima' => BeasiswaPenerima::query()
                ->with(['beasiswa', 'mahasiswa.prodi', 'mulai', 'selesai', 'pemutus'])
                ->when($request->filled('skema'), fn ($q) => $q->where('beasiswa_id', $request->integer('skema')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'jenisPilihan' => JenisBeasiswa::options(),
            'statusPilihan' => StatusPenerima::options(),
            'daftarTerm' => TahunAkademik::terbaru()->get(['id', 'kode', 'nama']),
            'filter' => $request->only(['skema', 'status']),
        ]);
    }

    public function simpanSkema(Request $request): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $data = $request->validate([
            'kode' => ['required', 'string', 'max:32', Rule::unique('beasiswa', 'kode')->whereNull('deleted_at')],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::enum(JenisBeasiswa::class)],
            'penyandang' => ['nullable', 'string', 'max:255'],
            'persen' => ['nullable', 'integer', 'min:1', 'max:100'],
            'nominal' => ['nullable', 'integer', 'min:1'],
            'komponen' => ['nullable', 'string', 'max:255'],
            'kuota' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        /*
         * Exactly one way of expressing coverage.
         *
         * Both set is ambiguous and neither set covers nothing — either would
         * produce a scheme that silently discounts the wrong amount, and the
         * totals would still balance.
         */
        if (filled($data['persen'] ?? null) === filled($data['nominal'] ?? null)) {
            return back()->withInput()->with('galat',
                'Isi salah satu saja: persentase atau nominal tetap. Skema tanpa keduanya tidak '
                    .'memotong apa pun, dan dengan keduanya tidak jelas mana yang berlaku.');
        }

        $jenis = JenisBeasiswa::from($data['jenis']);

        if ($jenis->perluPenyandang() && blank($data['penyandang'] ?? null)) {
            return back()->withInput()->with('galat',
                'Beasiswa eksternal wajib menyebutkan penyandang dananya — itulah pihak yang '
                    .'menanggung potongannya.');
        }

        Beasiswa::create([
            ...$data,
            'komponen' => filled($data['komponen'] ?? null)
                ? array_values(array_filter(array_map('trim', explode(',', $data['komponen']))))
                : null,
            'is_active' => true,
        ]);

        return back()->with('sukses', 'Skema beasiswa dibuat.');
    }

    public function tetapkan(Request $request, Beasiswa $beasiswa): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $data = $request->validate([
            'mahasiswa_id' => ['required', 'integer', Rule::exists('mahasiswa', 'id')],
            'tahun_akademik_mulai_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'tahun_akademik_selesai_id' => ['nullable', 'integer', Rule::exists('tahun_akademik', 'id')],
            'nomor_sk' => ['nullable', 'string', 'max:64'],
        ]);

        $this->beasiswa->tetapkan(
            $beasiswa,
            Mahasiswa::findOrFail($data['mahasiswa_id']),
            TahunAkademik::findOrFail($data['tahun_akademik_mulai_id']),
            isset($data['tahun_akademik_selesai_id'])
                ? TahunAkademik::find($data['tahun_akademik_selesai_id'])
                : null,
            $data['nomor_sk'] ?? null,
            Portal::user(),
        );

        return back()->with('sukses',
            'Beasiswa ditetapkan dan langsung diterapkan ke tagihan semester yang dicakup.');
    }

    public function cabut(Request $request, BeasiswaPenerima $penerima): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        $this->beasiswa->cabut($penerima, Portal::user(), $data['alasan']);

        return back()->with('sukses',
            'Beasiswa dicabut. Tagihan yang sudah dipotong tidak dibongkar — batalkan barisnya '
                .'satu per satu bila memang harus.');
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
