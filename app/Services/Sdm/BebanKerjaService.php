<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\DTOs\Sdm\BarisBeban;
use App\Enums\PeranPembimbing;
use App\Enums\StatusUjian;
use App\Enums\UnsurBkd;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\TugasAkhir\Pembimbing;
use App\Models\TugasAkhir\Penguji;
use Illuminate\Support\Collection;

/**
 * The teaching half of a workload report, computed rather than typed.
 *
 * This is the point of the whole module. Everything under "pendidikan" — which
 * classes, how many credits, how many students supervised, how many defences
 * examined, how many advisees — is already in this database as a by-product of
 * running the semester. A lecturer currently opens the SIAKAD in one tab and
 * retypes it into a ministry form in another.
 *
 * The other three elements are not here, and no amount of cleverness would put
 * them here: research, community service, and supporting duties never pass
 * through a SIAKAD. They are read from `penugasan_dosen`, where the lecturer
 * records them with evidence attached.
 *
 * **The weights are not this class's business.** How much a supervised student
 * is worth in credits is a campus interpretation of a guideline that changes,
 * and it lives in config/bkd.php. What is guaranteed here is that the counts
 * are right — same division of labour as IkuDataController, which counts facts
 * and refuses to apply thresholds.
 */
class BebanKerjaService
{
    /**
     * Every line of one lecturer's sheet for one term.
     *
     * @return Collection<int, BarisBeban>
     */
    public function hitung(Dosen $dosen, TahunAkademik $term): Collection
    {
        return collect()
            ->concat($this->mengajar($dosen, $term))
            ->concat($this->membimbing($dosen, $term))
            ->concat($this->menguji($dosen, $term))
            ->concat($this->perwalian($dosen))
            ->concat($this->kegiatanDilaporkan($dosen, $term))
            ->values();
    }

    /**
     * Totals per element, in hundredths.
     *
     * @param Collection<int, BarisBeban> $baris
     * @return array<string, int>
     */
    public function ringkas(Collection $baris): array
    {
        $per = collect(UnsurBkd::cases())
            ->mapWithKeys(fn (UnsurBkd $u): array => [
                $u->value => (int) $baris
                    ->filter(fn (BarisBeban $b): bool => $b->unsur === $u)
                    ->sum(fn (BarisBeban $b): int => $b->sksRatus),
            ])
            ->all();

        return $per + ['total' => (int) array_sum($per)];
    }

    /**
     * Which of the campus's thresholds this sheet fails, if any.
     *
     * Returns sentences rather than a boolean. A report is assessed element by
     * element, so "does not qualify" without saying which element leaves the
     * lecturer with nothing to act on — and the element that fails is almost
     * always research, which takes a semester to fix rather than an afternoon.
     *
     * @param array<string, int> $ringkas
     * @return array<int, string>
     */
    public function pelanggaranBatas(array $ringkas): array
    {
        $batas = config('bkd.batas');
        $pesan = [];

        $format = fn (int $ratus): string => number_format($ratus / 100, 2, ',', '.');

        if ($ringkas['total'] < $batas['minimum_ratus']) {
            $pesan[] = sprintf(
                'Total %s SKS, di bawah batas minimum %s SKS.',
                $format($ringkas['total']),
                $format($batas['minimum_ratus']),
            );
        }

        /*
         * Over the ceiling is a finding, not an error.
         *
         * A lecturer genuinely carrying twenty credits has a workload problem
         * worth surfacing to whoever allocates classes; refusing the report
         * would only hide it, and the excess is real work that was really done.
         */
        if ($batas['maksimum_ratus'] > 0 && $ringkas['total'] > $batas['maksimum_ratus']) {
            $pesan[] = sprintf(
                'Total %s SKS, melampaui batas maksimum %s SKS.',
                $format($ringkas['total']),
                $format($batas['maksimum_ratus']),
            );
        }

        foreach ([
            UnsurBkd::Pendidikan->value => $batas['minimum_pendidikan_ratus'],
            UnsurBkd::Penelitian->value => $batas['minimum_penelitian_ratus'],
        ] as $unsur => $minimum) {
            if ($minimum > 0 && $ringkas[$unsur] < $minimum) {
                $pesan[] = sprintf(
                    'Unsur %s %s SKS, di bawah batas minimum %s SKS.',
                    UnsurBkd::from($unsur)->label(),
                    $format($ringkas[$unsur]),
                    $format($minimum),
                );
            }
        }

        return $pesan;
    }

