<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\SyaratWisuda;
use App\Enums\EducationLevel;
use App\Enums\SumberNomorIjazah;
use App\Exceptions\AturanAkademikException;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Staff;
use App\Notifications\Kemahasiswaan\PendaftaranWisudaDibatalkan;
use App\Notifications\Kemahasiswaan\PendaftaranWisudaDiterima;
use App\Services\Branding\BrandingService;
use App\Services\Notifikasi\Notifier;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The graduation ceremony: who walks, in what order, holding which diploma.
 *
 * Distinct from yudisium, which is the academic decision that somebody has
 * graduated. A person is a graduate the moment yudisium is confirmed; the
 * ceremony is a separate event they register for, may miss, and may attend a
 * later round of. Collapsing the two would mean a graduate who skipped the
 * ceremony is not recorded as having graduated.
 *
 * The diploma number is the part that has to be right. It is printed on a
 * document the person keeps for life and quotes on every job application, so it
 * is issued once, never reused, and never silently regenerated.
 */
class WisudaService
{
    public function __construct(
        private readonly SyaratWisudaService $syarat,
        private readonly Notifier $notifier,
        private readonly BrandingService $brand,
    ) {}

    /**
     * Registers a confirmed graduate for a ceremony.
     *
     * Only confirmed graduations are eligible: registering somebody whose
     * yudisium is still a proposal would put a name on the programme for a
     * degree that has not been awarded. Beyond that the whole ceremony
     * checklist applies — the same one the student's own screen shows, so the
     * office cannot register somebody the student would have been refused, nor
     * the other way round.
     */
    public function daftarkan(WisudaPeriode $periode, Yudisium $yudisium): WisudaPeserta
    {
        return $this->daftarkanTerperiksa($periode, $yudisium, $this->syarat->periksa($yudisium->mahasiswa));
    }

    /**
     * Registers every eligible graduate who has not been placed yet.
     *
     * @return array{didaftarkan: int, dilewati: int, gagal: Collection<int, string>}
     */
    public function daftarkanMassal(WisudaPeriode $periode): array
    {
        $kandidat = Yudisium::query()
            ->with('mahasiswa')
            ->where('status', 'ditetapkan')
            ->whereDoesntHave('pesertaWisuda')
            ->orderBy('tanggal_lulus')
            ->get();

        // One batch check for the whole queue rather than one per graduate.
        $syarat = $this->syarat->periksaBanyak($kandidat->pluck('mahasiswa'));

        $didaftarkan = 0;
        $gagal = collect();

        foreach ($kandidat as $yudisium) {
            try {
                $this->daftarkanTerperiksa($periode, $yudisium, $syarat[$yudisium->mahasiswa_id]);
                $didaftarkan++;
            } catch (AturanAkademikException $e) {
                // A full quota stops the run; anything else is about this one
                // person and must not abandon the rest.
                $gagal->push($yudisium->mahasiswa->nama.': '.$e->getMessage());

                if (str_contains($e->getMessage(), 'Kuota')) {
                    break;
                }
            }
        }

        return [
            'didaftarkan' => $didaftarkan,
            'dilewati' => $kandidat->count() - $didaftarkan - $gagal->count(),
            'gagal' => $gagal,
        ];
    }

    /**
     * A graduate registering themself.
     *
     * The same path as staff registration from the first check onwards. The
     * only difference is that the graduation record is found from the person
     * signed in rather than from a form field naming it, so there is nothing
     * to forge.
     */
    public function daftarSendiri(WisudaPeriode $periode, Mahasiswa $mahasiswa): WisudaPeserta
    {
        $yudisium = $mahasiswa->yudisium;

        if ($yudisium === null) {
            throw new AturanAkademikException(__('wisuda.galat.tanpa_yudisium'));
        }

        return $this->daftarkan($periode, $yudisium);
    }

    /**
     * A graduate withdrawing their own registration.
     *
     * Only while the period is still open: once it closes, the seating plan and
     * the printed programme are built from the list, and a silent withdrawal
     * leaves an empty chair with a name on it. After that the graduate asks
     * the office, which can still remove them.
     */
    public function batalkanSendiri(WisudaPeriode $periode, Mahasiswa $mahasiswa): void
    {
        $peserta = $this->pesertaDari($periode, $mahasiswa);

        if ($peserta === null) {
            throw new AturanAkademikException(__('wisuda.galat.tidak_terdaftar', ['periode' => $periode->nama]));
        }

        if (!$periode->is_pendaftaran_dibuka) {
            throw new AturanAkademikException(__('wisuda.galat.batal_ditutup', ['periode' => $periode->nama]));
        }

        $this->batalkan($peserta);
    }

