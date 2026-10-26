<?php

declare(strict_types=1);

namespace App\Services\Pmb;

use App\DTOs\Pmb\BiodataPendaftarData;
use App\Enums\ApplicantStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Pmb\PmbAkun;
use App\Models\Pmb\PmbBerkas;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Notifications\Pmb\BerkasDikembalikan;
use App\Notifications\Pmb\SiapSeleksi;
use App\Services\Berkas\BerkasService;
use App\Services\Notifikasi\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * An application, from the first click to the selection queue.
 *
 * The applicant does the typing; this decides what they may type and when.
 * Two moments matter. **Submission** freezes the form: what the admissions
 * desk checks, and what PDDIKTI will eventually receive, must not shift
 * underneath them. **Readiness for selection** is reached by the campus, not
 * the applicant — the fee settled and every required document accepted — and
 * is the only path to an exam card.
 *
 * Every status move goes through pindahkan(), which refuses an edge
 * ApplicantStatus does not list and writes the move to the audit log.
 */
class PendaftaranPmbService
{
    /** Kinds an applicant may upload beyond the required list. */
    private const BERKAS_SUKARELA = ['bukti_bayar', 'lainnya'];

    /**
     * Biodata the form requires, keyed by column, valued by the label key.
     *
     * The FeederValidator errors (NIK, birth, sex) plus its two warnings
     * (mother's name, religion) — a warning there is a field nobody can
     * collect later without chasing the student, so here it is required —
     * plus the address MahasiswaMapper sends.
     */
    private const BIODATA_WAJIB = [
        'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama_kode', 'nama_ibu',
        'telepon', 'alamat', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi',
    ];