    /**
     * Classes taught, at the share this lecturer actually carries.
     *
     * `porsi_sks` on the pivot wins whenever it is set — the campus has already
     * stated the split explicitly, and recomputing over the top would discard a
     * decision somebody made. The fallback divides the class evenly, because
     * giving each co-teacher the full credit makes one class count twice at
     * campus level.
     *
     * @return Collection<int, BarisBeban>
     */
    private function mengajar(Dosen $dosen, TahunAkademik $term): Collection
    {
        $bobot = config('bkd.bobot.mengajar');

        return KelasKuliah::query()
            ->with(['mataKuliah', 'dosen'])
            ->where('tahun_akademik_id', $term->id)
            ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosen->id))
            ->get()
            ->map(function (KelasKuliah $kelas) use ($dosen, $bobot): BarisBeban {
                $pengampu = $kelas->dosen->firstWhere('id', $dosen->id);
                $porsi = $pengampu?->pivot->porsi_sks;

                $sksRatus = match (true) {
                    $porsi !== null => (int) round((float) $porsi * 100),
                    $pengampu?->pivot->peran === 'praktisi' => (int) $bobot['porsi_praktisi_ratus'],
                    (bool) $bobot['bagi_rata'] => (int) round($kelas->sks * 100 / max(1, $kelas->dosen->count())),
                    default => (int) round($kelas->sks * 100),
                };

                return new BarisBeban(
                    unsur: UnsurBkd::Pendidikan,
                    kegiatan: 'Mengajar '.$kelas->mataKuliah->nama,
                    rincian: sprintf(
                        '%s · Kelas %s · %d SKS · %s',
                        $kelas->mataKuliah->kode,
                        $kelas->nama,
                        $kelas->sks,
                        $pengampu?->pivot->peran ?? 'pengampu',
                    ),
                    sksRatus: $sksRatus,
                    otomatis: true,
                );
            });
    }

    /**
     * Final projects supervised during this term.
     *
     * A project spans semesters, so the question is not "which term does it
     * belong to" but "was it running while this one was". A project started
     * last year and still open counts now — that is when the supervision
     * actually happens.
     *
     * @return Collection<int, BarisBeban>
     */
    private function membimbing(Dosen $dosen, TahunAkademik $term): Collection
    {
        $bobot = config('bkd.bobot.bimbingan_ta');

        $baris = Pembimbing::query()
            ->with(['tugasAkhir.mahasiswa'])
            ->where('dosen_id', $dosen->id)
            ->whereHas('tugasAkhir', function ($q) use ($term): void {
                // whereDate, not where: SQLite has no DATE type and a plain
                // comparison against a cast column silently matches nothing.
                $q->whereDate('tanggal_pengajuan', '<=', $term->tanggal_selesai)
                    ->where(fn ($s) => $s
                        ->whereNull('tanggal_selesai')
                        ->orWhereDate('tanggal_selesai', '>=', $term->tanggal_mulai));
            })
            ->get()

            // The cap is applied to the rows themselves rather than to the total,
            // so the sheet shows exactly which students were counted. A capped
            // total with eleven names above it is a number nobody can check.
            ->take((int) $bobot['maks_mahasiswa']);

        return $baris->map(fn (Pembimbing $p): BarisBeban => new BarisBeban(
            unsur: UnsurBkd::Pendidikan,
            kegiatan: 'Membimbing tugas akhir · '.$p->tugasAkhir->mahasiswa->nama,
            rincian: $p->peran->label().' · '.$p->tugasAkhir->mahasiswa->nim,
            sksRatus: $p->peran === PeranPembimbing::Utama
                ? (int) $bobot['utama_ratus']
                : (int) $bobot['pendamping_ratus'],
            otomatis: true,
        ));
    }

    /**
     * Defences examined, dated inside this term.
     *
     * Cancelled sessions are excluded: a defence that did not happen is not work
     * that was done, and counting it would be the examining equivalent of
     * recording a verdict for a session nobody attended.
     *
     * @return Collection<int, BarisBeban>
     */
    private function menguji(Dosen $dosen, TahunAkademik $term): Collection
    {
        $bobot = (int) config('bkd.bobot.menguji_ratus');

        $baris = Penguji::query()
            ->with(['ujian.tugasAkhir.mahasiswa'])
            ->where('dosen_id', $dosen->id)
            ->whereHas('ujian', fn ($q) => $q
                ->whereDate('tanggal', '>=', $term->tanggal_mulai)
                ->whereDate('tanggal', '<=', $term->tanggal_selesai)
                ->where('status', '!=', StatusUjian::Dibatalkan->value))
            ->get()
            ->take((int) config('bkd.bobot.menguji_maks_mahasiswa'));

        return $baris->map(fn (Penguji $p): BarisBeban => new BarisBeban(
            unsur: UnsurBkd::Pendidikan,
            kegiatan: 'Menguji '.$p->ujian->jenis->label().' · '.$p->ujian->tugasAkhir->mahasiswa->nama,
            rincian: $p->peran->label().' · '.$p->ujian->tanggal->format('d/m/Y'),
            sksRatus: $bobot,
            otomatis: true,
        ));
    }

    /**
     * Academic advising, counted by group rather than by head.
     *
     * Advising twelve students and twenty-four is the same shape of work done
     * twice, not twice the work per student — so the unit is the group.
     *
     * Known limit, stated rather than hidden: this counts advisees as they stand
     * today, not as they stood during the term being reported. Open Academic
     * keeps no history of advisor changes, so a report filed for a past semester
     * will use the current roster. Recording that history is worth doing; it is
     * not worth pretending to have done it.
     *
     * @return Collection<int, BarisBeban>
     */
    private function perwalian(Dosen $dosen): Collection
    {
        $bobot = config('bkd.bobot.perwalian');
        $per = max(1, (int) $bobot['mahasiswa_per_rombongan']);

        $jumlah = Mahasiswa::aktif()->where('dosen_wali_id', $dosen->id)->count();

        if ($jumlah === 0 || (int) $bobot['per_rombongan_ratus'] === 0) {
            return collect();
        }

        $rombongan = (int) ceil($jumlah / $per);

        return collect([new BarisBeban(
            unsur: UnsurBkd::Pendidikan,
            kegiatan: 'Perwalian akademik',
            rincian: sprintf('%d mahasiswa · %d rombongan', $jumlah, $rombongan),
            sksRatus: $rombongan * (int) $bobot['per_rombongan_ratus'],
            otomatis: true,
        )]);
    }

    /**
     * The three elements no SIAKAD can derive.
     *
     * `sks_ekuivalen` on the row wins when present — somebody has already
     * decided, and overwriting that with a formula erases the decision. The
     * fallback multiplies a per-element base by the role and reach multipliers,
     * which is what most rubrics do and all of which sit in config.
     *
     * @return Collection<int, BarisBeban>
     */
    private function kegiatanDilaporkan(Dosen $dosen, TahunAkademik $term): Collection
    {
        return PenugasanDosen::query()
            ->where('dosen_id', $dosen->id)
            ->where('tahun_akademik_id', $term->id)
            ->whereNotNull('unsur')

            // Teaching is derived above; a self-reported teaching line would be
            // the same class counted twice.
            ->where('unsur', '!=', UnsurBkd::Pendidikan->value)
            ->orderBy('unsur')
            ->orderBy('tanggal_mulai')
            ->get()
            ->map(fn (PenugasanDosen $p): BarisBeban => new BarisBeban(
                unsur: $p->unsur,
                kegiatan: $p->judul,
                rincian: collect([
                    $p->jenis->label(),
                    $p->peran?->label(),
                    $p->tingkat?->label(),
                    $p->mitra_nama,
                    $p->luaran_identitas,
                ])->filter()->implode(' · ') ?: null,
                sksRatus: $this->sksKegiatan($p),
                otomatis: false,
                penugasanId: $p->id,
                buktiPath: $p->dokumen_path,
            ));
    }

    private function sksKegiatan(PenugasanDosen $penugasan): int
    {
        if ($penugasan->sks_ekuivalen !== null) {
            return (int) round((float) $penugasan->sks_ekuivalen * 100);
        }

        $dasar = (int) (config('bkd.bobot.dasar.'.$penugasan->unsur->value.'_ratus') ?? 0);

        $peran = (float) (config('bkd.bobot.peran.'.($penugasan->peran?->value ?? 'ketua')) ?? 1.0);
        $tingkat = (float) (config('bkd.bobot.tingkat.'.($penugasan->tingkat?->value ?? 'lokal')) ?? 1.0);

        return (int) round($dasar * $peran * $tingkat);
    }
}
