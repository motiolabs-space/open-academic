<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\DTOs\Sdm\AngkaKreditManualData;
use App\DTOs\Sdm\RekapAngkaKredit;
use App\Enums\JabatanFungsional;
use App\Enums\StatusBkd;
use App\Enums\SumberAngkaKredit;
use App\Enums\UnsurAngkaKredit;
use App\Enums\UnsurBkd;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\AngkaKreditDosen;
use App\Models\Sdm\BkdLaporan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\JabatanFungsionalDosen;
use App\Models\Sdm\Staff;
use App\Support\LembarKerjaXlsx;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The credit ledger: Kum A–E per lecturer, and what it adds up to.
 *
 * A ledger, not an assessment. Credit for promotion is assessed by the
 * ministry's PAK platform, by assessors one rank above the lecturer; this
 * class only keeps the lines that platform will ask about, sums them the way
 * its forms do, and says how far the sum is from the next rank's threshold.
 * There is no assessor workflow here and there will not be one.
 *
 * Lines come from four places (SumberAngkaKredit). Two are derived and rebuilt
 * from their source on every reassembly — an endorsed BKD report (Kum B–E) and
 * a degree on the portfolio (Kum A). Two are entered by the personnel office —
 * semesters from before this application existed, converted SKS × 4, and
 * adjustments with a written reason.
 *
 * Everything is in hundredths. The only floats are at the export edge.
 */
class AngkaKreditService
{
    /**
     * The penyetaraan sheet's columns, in the order the operator guide gives.
     *
     * Kept as one constant so a change in the template is one edit.
     */
    public const KOLOM_PENYETARAAN = [
        'NUPTK', 'Nama', 'Jabatan Fungsional', 'TMT Jabatan Fungsional', 'AK Kumulatif Terakhir',
        'Kum A', 'Kum B', 'Kum C', 'Kum D', 'Kum E', 'Total DUPAK', 'AK Penyetaraan',
    ];

    /* ---------------------------------------------------------------------
     | Conversion
     |-------------------------------------------------------------------- */

    /**
     * SKS → AK, both in hundredths: 12,50 SKS × 4 = 50,00 AK.
     *
     * Rounded half up once, here. With the regulation's factor of 4 the result
     * is always exact; the rounding is for a campus that sets another factor.
     */
    public function konversiSks(int $sksRatus, ?int $faktorRatus = null): int
    {
        $faktor = $faktorRatus ?? (int) config('angka_kredit.konversi.faktor_ratus');

        return intdiv($sksRatus * $faktor + 50, 100);
    }

    /**
     * Which factor applies to performance that began on a date.
     *
     * Before the cut-off it is the regulation's conversion. From it on, the
     * ministry platform claims directly from LKD/BKD, and any factor here is
     * the campus's own estimate — so those lines are marked as one.
     *
     * @return array{faktor_ratus: int, perkiraan: bool}
     */
    public function faktorUntuk(CarbonInterface $mulai): array
    {
        $terkonversi = $mulai->lt(Carbon::parse((string) config('angka_kredit.konversi.sebelum')));

        return [
            'faktor_ratus' => (int) config($terkonversi
                ? 'angka_kredit.konversi.faktor_ratus'
                : 'angka_kredit.konversi.faktor_sesudah_ratus'),
            'perkiraan' => !$terkonversi,
        ];
    }

    /* ---------------------------------------------------------------------
     | Derived lines
     |-------------------------------------------------------------------- */

