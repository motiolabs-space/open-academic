<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tarif;
use App\Services\Keuangan\TarifResolver;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The fee matrix.
 *
 * Sparse and overlapping by design: one general row, then overrides for a
 * programme, an intake, an admission path or a UKT band. Which makes the matrix
 * genuinely hard to read — a row is only meaningful next to the rows it beats
 * or loses to.
 *
 * So the screen carries a simulator. Picking a real student shows exactly which
 * rows applied, which lost, and what the total comes to. A finance officer about
 * to bill five thousand people should not have to work that out in their head.
 */
class TarifController extends Controller
{
    /** UKT bands as Indonesian campuses number them. */
    public const GOLONGAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];

    private const KOMPONEN = [
        'ukt' => 'UKT',
        'spp' => 'SPP',
        'praktikum' => 'Praktikum',
        'registrasi' => 'Registrasi',
        'wisuda' => 'Wisuda',
        'lainnya' => 'Lainnya',
    ];

    public function __construct(private readonly TarifResolver $resolver) {}

    public function index(Request $request): View
    {
        $this->izin('keuangan.view');

        $daftar = Tarif::query()
            ->with('prodi')
            ->when($request->filled('komponen'), fn ($q) => $q->where('komponen', $request->string('komponen')))
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi_id', $request->integer('prodi')))
            ->orderBy('komponen')
            ->orderByDesc('prodi_id')
            ->orderByDesc('angkatan')
            ->orderBy('golongan_ukt')
            ->get();

        return view('admin.tarif', [
            'judul' => 'Matriks Tarif',
            'konteks' => $daftar->count().' baris tarif',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Matriks Tarif'],
            'daftar' => $daftar->groupBy('komponen'),
            'daftarProdi' => Prodi::orderBy('nama')->get(['id', 'nama', 'jenjang']),
            'daftarTerm' => TahunAkademik::terbaru()->get(['id', 'kode', 'nama']),
            'komponenPilihan' => self::KOMPONEN,
            'golonganPilihan' => array_combine(self::GOLONGAN, self::GOLONGAN),
            'filter' => $request->only(['komponen', 'prodi']),
            'simulasi' => $this->simulasi($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->izin('keuangan.manage');

        Tarif::create($this->validasi($request) + ['is_active' => true]);

        return back()->with('sukses', 'Baris tarif ditambahkan.');
    }

    public function perbarui(Request $request, Tarif $tarif): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $tarif->update($this->validasi($request));

        return back()->with('sukses', 'Baris tarif diperbarui.');
    }

    public function hapus(Tarif $tarif): RedirectResponse
    {
        $this->izin('keuangan.manage');

        // Soft delete keeps the row that a past invoice line points at; an
        // invoice whose tariff vanished cannot explain its own amount.
        $tarif->delete();

        return back()->with('sukses', 'Baris tarif dinonaktifkan. Tagihan yang sudah terbit tidak berubah.');
    }

    /**
     * What a real student would be billed, and why.
     *
     * @return array<string, mixed>|null
     */
    private function simulasi(Request $request): ?array
    {
        if (!$request->filled('simulasi_nim')) {
            return null;
        }

        $mahasiswa = Mahasiswa::where('nim', $request->string('simulasi_nim'))->first();

        if ($mahasiswa === null) {
            return ['mahasiswa' => null, 'nim' => (string) $request->string('simulasi_nim')];
        }

        $term = $request->filled('simulasi_term')
            ? TahunAkademik::find($request->integer('simulasi_term'))
            : Portal::term();

        if ($term === null) {
            return ['mahasiswa' => $mahasiswa, 'term' => null];
        }

        return [
            'mahasiswa' => $mahasiswa,
            'term' => $term,
            'rincian' => $this->resolver->rincian($mahasiswa, $term),
            'total' => $this->resolver->total($mahasiswa, $term),
        ];
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'komponen' => ['required', Rule::in(array_keys(self::KOMPONEN))],
            'nama' => ['required', 'string', 'max:255'],

            // Rupiah as an integer. No float ever touches money — a rounding
            // error of one cent across five thousand invoices is a real number.
            'nominal' => ['required', 'integer', 'min:0', 'max:1000000000'],

            // Every dimension is nullable, and null means "applies to all".
            'prodi_id' => ['nullable', 'integer', Rule::exists('prodi', 'id')],
            'angkatan' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'jalur_masuk' => ['nullable', 'string', 'max:48'],
            'golongan_ukt' => ['nullable', Rule::in(self::GOLONGAN)],

            'term_berlaku_dari' => ['nullable', 'string', 'size:5'],
            'term_berlaku_sampai' => ['nullable', 'string', 'size:5', 'gte:term_berlaku_dari'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'term_berlaku_sampai.gte' => 'Masa berlaku tidak boleh berakhir sebelum dimulai.',
            'golongan_ukt.in' => 'Golongan UKT ditulis dengan angka Romawi I sampai VIII.',
        ]);
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