    public function __construct(
        private readonly BerkasService $berkas,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Opens a draft application in a wave.
     *
     * The wave row is locked for the duration so two submissions in the same
     * second cannot both take the last place or the same number.
     */
    public function mulai(PmbAkun $akun, PmbGelombang $gelombang, Prodi $pilihan1, ?Prodi $pilihan2 = null): PmbPendaftar
    {
        return DB::transaction(function () use ($akun, $gelombang, $pilihan1, $pilihan2): PmbPendaftar {
            $gelombang = PmbGelombang::whereKey($gelombang->id)->lockForUpdate()->firstOrFail();
            $gelombang->load('prodiDitawarkan');

            if (!$gelombang->terbukaDaring()) {
                throw new AturanAkademikException(__('pmb.validasi.gelombang_tutup'));
            }

            $tawaran = $gelombang->prodiDitawarkan->firstWhere('prodi_id', $pilihan1->id);

            if ($tawaran === null || ($pilihan2 !== null && !$gelombang->prodiDitawarkan->contains('prodi_id', $pilihan2->id))) {
                throw new AturanAkademikException(__('pmb.validasi.prodi_tidak_ditawarkan'));
            }

            if ($pilihan2 !== null && $pilihan2->id === $pilihan1->id) {
                throw new AturanAkademikException(__('pmb.validasi.pilihan_sama'));
            }

            $ada = PmbPendaftar::query()
                ->where('pmb_akun_id', $akun->id)
                ->where('pmb_gelombang_id', $gelombang->id)
                ->where('status', '!=', ApplicantStatus::Batal->value)
                ->first();

            if ($ada !== null) {
                throw new AturanAkademikException(__('pmb.validasi.sudah_terdaftar', ['nomor' => $ada->nomor_pendaftaran]));
            }

            $this->pastikanKuota($gelombang);

            return PmbPendaftar::create([
                'pmb_gelombang_id' => $gelombang->id,
                'pmb_akun_id' => $akun->id,
                'nomor_pendaftaran' => $this->nomorBerikutnya($gelombang),
                'nama' => $akun->nama,
                'email' => $akun->email,
                'prodi_pilihan_1_id' => $pilihan1->id,
                'prodi_pilihan_2_id' => $pilihan2?->id,
                'status' => ApplicantStatus::Draf,

                // Frozen now. Raising the wave's price next week must not
                // reprice an application somebody already started.
                'biaya_formulir' => $gelombang->biayaUntuk($tawaran),
            ]);
        });
    }

    public function simpanBiodata(PmbPendaftar $pendaftar, BiodataPendaftarData $data): PmbPendaftar
    {
        $this->pastikanDraf($pendaftar);

        $pendaftar->update($data->kolom());

        return $pendaftar->refresh();
    }

    /**
     * Stores one document, replacing an earlier upload of the same kind.
     *
     * Before submission anything not yet accepted may be replaced. After it,
     * only what the desk sent back — plus the transfer receipt while the fee
     * is open, which is the one document the applicant produces *after*
     * submitting.
     */
    public function unggahBerkas(PmbPendaftar $pendaftar, string $jenis, UploadedFile $unggahan): PmbBerkas
    {
        $pendaftar->loadMissing(['gelombang.prodiDitawarkan', 'berkas', 'pembayaran']);

        $boleh = [...$pendaftar->dokumenWajib(), ...self::BERKAS_SUKARELA];

        if (!in_array($jenis, $boleh, true)) {
            throw new AturanAkademikException(__('pmb.validasi.berkas_tidak_wajib'));
        }

        $lama = $pendaftar->berkas->firstWhere('jenis', $jenis);

        if ($lama?->is_verified) {
            throw new AturanAkademikException(__('pmb.validasi.berkas_terkunci'));
        }

        if (!$pendaftar->masihDraf()) {
            $bolehSesudahAjukan = ($lama?->dikembalikan() ?? false)
                || ($jenis === 'bukti_bayar' && !$pendaftar->formulirLunas() && $pendaftar->status !== ApplicantStatus::Batal);

            if (!$bolehSesudahAjukan) {
                throw new AturanAkademikException(__('pmb.validasi.bukan_draf'));
            }
        }

        // Stored before the transaction: a file written for a row that then
        // failed is an orphan on disk, which is cheaper than a row pointing at
        // a file that was never written.
        $path = $this->berkas->simpan($unggahan, 'pmb/'.$pendaftar->uuid);

        $baru = DB::transaction(function () use ($pendaftar, $jenis, $unggahan, $path, $lama): PmbBerkas {
            $lama?->delete();

            return PmbBerkas::create([
                'pmb_pendaftar_id' => $pendaftar->id,
                'jenis' => $jenis,
                'nama_file' => $unggahan->getClientOriginalName(),
                'file_path' => $path,
                'is_verified' => false,
            ]);
        });

        $this->berkas->hapus($lama?->file_path);

        return $baru;
    }

    public function hapusBerkas(PmbPendaftar $pendaftar, PmbBerkas $berkas): void
    {
        $this->pastikanDraf($pendaftar);

        if ($berkas->is_verified) {
            throw new AturanAkademikException(__('pmb.validasi.berkas_terkunci'));
        }

        $berkas->delete();
        $this->berkas->hapus($berkas->file_path);
    }

    /**
     * Columns the applicant still has to fill before submitting, as labels.
     *
     * @return array<int, string>
     */
    public function biodataKurang(PmbPendaftar $pendaftar): array
    {
        $kolom = self::BIODATA_WAJIB;

        if ($pendaftar->gelombang?->jalurPindahan()) {
            $kolom = [...$kolom, 'pt_asal', 'prodi_asal'];
        }

        $kurang = array_filter($kolom, fn (string $k): bool => blank($pendaftar->getAttribute($k)));

        if (!in_array('nik', $kurang, true) && !preg_match('/^\d{16}$/', (string) $pendaftar->nik)) {
            $kurang[] = 'nik';
        }

        return array_values(array_map(fn (string $k): string => __('pmb.kolom.'.$k), $kurang));
    }

    /**
     * Submission: the form is complete and the applicant commits to it.
     *
     * Without a form fee the application goes straight to document checking;
     * with one it waits for the cashier.
     */
    public function ajukan(PmbPendaftar $pendaftar): PmbPendaftar
    {
        $this->pastikanDraf($pendaftar);
        $pendaftar->load(['gelombang.prodiDitawarkan', 'berkas']);

        $kurang = $this->biodataKurang($pendaftar);

        if ($kurang !== []) {
            throw new AturanAkademikException(__('pmb.validasi.biodata_kurang', ['kolom' => implode(', ', $kurang)]));
        }

        $berkasKurang = $pendaftar->dokumenKurang();

        if ($berkasKurang !== []) {
            throw new AturanAkademikException(__('pmb.validasi.berkas_kurang', [
                'jenis' => implode(', ', array_map(fn (string $j): string => (string) config('berkas.pmb.'.$j, $j), $berkasKurang)),
            ]));
        }

        return DB::transaction(function () use ($pendaftar): PmbPendaftar {
            $gelombang = PmbGelombang::whereKey($pendaftar->pmb_gelombang_id)->lockForUpdate()->firstOrFail();

            $this->pastikanKuota($gelombang, kecuali: $pendaftar);

            $pendaftar->forceFill(['diajukan_at' => now()]);

            return $this->pindahkan(
                $pendaftar,
                (int) $pendaftar->biaya_formulir > 0 ? ApplicantStatus::Mendaftar : ApplicantStatus::Verifikasi,
            );
        });
    }

    /** Withdrawal by the applicant, possible until a decision exists. */
    public function batalkan(PmbPendaftar $pendaftar): PmbPendaftar
    {
        if ($pendaftar->status->sudahDiputuskan() || $pendaftar->status === ApplicantStatus::Batal) {
            throw new AturanAkademikException(__('pmb.validasi.tidak_bisa_batal'));
        }

        return $this->pindahkan($pendaftar, ApplicantStatus::Batal);
    }

    /** The desk sends a document back with the reason. */
    public function kembalikanBerkas(PmbBerkas $berkas, string $catatan): PmbBerkas
    {
        $berkas->update(['is_verified' => false, 'catatan' => $catatan]);

        $pendaftar = $berkas->pendaftar()->with('akun')->firstOrFail();
        $berkas->setRelation('pendaftar', $pendaftar);

        $this->notifier->kirim($pendaftar->akun, new BerkasDikembalikan($berkas));

        return $berkas;
    }

    /**
     * Accepts a document, or withdraws the acceptance.
     *
     * Accepting the last missing one may complete the application, so the
     * readiness check runs every time.
     */
    public function verifikasiBerkas(PmbBerkas $berkas): PmbBerkas
    {
        $berkas->update([
            'is_verified' => !$berkas->is_verified,
            // An accepted document carries no outstanding request.
            'catatan' => $berkas->is_verified ? $berkas->catatan : null,
        ]);

        $this->periksaKesiapanSeleksi($berkas->pendaftar()->firstOrFail());

        return $berkas->refresh();
    }

    /**
     * Moves a checked application into the selection pool when it is ready.
     *
     * Ready means the fee is settled and every required document accepted.
     * Returns whether it moved.
     */
    public function periksaKesiapanSeleksi(PmbPendaftar $pendaftar): bool
    {
        $pendaftar->load(['gelombang.prodiDitawarkan', 'berkas', 'pembayaran', 'akun']);

        if ($pendaftar->status !== ApplicantStatus::Verifikasi
            || !$pendaftar->formulirLunas()
            || !$pendaftar->dokumenTerverifikasi()) {
            return false;
        }

        $this->pindahkan($pendaftar, ApplicantStatus::Seleksi);
        $this->notifier->kirim($pendaftar->akun, new SiapSeleksi($pendaftar));

        return true;
    }

    /**
     * The one place an applicant's status changes in this flow.
     *
     * Refuses an edge the enum does not list, and records the move with both
     * ends named — the generic "updated" row shows a column changed, not
     * that a person was withdrawn or let into selection.
     */
    public function pindahkan(PmbPendaftar $pendaftar, ApplicantStatus $ke): PmbPendaftar
    {
        $dari = $pendaftar->status;

        if (!$dari->bolehMenujuKe($ke)) {
            throw new AturanAkademikException(__('pmb.validasi.transisi', [
                'dari' => $dari->label(),
                'ke' => $ke->label(),
            ]));
        }

        $pendaftar->status = $ke;
        $pendaftar->save();

        $pendaftar->recordActivity('status_changed', "Status pendaftar: {$dari->label()} → {$ke->label()}.");

        return $pendaftar;
    }

    private function pastikanDraf(PmbPendaftar $pendaftar): void
    {
        if (!$pendaftar->masihDraf()) {
            throw new AturanAkademikException(__('pmb.validasi.bukan_draf'));
        }
    }

    /**
     * The wave's quota counts submitted applications, not drafts.
     *
     * A draft somebody abandoned would otherwise hold a place forever. The
     * cost is that a draft may be refused at submission after the last place
     * went to somebody faster — which is what a quota is.
     */
    private function pastikanKuota(PmbGelombang $gelombang, ?PmbPendaftar $kecuali = null): void
    {
        if ($gelombang->kuota === null) {
            return;
        }

        $terisi = PmbPendaftar::query()
            ->where('pmb_gelombang_id', $gelombang->id)
            ->whereNotIn('status', [ApplicantStatus::Draf->value, ApplicantStatus::Batal->value])
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id))
            ->count();