    /**
     * Credits an endorsed BKD report: one line per element with SKS in it.
     *
     * Only an endorsed report counts — "approved" means the institution signed
     * it, not that it was submitted. Run again, it updates the same lines; a
     * report that is somehow no longer endorsed has its lines withdrawn.
     *
     * @return Collection<int, AngkaKreditDosen>
     */
    public function catatBkd(BkdLaporan $laporan): Collection
    {
        return DB::transaction(function () use ($laporan): Collection {
            if ($laporan->status !== StatusBkd::Disahkan || $laporan->trashed()) {
                AngkaKreditDosen::where('bkd_laporan_id', $laporan->id)->get()->each->delete();

                return collect();
            }

            $term = $laporan->tahunAkademik()->withTrashed()->first();
            ['faktor_ratus' => $faktor, 'perkiraan' => $perkiraan] = $this->faktorUntuk($term->tanggal_mulai);

            $hasil = collect();

            foreach (UnsurBkd::cases() as $unsurBkd) {
                $sks = (int) $laporan->{'sks_'.$unsurBkd->value};
                $unsur = UnsurAngkaKredit::dariUnsurBkd($unsurBkd);

                $baris = AngkaKreditDosen::withTrashed()->firstOrNew([
                    'bkd_laporan_id' => $laporan->id,
                    'unsur' => $unsur->value,
                ]);

                if ($sks <= 0) {
                    if ($baris->exists && !$baris->trashed()) {
                        $baris->delete();
                    }

                    continue;
                }

                $baris->fill([
                    'dosen_id' => $laporan->dosen_id,
                    'sumber' => SumberAngkaKredit::Bkd,
                    'semester_kode' => $term->kode,
                    'tanggal' => $term->tanggal_selesai,
                    'sks_ratus' => $sks,
                    'faktor_ratus' => $faktor,
                    'nilai_ratus' => $this->konversiSks($sks, $faktor),
                    'perkiraan' => $perkiraan,
                    'keterangan' => __('angka_kredit.keterangan.bkd', ['semester' => $term->nama]),
                ]);

                $this->simpanPulihkan($baris);
                $hasil->push($baris);
            }

            return $hasil;
        });
    }

    /**
     * Credits the lecturer's master's and doctoral degrees (Kum A).
     *
     * Every degree with a configured value gets a line; the recap decides
     * which ones fall after the rank in force. Degrees removed from the
     * portfolio have their lines withdrawn.
     *
     * @return Collection<int, AngkaKreditDosen>
     */
    public function catatPendidikan(Dosen $dosen): Collection
    {
        /** @var array<string, int> $nilai */
        $nilai = (array) config('angka_kredit.pendidikan_formal');

        return DB::transaction(function () use ($dosen, $nilai): Collection {
            $hasil = collect();

            foreach ($dosen->riwayatPendidikan()->get() as $ijazah) {
                $ak = $nilai[$ijazah->jenjang->value] ?? null;

                if ($ak === null || $ijazah->tahun_lulus === null) {
                    continue;
                }

                $baris = AngkaKreditDosen::withTrashed()->firstOrNew([
                    'riwayat_pendidikan_dosen_id' => $ijazah->id,
                    'unsur' => UnsurAngkaKredit::Pendidikan->value,
                ]);

                $baris->fill([
                    'dosen_id' => $dosen->id,
                    'sumber' => SumberAngkaKredit::PendidikanFormal,
                    'tanggal' => Carbon::create((int) $ijazah->tahun_lulus, 12, 31),
                    'nilai_ratus' => (int) $ak,
                    'perkiraan' => false,
                    'keterangan' => $ijazah->jenjang->label().' — '.$ijazah->perguruan_tinggi,
                ]);

                $this->simpanPulihkan($baris);
                $hasil->push($baris);
            }

            AngkaKreditDosen::query()
                ->where('dosen_id', $dosen->id)
                ->where('sumber', SumberAngkaKredit::PendidikanFormal->value)
                ->whereNotIn('id', $hasil->pluck('id'))
                ->get()
                ->each->delete();

            return $hasil;
        });
    }

