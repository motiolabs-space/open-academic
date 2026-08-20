<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SumberRealisasi;
use App\Http\Controllers\Controller;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kinerja\PeriodeKinerja;
use App\Models\Kinerja\SasaranKinerja;
use App\Models\Kinerja\UkuranKinerja;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Kinerja\KinerjaService;
use App\Services\Kinerja\PengukurKinerja;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Rencana kinerja: periods, objectives cascaded down the org chart, measures.
 *
 * Deliberately *not* an IKU dashboard. See docs/KINERJA.md — the indicator
 * dashboard, accreditation forms and SPMI stay with Open Campus, and what lives
 * here is only what this application can measure from its own data.
 */
class KinerjaController extends Controller
{
    public function __construct(
        private readonly KinerjaService $kinerja,
        private readonly PengukurKinerja $pengukur,
    ) {}

    public function index(Request $request): View
    {
        $this->izin('pengaturan.view');

        $periode = $request->filled('periode')
            ? PeriodeKinerja::where('uuid', $request->string('periode'))->first()
            : PeriodeKinerja::orderByDesc('tahun')->first();

        $pohon = $periode ? $this->kinerja->pohonSasaran($periode) : collect();

        return view('admin.kinerja', [
            'judul' => 'Rencana Kinerja',
            'konteks' => $periode?->nama ?? 'Belum ada periode',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Rencana Kinerja'],

            'periode' => $periode,
            'daftarPeriode' => PeriodeKinerja::orderByDesc('tahun')->get(),
            'pohon' => $pohon,

            // Depth per objective, computed once here rather than walked per row
            // inside the view.
            'kedalaman' => $this->kedalaman($pohon),

            'unitAktif' => UnitKerja::aktif()->orderBy('kode')->get(),
            'indikator' => $this->pengukur->katalog(),
            'sumberOptions' => SumberRealisasi::options(),
            'daftarTerm' => TahunAkademik::orderByDesc('kode')->get(),
        ]);
    }

    public function simpanPeriode(Request $request): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tahun_akademik_id' => ['nullable', 'integer', 'exists:tahun_akademik,id'],
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date', 'after_or_equal:mulai'],
        ]);

        PeriodeKinerja::create($data);

        return back()->with('sukses', 'Periode kinerja dibuat sebagai draf.');
    }

    public function jalankan(PeriodeKinerja $periode): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $this->kinerja->jalankan($periode);

        return back()->with('sukses', 'Periode dijalankan. Capaian sudah dapat dicatat.');
    }

    /**
     * Locks the period. One way, and the screen says so before it happens.
     */
    public function kunci(PeriodeKinerja $periode): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $this->kinerja->kunci($periode, $this->staf());

        return back()->with('sukses', 'Periode dikunci. Target dan realisasinya dibekukan permanen.');
    }

    public function ukurOtomatis(PeriodeKinerja $periode): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $jumlah = $this->kinerja->ukurOtomatis($periode);

        return back()->with('sukses', sprintf('%d ukuran diukur ulang dari data.', $jumlah));
    }

    public function simpanSasaran(Request $request, PeriodeKinerja $periode): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate([
            'unit_kerja_id' => ['required', 'integer', 'exists:unit_kerja,id'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:sasaran_kinerja,id'],
        ]);

        $this->kinerja->buatSasaran($periode, UnitKerja::findOrFail($data['unit_kerja_id']), $data);

        return back()->with('sukses', 'Sasaran ditambahkan.');
    }

    public function simpanUkuran(Request $request, SasaranKinerja $sasaran): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'sumber_realisasi' => ['required', 'string', 'in:'.implode(',', array_keys(SumberRealisasi::options()))],
            'indikator_kunci' => ['nullable', 'string', 'max:64'],
            'satuan' => ['nullable', 'string', 'max:24'],
            /*
             * Dibatasi pada apa yang MUAT DI KOLOMNYA, bukan pada aturan bisnis
             * karangan.
             *
             * `target` dan `nilai` disimpan sebagai DECIMAL(12,2). MySQL di luar
             * mode ketat tidak menolak nilai yang melebihinya — ia memotongnya
             * diam-diam: target 1.000.000.000.000.000 tersimpan sebagai
             * 9.999.999.999,99. Operator mengetik satu angka, sistem menyimpan
             * angka lain, dan tidak ada yang memberitahu. Diuji langsung pada
             * MariaDB XAMPP; sql_mode di sana memang tanpa STRICT_TRANS_TABLES.
             *
             * Batas bawah 0 karena kedelapan indikator di config/kinerja.php
             * adalah cacahan, rerata, atau persentase — tak satu pun dapat
             * bernilai negatif. Ukuran bertanda perlu keputusan tersendiri,
             * bukan diselundupkan lewat validasi yang longgar.
             */
            'target' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'semakin_besar_semakin_baik' => ['nullable', 'boolean'],
        ]);

        $this->kinerja->tambahUkuran($sasaran, [
            ...$data,
            'semakin_besar_semakin_baik' => (bool) ($data['semakin_besar_semakin_baik'] ?? true),
        ]);

        return back()->with('sukses', 'Ukuran ditambahkan.');
    }

    public function catatCapaian(Request $request, UkuranKinerja $ukuran): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $request->validate([
            // Batas yang sama dengan `target` di atas, dan alasannya sama.
            'nilai' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'tanggal' => ['required', 'date'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->kinerja->catatCapaian(
            $ukuran,
            (float) $data['nilai'],
            $data['tanggal'],
            $this->staf(),
            $data['catatan'] ?? null,
        );

        return back()->with('sukses', 'Capaian dicatat.');
    }

    /**
     * Depth of each objective in the cascade, for indenting the list.
     *
     * @param Collection<int, SasaranKinerja> $pohon
     * @return array<int, int>
     */
    private function kedalaman($pohon): array
    {
        $hasil = [];

        foreach ($pohon as $sasaran) {
            $tingkat = 0;
            $kini = $sasaran;

            // Bounded by the node count: the cascade refuses cycles at write
            // time, but a view that trusts that is one bad row from hanging.
            for ($i = 0; $i < $pohon->count() && $kini?->parent_id !== null; $i++) {
                $kini = $pohon->firstWhere('id', $kini->parent_id);
                $tingkat++;
            }

            $hasil[$sasaran->id] = $tingkat;
        }

        return $hasil;
    }

    private function staf(): Staff
    {
        $staf = Portal::user();

        abort_unless($staf instanceof Staff, 403);

        return $staf;
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