        if ($terisi >= $gelombang->kuota) {
            throw new AturanAkademikException(__('pmb.validasi.kuota_penuh'));
        }
    }

    /**
     * "<wave code>-0001", continuing from the highest number in the wave.
     *
     * Counted with trashed rows: a withdrawn applicant's number stays theirs,
     * printed on whatever receipt they were given.
     */
    private function nomorBerikutnya(PmbGelombang $gelombang): string
    {
        $awalan = $gelombang->kode.'-';

        $terakhir = PmbPendaftar::withTrashed()
            ->whereLike('nomor_pendaftaran', $awalan.'%', caseSensitive: true)
            ->orderByDesc('nomor_pendaftaran')
            ->value('nomor_pendaftaran');

        $urut = $terakhir === null ? 1 : ((int) substr((string) $terakhir, strlen($awalan))) + 1;

        for ($i = 0; $i < 25; $i++, $urut++) {
            $kandidat = $awalan.str_pad((string) $urut, 4, '0', STR_PAD_LEFT);

            if (!PmbPendaftar::withTrashed()->where('nomor_pendaftaran', $kandidat)->exists()) {
                return $kandidat;
            }
        }

        throw new AturanAkademikException("Tidak ada nomor pendaftaran bebas untuk gelombang {$gelombang->kode}.");
    }
}