    /**
     * Rebuilds every derived line for one lecturer.
     *
     * Office-entered lines are left exactly as they are.
     */
    public function susun(Dosen $dosen): void
    {
        DB::transaction(function () use ($dosen): void {
            $laporan = BkdLaporan::query()
                ->where('dosen_id', $dosen->id)
                ->where('status', StatusBkd::Disahkan->value)
                ->get();

            foreach ($laporan as $satu) {
                $this->catatBkd($satu);
            }

            // Lines whose report was deleted or is no longer endorsed.
            AngkaKreditDosen::query()
                ->where('dosen_id', $dosen->id)
                ->where('sumber', SumberAngkaKredit::Bkd->value)
                ->where(fn ($q) => $q->whereNull('bkd_laporan_id')->orWhereNotIn('bkd_laporan_id', $laporan->pluck('id')))
                ->get()
                ->each->delete();

            $this->catatPendidikan($dosen);
        });
    }

    /** Rebuilds the derived lines of every lecturer; returns how many. */
    public function susunSemua(): int
    {
        $jumlah = 0;

        Dosen::query()->orderBy('id')->chunkById(100, function ($dosen) use (&$jumlah): void {
            foreach ($dosen as $satu) {
                $this->susun($satu);
                $jumlah++;
            }
        });

        return $jumlah;
    }

    /* ---------------------------------------------------------------------
     | Office-entered lines
     |-------------------------------------------------------------------- */

    /**
     * Records a converted pre-2026 semester or a reasoned adjustment.
     *
     * A converted semester is refused when the ledger already credits an
     * endorsed BKD report for that lecturer and semester — the same work
     * counted twice is the most likely mistake when a campus backfills.
     */
    public function catatManual(Dosen $dosen, Staff $staf, AngkaKreditManualData $data): AngkaKreditDosen
    {
        return match ($data->sumber) {
            SumberAngkaKredit::KonversiManual => $this->catatKonversi($dosen, $staf, $data),
            SumberAngkaKredit::Penyesuaian => $this->catatPenyesuaian($dosen, $staf, $data),
            default => throw new AturanAkademikException(__('angka_kredit.pesan.sumber_turunan')),
        };
    }

    /** Removes an office-entered line. Derived lines follow their source. */
    public function hapusManual(AngkaKreditDosen $baris): void
    {
        if ($baris->sumber->turunan()) {
            throw new AturanAkademikException(__('angka_kredit.pesan.sumber_turunan'));
        }

        $baris->delete();
    }

    /* ---------------------------------------------------------------------
     | Recap
     |-------------------------------------------------------------------- */

    public function rekap(Dosen $dosen): RekapAngkaKredit
    {
        $jabatan = $dosen->riwayatJabatan()->aktif()->first();
        $baris = AngkaKreditDosen::where('dosen_id', $dosen->id)->get();

        return $this->hitung($dosen, $jabatan, $baris);
    }

    /**
     * Every active lecturer's recap, in three queries whatever the size.
     *
     * @return Collection<int, RekapAngkaKredit>
     */
    public function rekapSemua(): Collection
    {
        $dosen = Dosen::aktif()
            ->with(['prodi', 'riwayatJabatan' => fn ($q) => $q->aktif()])
            ->orderBy('nama')
            ->get();

        $baris = AngkaKreditDosen::query()
            ->whereIn('dosen_id', $dosen->modelKeys())
            ->get()
            ->groupBy('dosen_id');

        return $dosen->map(fn (Dosen $d): RekapAngkaKredit => $this->hitung(
            $d,
            $d->riwayatJabatan->first(),
            $baris->get($d->id, collect()),
        ));
    }

