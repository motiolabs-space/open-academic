<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Services\Lkps\PerakitBorang;
use App\Support\Portal;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The accreditation figures, assembled per programme.
 *
 * Read-only by design. This screen reports what the semester already recorded;
 * anything that needs correcting is corrected where it was entered, not here.
 * A form that let an operator overwrite a computed figure would make the whole
 * thing a spreadsheet with extra steps.
 */
class LkpsController extends Controller
{
    public function __construct(private readonly PerakitBorang $perakit) {}

    public function index(Request $request): View
    {
        $this->izin('lkps.view');

        $prodi = $request->filled('prodi')
            ? Prodi::where('uuid', $request->string('prodi'))->firstOrFail()
            : Prodi::orderBy('kode')->firstOrFail();

        $term = $request->filled('semester')
            ? TahunAkademik::where('kode', $request->string('semester'))->firstOrFail()
            : Portal::term();

        return view('admin.lkps', [
            'judul' => 'Borang LKPS',
            'konteks' => $prodi->kode.' · '.$term->nama,
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Borang LKPS'],

            'prodi' => $prodi,
            'term' => $term,
            'daftarProdi' => Prodi::orderBy('kode')->get(),
            'semesterPilihan' => TahunAkademik::orderByDesc('kode')->pluck('nama', 'kode')->all(),

            'tabel' => $this->perakit->rakit($prodi, $term),

            // Ditampilkan di layar, bukan hanya di dokumentasi: angka di bawah
            // dihitung memakai definisi yang belum tentu disepakati kampus.
            'definisiSementara' => $this->definisiSementara(),
        ]);
    }

    /** One flat CSV of every filled table, for pasting into the form. */
    public function ekspor(Request $request): StreamedResponse
    {
        $this->izin('lkps.view');

        $prodi = $request->filled('prodi')
            ? Prodi::where('uuid', $request->string('prodi'))->firstOrFail()
            : Prodi::orderBy('kode')->firstOrFail();

        $term = $request->filled('semester')
            ? TahunAkademik::where('kode', $request->string('semester'))->firstOrFail()
            : Portal::term();

        $tabel = $this->perakit->rakit($prodi, $term);
        $pemisah = (string) config('bkd.ekspor.pemisah_csv');

        return response()->streamDownload(function () use ($tabel, $pemisah, $prodi, $term): void {
            $keluaran = fopen('php://output', 'wb');
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, ['Prodi', $prodi->kode.' '.$prodi->nama], $pemisah, '"', '\\');
            fputcsv($keluaran, ['Semester', $term->nama], $pemisah, '"', '\\');
            fputcsv($keluaran, [], $pemisah, '"', '\\');

            foreach ($tabel as $satu) {
                fputcsv($keluaran, array_filter([$satu['nomor'], $satu['judul']]), $pemisah, '"', '\\');

                /*
                 * A table this application cannot fill writes its reason into
                 * the file, in place of rows.
                 *
                 * Omitting it would be worse than useless: whoever pastes this
                 * into the form would find the group missing and assume it was
                 * not asked for.
                 */
                if (!$satu['terisi']) {
                    fputcsv($keluaran, ['TIDAK DIISI', $satu['alasan']], $pemisah, '"', '\\');
                    fputcsv($keluaran, [], $pemisah, '"', '\\');

                    continue;
                }

                fputcsv($keluaran, $satu['kolom'], $pemisah, '"', '\\');

                foreach ($satu['baris'] as $baris) {
                    fputcsv($keluaran, $baris, $pemisah, '"', '\\');
                }

                if ($satu['catatan'] !== null) {
                    fputcsv($keluaran, ['Catatan', $satu['catatan']], $pemisah, '"', '\\');
                }

                fputcsv($keluaran, [], $pemisah, '"', '\\');
            }

            fclose($keluaran);
        }, "lkps-{$prodi->kode}-{$term->kode}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The definitions these figures were computed with, in words.
     *
     * On the screen rather than in a document, because the person reading the
     * numbers is the person who needs to know which rule produced them — and
     * they will not open a file to find out.
     *
     * @return array<int, string>
     */
    private function definisiSementara(): array
    {
        $dtps = implode(', ', (array) config('lkps.dtps.status_kepegawaian'));
        $batas = (array) config('lkps.tepat_waktu.batas_semester');

        return [
            'Pendaftar dihitung sejak tahap "'.config('lkps.keketatan.pendaftar_sejak').'".',
            'DTPS: status kepegawaian '.$dtps
                .(config('lkps.dtps.sertakan_praktisi') ? ', termasuk praktisi.' : ', tanpa praktisi.'),
            'Mahasiswa aktif: status '.implode(', ', (array) config('lkps.mahasiswa_aktif.status')).'.',
            'Masa studi '.(config('lkps.masa_studi.kurangi_cuti') ? 'dikurangi' : 'tidak dikurangi').' semester cuti.',
            'Batas tepat waktu: '.collect($batas)->map(fn ($n, $j): string => "{$j} {$n} smt")->implode(', ').'.',
        ];
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
