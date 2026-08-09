<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPoin;
use App\Http\Controllers\Controller;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\PoinKemahasiswaanService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The catalogue, the queue of unverified claims, and one student's record.
 *
 * The two ledgers are always presented side by side and never summed. See
 * PoinKemahasiswaanService for why there is no combined figure to present.
 */
class PoinKemahasiswaanController extends Controller
{
    public function __construct(private readonly PoinKemahasiswaanService $poin) {}

    public function index(Request $request): View
    {
        $this->izin('mahasiswa.view');

        $mahasiswa = $request->filled('nim')
            ? Mahasiswa::where('nim', $request->string('nim'))->with('prodi')->first()
            : null;

        return view('admin.poin-kemahasiswaan', [
            'judul' => 'Poin Kemahasiswaan',
            'konteks' => 'Prestasi & pelanggaran',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Poin Kemahasiswaan'],

            'antrean' => $this->poin->antrean(),
            'kategori' => PoinKategori::orderBy('jenis')->orderBy('kode')->get(),
            'jenisOptions' => JenisPoin::options(),
            'tingkatOptions' => (array) config('kemahasiswaan.tingkat'),
            'daftarTerm' => TahunAkademik::orderByDesc('kode')->get(),

            'mahasiswa' => $mahasiswa,
            'rekap' => $mahasiswa ? $this->poin->rekap($mahasiswa) : null,
            'riwayat' => $mahasiswa ? $this->poin->riwayat($mahasiswa) : collect(),
        ]);
    }

    public function simpanKategori(Request $request): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $data = $request->validate([
            'kode' => ['required', 'string', 'max:24', 'unique:poin_kategori,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'string', 'in:'.implode(',', array_keys(JenisPoin::options()))],
            'tingkat' => ['nullable', 'string', 'in:'.implode(',', array_keys((array) config('kemahasiswaan.tingkat')))],
            'poin' => ['required', 'integer', 'min:1', 'max:9999'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'wajib_bukti' => ['nullable', 'boolean'],
        ]);

        PoinKategori::create([...$data, 'wajib_bukti' => (bool) ($data['wajib_bukti'] ?? false)]);

        return back()->with('sukses', 'Kategori poin ditambahkan.');
    }

    public function catat(Request $request): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $data = $request->validate([
            'nim' => ['required', 'string'],
            'poin_kategori_id' => ['required', 'integer'],
            'tahun_akademik_id' => ['nullable', 'integer'],
            'tanggal' => ['required', 'date'],
            'judul' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'bukti_path' => ['nullable', 'string', 'max:255'],
        ]);

        $mahasiswa = Mahasiswa::where('nim', $data['nim'])->first();

        if ($mahasiswa === null) {
            return back()->with('galat', 'Mahasiswa dengan NIM tersebut tidak ditemukan.');
        }

        $kategori = PoinKategori::findOrFail($data['poin_kategori_id']);

        $this->poin->catat($mahasiswa, $kategori, $data, $this->staf());

        return back()->with('sukses', sprintf(
            'Catatan %s untuk %s disimpan dan menunggu verifikasi.',
            $kategori->jenis->label(),
            $mahasiswa->nama,
        ));
    }

    public function verifikasi(PoinMahasiswa $poin): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $this->poin->verifikasi($poin, $this->staf());

        return back()->with('sukses', 'Catatan diverifikasi dan kini terhitung.');
    }

    public function tolak(Request $request, PoinMahasiswa $poin): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        $this->poin->tolak($poin, $this->staf(), $data['alasan']);

        return back()->with('sukses', 'Catatan ditolak. Barisnya tetap disimpan beserta alasannya.');
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