    /**
     * The recap grouped by rank held, lowest first.
     *
     * @param Collection<int, RekapAngkaKredit> $rekap
     * @return array<int, array{jabatan: ?JabatanFungsional, jumlah: int, siap: int, dupak_ratus: int, diakui_ratus: array<string, int>}>
     */
    public function rekapPerJenjang(Collection $rekap): array
    {
        return $rekap
            ->groupBy(fn (RekapAngkaKredit $r): string => $r->jabatan?->value ?? '')
            ->map(fn (Collection $kelompok): array => [
                'jabatan' => $kelompok->first()->jabatan,
                'jumlah' => $kelompok->count(),
                'siap' => $kelompok->where('siap', true)->count(),
                'dupak_ratus' => $kelompok->sum('totalDupakRatus'),
                'diakui_ratus' => collect(UnsurAngkaKredit::cases())
                    ->mapWithKeys(fn (UnsurAngkaKredit $u): array => [
                        $u->value => $kelompok->sum(fn (RekapAngkaKredit $r): int => $r->diakui($u)),
                    ])->all(),
            ])
            ->sortBy(fn (array $k): int => $k['jabatan']?->tingkat() ?? -1)
            ->values()
            ->all();
    }

    /**
     * Holds Kum D and E each to their share of the total DUPAK.
     *
     * The share is of the total *after* capping — the figure the uploaded
     * sheet will show and be checked against — so the cap is a fixed point,
     * found by lowering D and E until neither exceeds its share of the sum
     * that includes them. It converges from above in a handful of steps, and
     * never raises a value above what was actually done.
     *
     * @param array<string, int> $kum keyed by UnsurAngkaKredit value
     * @return array<string, int>
     */
    public function batasiUnsur(array $kum): array
    {
        $kum += array_fill_keys(array_map(fn ($u) => $u->value, UnsurAngkaKredit::cases()), 0);
        $persen = (array) config('angka_kredit.batas_persen');
        $diakui = $kum;

        for ($putaran = 0; $putaran < 100; $putaran++) {
            $total = array_sum($diakui);
            $berubah = false;

            foreach (UnsurAngkaKredit::cases() as $unsur) {
                if (!$unsur->dibatasi()) {
                    continue;
                }

                $maks = max(0, intdiv($total * (int) ($persen[$unsur->value] ?? 100), 100));
                $baru = min($kum[$unsur->value], $maks);

                if ($baru !== $diakui[$unsur->value]) {
                    $diakui[$unsur->value] = $baru;
                    $berubah = true;
                }
            }

            if (!$berubah) {
                break;
            }
        }

        return $diakui;
    }

    /* ---------------------------------------------------------------------
     | Penyetaraan sheet
     |-------------------------------------------------------------------- */

    /**
     * Rows for the AK penyetaraan upload, and the lecturers left out and why.
     *
     * Eligible: a permanent lecturer holding AA, L or LK with a TMT on or
     * before the configured date. A lecturer without a NUPTK is reported and
     * left out of the file rather than written into it — the upload matches
     * by NUPTK and name, and one unmatched row is a rejected upload found
     * after the fact instead of a list to fix before.
     *
     * Pass the recap a screen already built to avoid computing it twice.
     *
     * @param Collection<int, RekapAngkaKredit>|null $rekap
     * @return array{baris: array<int, array<int, string|float|null>>, tertahan: array<int, array{dosen: Dosen, alasan: string}>}
     */
    public function lembarPenyetaraan(?Collection $rekap = null): array
    {
        $batas = Carbon::parse((string) config('angka_kredit.penyetaraan.tmt_paling_lambat'))->endOfDay();
        $status = (array) config('angka_kredit.penyetaraan.status_kepegawaian');
        $jenjang = [JabatanFungsional::AsistenAhli, JabatanFungsional::Lektor, JabatanFungsional::LektorKepala];

        $baris = [];
        $tertahan = [];

        foreach ($rekap ?? $this->rekapSemua() as $r) {
            if (!in_array($r->jabatan, $jenjang, true)
                || $r->tmtJabatan === null || $r->tmtJabatan->gt($batas)
                || !in_array($r->dosen->status_kepegawaian, $status, true)) {
                continue;
            }

            if (blank($r->dosen->nuptk)) {
                $tertahan[] = ['dosen' => $r->dosen, 'alasan' => __('angka_kredit.pesan.tanpa_nuptk')];

                continue;
            }

            $angka = fn (int $ratus): float => $ratus / 100;

            $baris[] = [
                $r->dosen->nuptk,
                $r->dosen->nama,
                $r->jabatan->singkatan(),
                $r->tmtJabatan->format('Y-m-d'),
                $angka($r->akTerakhirRatus),
                ...array_map(fn (UnsurAngkaKredit $u): float => $angka($r->diakui($u)), UnsurAngkaKredit::cases()),
                $angka($r->totalDupakRatus),
                $angka($r->akKumulatifRatus),
            ];
        }

        return ['baris' => $baris, 'tertahan' => $tertahan];
    }