    /** The graduate's registration in this period, if any. */
    public function pesertaDari(WisudaPeriode $periode, Mahasiswa $mahasiswa): ?WisudaPeserta
    {
        return WisudaPeserta::query()
            ->where('wisuda_periode_id', $periode->id)
            ->whereHas('yudisium', fn ($q) => $q->where('mahasiswa_id', $mahasiswa->id))
            ->first();
    }

    /**
     * Ceremonies a graduate can still sign up for: open, and not yet held.
     *
     * @return EloquentCollection<int, WisudaPeriode>
     */
    public function periodeTerbuka(): EloquentCollection
    {
        return WisudaPeriode::query()
            ->withCount('peserta')
            ->where('is_pendaftaran_dibuka', true)
            ->whereDate('tanggal', '>=', today())
            ->orderBy('tanggal')
            ->get();
    }

    public function batalkan(WisudaPeserta $peserta): void
    {
        if (filled($peserta->nomor_ijazah)) {
            throw new AturanAkademikException(
                'Peserta ini sudah menerima nomor ijazah dan tidak dapat dikeluarkan dari daftar. '
                    .'Nomor ijazah tercetak pada dokumen yang dipegang seumur hidup.',
            );
        }

        $periode = $peserta->periode;
        $mahasiswa = $peserta->yudisium->mahasiswa;

        $peserta->delete();

        $periode->recordActivity('participant_removed', sprintf(
            '%s (%s) keluar dari daftar peserta %s.',
            $mahasiswa->nama,
            $mahasiswa->nim,
            $periode->nama,
        ));

        // Told whichever side removed them: a graduate dropped by the office
        // must not find out at the door.
        $this->notifier->kirim($mahasiswa, new PendaftaranWisudaDibatalkan($periode));
    }

    /** The registration itself, against a checklist the caller already ran. */
    private function daftarkanTerperiksa(WisudaPeriode $periode, Yudisium $yudisium, SyaratWisuda $syarat): WisudaPeserta
    {
        if (!$periode->is_pendaftaran_dibuka) {
            throw new AturanAkademikException("Pendaftaran {$periode->nama} sudah ditutup.");
        }

        if ($yudisium->status !== 'ditetapkan') {
            throw new AturanAkademikException(
                "Kelulusan {$yudisium->mahasiswa->nama} belum ditetapkan, sehingga belum dapat "
                    .'didaftarkan sebagai peserta wisuda.',
            );
        }

        if (!$syarat->memenuhi()) {
            throw new AturanAkademikException(__('wisuda.galat.syarat', [
                'nama' => $yudisium->mahasiswa->nama,
                'syarat' => implode(', ', $syarat->belumTerpenuhi()),
            ]));
        }

        $sudah = WisudaPeserta::where('yudisium_id', $yudisium->id)->first();

        if ($sudah !== null) {
            throw new AturanAkademikException(sprintf(
                '%s sudah terdaftar pada %s.',
                $yudisium->mahasiswa->nama,
                $sudah->periode->nama,
            ));
        }

        $peserta = DB::transaction(function () use ($periode, $yudisium): WisudaPeserta {
            // Locked before counting: two registrations arriving together would
            // otherwise both read the same count and both be admitted past a
            // full ceremony, or take the same queue number.
            $terkunci = WisudaPeriode::whereKey($periode->id)->lockForUpdate()->firstOrFail();

            $terdaftar = WisudaPeserta::where('wisuda_periode_id', $terkunci->id)->count();

            if ($terkunci->kuota !== null && $terdaftar >= (int) $terkunci->kuota) {
                throw new AturanAkademikException(sprintf(
                    'Kuota %s sudah penuh (%d peserta).',
                    $terkunci->nama,
                    $terkunci->kuota,
                ));
            }

            // After the highest number, not after the head count: once anyone
            // withdraws, the count falls behind the numbers already handed out
            // and the next graduate would be given one that is taken.
            $urut = (int) WisudaPeserta::where('wisuda_periode_id', $terkunci->id)->max('nomor_urut');

            return WisudaPeserta::create([
                'wisuda_periode_id' => $terkunci->id,
                'yudisium_id' => $yudisium->id,
                'nomor_urut' => $urut + 1,
            ]);
        });

        $this->notifier->kirim($yudisium->mahasiswa, new PendaftaranWisudaDiterima($peserta));

        return $peserta;
    }

