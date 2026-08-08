<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Keuangan\Pembayaran;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Services\Keuangan\PembayaranService;
use App\Services\Keuangan\PenerbitanTagihanService;
use App\Services\Keuangan\PotonganService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The finance office's two jobs: billing a cohort, and reconciling what came
 * back.
 *
 * Issuing invoices is previewed before it runs. Several thousand invoices is
 * not an action anybody should discover the shape of by performing it.
 */
class KeuanganController extends Controller
{
    public function __construct(
        private readonly PenerbitanTagihanService $penerbitan,
        private readonly PembayaranService $pembayaran,
        private readonly PotonganService $potongan,
    ) {}

    /**
     * A one-off reduction on one invoice.
     *
     * Lives beside the payment actions because it is the same kind of decision:
     * somebody is changing what a student owes, and the record of who and why is
     * the whole point. Scholarships have their own screen; this is the
     * discretionary case that has no scheme behind it.
     */
    public function keringanan(Request $request, Tagihan $tagihan): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $data = $request->validate([
            'nominal' => ['required', 'integer', 'min:1'],
            'alasan' => ['required', 'string', 'max:500'],
        ], [
            'alasan.required' => 'Alasan keringanan wajib diisi — potongan tanpa alasan tertulis '
                .'tidak dapat dibedakan dari penyalahgunaan.',
        ]);

        $this->potongan->keringanan(
            $tagihan,
            (int) $data['nominal'],
            $data['alasan'],
            Portal::user(),
        );

        $lebih = $tagihan->fresh()->kelebihanBayar();

        return back()->with('sukses', $lebih > 0
            ? 'Keringanan dicatat. Pembayaran mahasiswa kini melebihi tagihan sebesar Rp'
                .number_format($lebih, 0, ',', '.').' — perlu dikembalikan atau dipindahkan.'
            : 'Keringanan dicatat dan tagihan diperbarui.');
    }

    public function hapusPotongan(Request $request, TagihanItem $item): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        $this->potongan->hapus($item, Portal::user(), $data['alasan']);

        return back()->with('sukses', 'Potongan dibatalkan dan nominalnya kembali ke tagihan.');
    }

    public function index(Request $request): View
    {
        $this->izin('keuangan.view');

        $term = Portal::term();

        $tagihan = Tagihan::query()
            ->with(['mahasiswa.prodi', 'tahunAkademik'])
            ->when($request->filled('term'), fn ($q) => $q->where('tahun_akademik_id', $request->integer('term')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->cari($request->string('cari'), ['nomor', 'mahasiswa.nama', 'mahasiswa.nim'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.keuangan', [
            'judul' => 'Tagihan & Rekonsiliasi',
            'konteks' => $term?->nama ?? 'Belum ada semester aktif',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Keuangan'],
            'tagihan' => $tagihan,
            'daftarTerm' => TahunAkademik::terbaru()->get(['id', 'kode', 'nama']),
            'daftarProdi' => Prodi::orderBy('nama')->get(['id', 'nama', 'jenjang']),
            'statusPilihan' => InvoiceStatus::cases(),
            'termAktif' => $term,
            'filter' => $request->only(['term', 'status', 'cari']),
            'pratinjau' => session('pratinjau'),

            // The headline numbers a finance office is actually asked for.
            'ringkas' => $this->ringkas($request->filled('term')
                ? TahunAkademik::find($request->integer('term'))
                : $term),

            'pembayaranTerbaru' => Pembayaran::query()
                ->with(['mahasiswa', 'tagihan'])
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    /** Shows what a bulk run would do. Nothing is written. */
    public function pratinjau(Request $request): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $validated = $this->validasiPenerbitan($request);

        $hasil = $this->penerbitan->pratinjau(
            TahunAkademik::findOrFail($validated['tahun_akademik_id']),
            $validated['angkatan'] ?? null,
            $validated['prodi_id'] ?? null,
        );

        return back()->with('pratinjau', $hasil + $validated);
    }

    public function terbitkan(Request $request): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $validated = $this->validasiPenerbitan($request);

        $hasil = $this->penerbitan->terbitkan(
            TahunAkademik::findOrFail($validated['tahun_akademik_id']),
            $validated['angkatan'] ?? null,
            $validated['prodi_id'] ?? null,
        );

        $pesan = sprintf(
            '%d tagihan diterbitkan senilai Rp%s. %d dilewati karena sudah ditagih.',
            $hasil['terbit'],
            number_format($hasil['total_rupiah'], 0, ',', '.'),
            $hasil['dilewati'],
        );

        if ($hasil['tanpa_tarif'] > 0) {
            // Never buried: these students end the semester owing nothing on
            // paper, and nobody notices until graduation.
            $pesan .= sprintf(
                ' %d mahasiswa TIDAK ditagih karena belum ada tarif yang cocok — periksa master tarif.',
                $hasil['tanpa_tarif'],
            );
        }

        return back()->with($hasil['tanpa_tarif'] > 0 ? 'peringatan' : 'sukses', $pesan);
    }

    public function catatPembayaran(Request $request, Tagihan $tagihan): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $validated = $request->validate([
            'nominal' => ['required', 'integer', 'min:1'],
            'channel' => ['required', Rule::in(['tunai', 'transfer', 'va', 'qris', 'lainnya'])],
            'referensi' => ['nullable', 'string', 'max:64'],
        ]);

        $this->pembayaran->catatManual(
            $tagihan,
            (int) $validated['nominal'],
            Portal::user(),
            $validated['channel'],
            $validated['referensi'] ?? null,
        );

        return back()->with('sukses', 'Pembayaran dicatat dan tagihan diperbarui.');
    }

    public function batalkanPembayaran(Request $request, Pembayaran $pembayaran): RedirectResponse
    {
        $this->izin('keuangan.manage');

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan.required' => 'Pembatalan pembayaran wajib disertai alasan yang tercatat.',
        ]);

        $this->pembayaran->batalkan($pembayaran, Portal::user(), $validated['alasan']);

        return back()->with('sukses', 'Pembayaran dibatalkan; catatannya tetap tersimpan pada jejak audit.');
    }

    /** @return array<string, int> */
    private function ringkas(?TahunAkademik $term): array
    {
        if ($term === null) {
            return ['tagihan' => 0, 'tertagih' => 0, 'terkumpul' => 0, 'tunggakan' => 0, 'belum_lunas' => 0];
        }

        $baris = Tagihan::query()
            ->where('tahun_akademik_id', $term->id)
            ->selectRaw('COUNT(*) as jumlah, SUM(total) as total, SUM(terbayar) as terbayar')
            ->first();

        return [
            'tagihan' => (int) ($baris->jumlah ?? 0),
            'tertagih' => (int) ($baris->total ?? 0),
            'terkumpul' => (int) ($baris->terbayar ?? 0),
            'tunggakan' => (int) ($baris->total ?? 0) - (int) ($baris->terbayar ?? 0),
            'belum_lunas' => Tagihan::where('tahun_akademik_id', $term->id)
                ->where('status', '!=', InvoiceStatus::Lunas->value)
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function validasiPenerbitan(Request $request): array
    {
        return $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'angkatan' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'prodi_id' => ['nullable', 'integer', Rule::exists('prodi', 'id')],
        ]);
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