    /** Writes the penyetaraan sheet to a temporary .xlsx and returns its path. */
    public function eksporPenyetaraan(): string
    {
        $lembar = new LembarKerjaXlsx('Penyetaraan', self::KOLOM_PENYETARAAN);

        foreach ($this->lembarPenyetaraan()['baris'] as $baris) {
            $lembar->tambah($baris);
        }

        $path = tempnam(sys_get_temp_dir(), 'oa-penyetaraan-');

        return $lembar->simpan($path === false ? sys_get_temp_dir().'/oa-penyetaraan-'.uniqid().'.xlsx' : $path);
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    /** @param Collection<int, AngkaKreditDosen> $baris */
    private function hitung(Dosen $dosen, ?JabatanFungsionalDosen $jabatan, Collection $baris): RekapAngkaKredit
    {
        $tmt = $jabatan?->tmt;

        $kum = array_fill_keys(array_map(fn ($u) => $u->value, UnsurAngkaKredit::cases()), 0);

        foreach ($baris as $b) {
            if ($b->sesudahTmt($tmt)) {
                $kum[$b->unsur->value] += $b->nilai_ratus;
            }
        }

        $diakui = $this->batasiUnsur($kum);
        $dupak = array_sum($diakui);
        $akTerakhir = (int) ($jabatan?->angka_kredit_ratus ?? 0);
        $akKumulatif = $akTerakhir + $dupak;

        $sekarang = $jabatan?->jabatan ?? JabatanFungsional::TenagaPengajar;
        $berikutnya = $sekarang->berikutnya();
        $syarat = $berikutnya === null ? null : (array) config('angka_kredit.syarat.'.$berikutnya->value);
        $syaratRatus = $syarat === null ? null : (int) ($syarat['kumulatif_ratus'] ?? $berikutnya->angkaKreditMinimum() * 100);

        $selisih = $syaratRatus === null ? null : max(0, $syaratRatus - $akKumulatif);

        // The research share is of what the promotion still needs, rounded up:
        // 35 % of 3,01 is 1,06, not 1,05.
        $kebutuhan = $syaratRatus === null ? 0 : max(0, $syaratRatus - $akTerakhir);
        $penelitianMinimum = intdiv($kebutuhan * (int) ($syarat['penelitian_persen'] ?? 0) + 99, 100);
        $memenuhiPenelitian = $diakui[UnsurAngkaKredit::Penelitian->value] >= $penelitianMinimum;

        return new RekapAngkaKredit(
            dosen: $dosen,
            jabatan: $jabatan?->jabatan,
            tmtJabatan: $tmt,
            akTerakhirRatus: $akTerakhir,
            kumRatus: $kum,
            diakuiRatus: $diakui,
            totalDupakRatus: $dupak,
            akKumulatifRatus: $akKumulatif,
            berikutnya: $berikutnya,
            syaratRatus: $syaratRatus,
            selisihRatus: $selisih,
            penelitianMinimumRatus: $penelitianMinimum,
            memenuhiPenelitian: $memenuhiPenelitian,
            siap: $berikutnya !== null && $selisih === 0 && $memenuhiPenelitian,
            adaPerkiraan: $baris->contains(fn (AngkaKreditDosen $b): bool => $b->perkiraan && $b->sesudahTmt($tmt)),
        );
    }

    private function catatKonversi(Dosen $dosen, Staff $staf, AngkaKreditManualData $data): AngkaKreditDosen
    {
        if ($data->semesterKode === null || $data->sksRatus === null || $data->sksRatus <= 0) {
            throw new AturanAkademikException(__('angka_kredit.pesan.konversi_lengkap'));
        }

        [$mulai, $selesai] = $this->rentangSemester($data->semesterKode);

        if ($this->faktorUntuk($mulai)['perkiraan']) {
            throw new AturanAkademikException(__('angka_kredit.pesan.konversi_sesudah'));
        }

        $sudahDariBkd = AngkaKreditDosen::query()
            ->where('dosen_id', $dosen->id)
            ->where('sumber', SumberAngkaKredit::Bkd->value)
            ->where('semester_kode', $data->semesterKode)
            ->where('unsur', $data->unsur->value)
            ->exists();

        if ($sudahDariBkd) {
            throw new AturanAkademikException(__('angka_kredit.pesan.konversi_ganda'));
        }

        $faktor = (int) config('angka_kredit.konversi.faktor_ratus');

        return AngkaKreditDosen::create([
            'dosen_id' => $dosen->id,
            'unsur' => $data->unsur,
            'sumber' => SumberAngkaKredit::KonversiManual,
            'semester_kode' => $data->semesterKode,
            'tanggal' => $selesai,
            'sks_ratus' => $data->sksRatus,
            'faktor_ratus' => $faktor,
            'nilai_ratus' => $this->konversiSks($data->sksRatus, $faktor),
            'perkiraan' => false,
            'keterangan' => $data->keterangan,
            'dicatat_oleh' => $staf->id,
        ]);
    }

    private function catatPenyesuaian(Dosen $dosen, Staff $staf, AngkaKreditManualData $data): AngkaKreditDosen
    {
        if ($data->nilaiRatus === null || $data->nilaiRatus === 0 || $data->tanggal === null) {
            throw new AturanAkademikException(__('angka_kredit.pesan.penyesuaian_lengkap'));
        }

        if (mb_strlen((string) $data->keterangan) < 5) {
            throw new AturanAkademikException(__('angka_kredit.pesan.alasan_wajib'));
        }

        return AngkaKreditDosen::create([
            'dosen_id' => $dosen->id,
            'unsur' => $data->unsur,
            'sumber' => SumberAngkaKredit::Penyesuaian,
            'tanggal' => $data->tanggal,
            'nilai_ratus' => $data->nilaiRatus,
            'perkiraan' => false,
            'keterangan' => $data->keterangan,
            'dicatat_oleh' => $staf->id,
        ]);
    }

    /**
     * A semester's dates: the term's own when it is on record, otherwise the
     * PDDIKTI pattern — Ganjil August–January, Genap February–July, Antara
     * July–August. Old semesters usually predate the tahun_akademik table.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rentangSemester(string $kode): array
    {
        $term = TahunAkademik::query()->withTrashed()->where('kode', $kode)->first();

        if ($term !== null) {
            return [$term->tanggal_mulai, $term->tanggal_selesai];
        }

        $tahun = (int) substr($kode, 0, 4);

        return match (substr($kode, 4, 1)) {
            '1' => [Carbon::create($tahun, 8, 1), Carbon::create($tahun + 1, 1, 31)],
            '2' => [Carbon::create($tahun + 1, 2, 1), Carbon::create($tahun + 1, 7, 31)],
            default => [Carbon::create($tahun + 1, 7, 1), Carbon::create($tahun + 1, 8, 31)],
        };
    }

    /** Saves a line, restoring it first when a previous reassembly withdrew it. */
    private function simpanPulihkan(AngkaKreditDosen $baris): void
    {
        if ($baris->trashed()) {
            $baris->restore();
        }

        $baris->save();
    }
}