    /**
     * Issues diploma numbers for everyone in the ceremony who lacks one.
     *
     * Never regenerated. A number already issued stays as it is even if the
     * ceremony is re-run, because it is already printed on a document in
     * somebody's hands.
     *
     * @return array{diterbitkan: int, dilewati: int}
     */
    public function terbitkanNomorIjazah(WisudaPeriode $periode, Staff $staff, string $pola): array
    {
        if (!$this->penomoranLokal()) {
            throw new AturanAkademikException(__('wisuda.pisn.tidak_dibangkitkan'));
        }

        $peserta = WisudaPeserta::query()
            ->with('yudisium.mahasiswa.prodi')
            ->where('wisuda_periode_id', $periode->id)
            ->orderBy('nomor_urut')
            ->get();

        $diterbitkan = 0;
        $dilewati = 0;

        foreach ($peserta as $baris) {
            if (filled($baris->nomor_ijazah)) {
                $dilewati++;

                continue;
            }

            $baris->update(['nomor_ijazah' => $this->nomorIjazah($pola, $periode, $baris)]);
            $diterbitkan++;
        }

        if ($diterbitkan > 0) {
            $periode->recordActivity('diploma_numbers_issued', sprintf(
                '%d nomor ijazah diterbitkan oleh %s.',
                $diterbitkan,
                $staff->nama,
            ));
        }

        return ['diterbitkan' => $diterbitkan, 'dilewati' => $dilewati];
    }

    /** Whether this campus generates its own diploma numbers. */
    public function penomoranLokal(): bool
    {
        return SumberNomorIjazah::dariConfig() === SumberNomorIjazah::Lokal;
    }

    /**
     * Records diploma numbers issued by the national service (PISN).
     *
     * Input is what the PISN site gives back: one graduate per line, NIM and
     * number, separated by a comma, a semicolon, a tab or spaces. Each line
     * is judged on its own — a bad line is reported, the good ones land —
     * because the alternative, all-or-nothing, means an operator retypes the
     * forty good lines to fix the one typo.
     *
     * Under PISN a number is also checked against its structure (ADR-036 §2):
     * 21 digits, this institution, the graduate's programme, a plausible year,
     * and the document type the programme issues. A number pasted into the
     * wrong row is caught here rather than printed.
     *
     * A number already recorded is never overwritten: it may be printed on a
     * document by now. A line that repeats the same number for the same
     * graduate is a harmless re-import and counted as skipped.
     *
     * @return array{dicatat: int, dilewati: int, ditolak: array<int, string>}
     */
    public function catatNomorIjazah(WisudaPeriode $periode, Staff $staff, string $isian): array
    {
        $peserta = WisudaPeserta::query()
            ->with('yudisium.mahasiswa.prodi')
            ->where('wisuda_periode_id', $periode->id)
            ->get()
            ->keyBy(fn (WisudaPeserta $p): string => $p->yudisium->mahasiswa->nim);

        $dicatat = 0;
        $dilewati = 0;
        $ditolak = [];
        $terpakai = [];

        foreach (preg_split('/\R/', trim($isian)) ?: [] as $nomorBaris => $baris) {
            $baris = trim($baris);

            if ($baris === '' || str_starts_with($baris, '#')) {
                continue;
            }

            $bagian = preg_split('/[,;\t]+|\s{1,}/', $baris, 2) ?: [];
            $nim = trim($bagian[0] ?? '');
            $nomor = trim($bagian[1] ?? '');
            $label = 'Baris '.($nomorBaris + 1).' ('.$nim.')';

            if ($nim === '' || $nomor === '') {
                $ditolak[] = "{$label}: butuh NIM dan nomor ijazah, dipisah koma, titik koma, atau spasi.";

                continue;
            }

            $p = $peserta->get($nim);

            if ($p === null) {
                $ditolak[] = "{$label}: NIM tidak terdaftar pada periode wisuda ini.";

                continue;
            }

            if (filled($p->nomor_ijazah)) {
                if ($p->nomor_ijazah === $nomor) {
                    $dilewati++;
                } else {
                    $ditolak[] = "{$label}: sudah tercatat {$p->nomor_ijazah}; nomor yang sudah tercatat tidak ditimpa.";
                }

                continue;
            }

            if (($masalah = $this->masalahNomorPisn($p, $nomor)) !== null) {
                $ditolak[] = "{$label}: {$masalah}";

                continue;
            }

            if (isset($terpakai[$nomor]) || WisudaPeserta::query()->where('nomor_ijazah', $nomor)->exists()) {
                $ditolak[] = "{$label}: nomor {$nomor} sudah dipakai lulusan lain.";

                continue;
            }

            $p->update(['nomor_ijazah' => $nomor]);
            $terpakai[$nomor] = true;
            $dicatat++;
        }

        if ($dicatat > 0) {
            $periode->recordActivity('diploma_numbers_recorded', sprintf(
                '%d nomor ijazah PISN dicatat oleh %s.',
                $dicatat,
                $staff->nama,
            ));
        }

        return ['dicatat' => $dicatat, 'dilewati' => $dilewati, 'ditolak' => $ditolak];
    }

    /**
     * Why a number cannot be a PISN number for this graduate, or null.
     *
     * Structure, from the ministry's PISN material: institution code (6),
     * programme code (5), year of issue (4), type (1 — 1 diploma, 2
     * professional certificate), sequence (5). The institution and programme
     * parts are compared only when the codes held here have PDDIKTI's shape,
     * so a demo or a campus that has not filled them in is not blocked by its
     * own settings. Graduates before 2025 kept numbers from the earlier
     * scheme, whose format differs, and are not checked.
     */
    private function masalahNomorPisn(WisudaPeserta $peserta, string $nomor): ?string
    {
        $yudisium = $peserta->yudisium;

        if ($this->penomoranLokal() || ($yudisium->tanggal_lulus !== null && $yudisium->tanggal_lulus->year < 2025)) {
            return null;
        }

        if (preg_match('/^\d{21}$/', $nomor) !== 1) {
            return __('wisuda.pisn.format_digit', ['nomor' => $nomor]);
        }

        $pt = $this->brand->institutionCode();
        $prodi = $yudisium->mahasiswa->prodi;
        $tahun = (int) substr($nomor, 11, 4);
        $sertifikat = in_array($prodi?->jenjang, [EducationLevel::Profesi, EducationLevel::Spesialis1, EducationLevel::Spesialis2], true);

        return match (true) {
            preg_match('/^\d{6}$/', $pt) === 1 && substr($nomor, 0, 6) !== $pt => __('wisuda.pisn.format_pt', ['kode' => substr($nomor, 0, 6), 'pt' => $pt]),
            $prodi !== null && preg_match('/^\d{5}$/', $prodi->kode) === 1 && substr($nomor, 6, 5) !== $prodi->kode => __('wisuda.pisn.format_prodi', ['kode' => substr($nomor, 6, 5), 'prodi' => $prodi->kode]),
            $tahun < 2024 || $tahun > now()->year + 1 => __('wisuda.pisn.format_tahun', ['tahun' => $tahun]),
            $nomor[15] !== ($sertifikat ? '2' : '1') => __($sertifikat ? 'wisuda.pisn.format_tipe_sertifikat' : 'wisuda.pisn.format_tipe_ijazah'),
            default => null,
        };
    }

    /**
     * Builds one diploma number from the campus's pattern.
     *
     * Placeholders mirror the NIM generator so an operator only learns one
     * convention: {tahun}, {prodi}, {urut}. Like the NIM, {prodi} is `kode`:
     * `kode_pddikti` is PDDIKTI's id_sms, a UUID that has no place on a diploma.
     */
    private function nomorIjazah(string $pola, WisudaPeriode $periode, WisudaPeserta $peserta): string
    {
        $prodi = $peserta->yudisium->mahasiswa->prodi;

        return str_replace(
            ['{tahun}', '{prodi}', '{urut}'],
            [
                (string) $periode->tanggal->year,
                $prodi->kode,
                str_pad((string) $peserta->nomor_urut, 4, '0', STR_PAD_LEFT),
            ],
            $pola,
        );
    }

    public function bukaPendaftaran(WisudaPeriode $periode): WisudaPeriode
    {
        $periode->update(['is_pendaftaran_dibuka' => true]);

        return $periode->refresh();
    }

    public function tutupPendaftaran(WisudaPeriode $periode): WisudaPeriode
    {
        $periode->update(['is_pendaftaran_dibuka' => false]);

        return $periode->refresh();
    }
}
