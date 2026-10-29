<?php

declare(strict_types=1);

namespace App\Services\Bridge;

use App\Enums\EducationLevel;
use App\Enums\JenisLuaran;
use App\Enums\JenisPoin;
use App\Enums\StudentStatus;
use App\Enums\TingkatKegiatan;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Support\KodeSemester;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The `iku_2026` block of `GET /iku-data`: the same counted facts, filed under
 * the twelve indicators of Kepmendiktisaintek 358/M/KEP/2025 (ADR-036).
 *
 * The decree replaced the eight-indicator numbering this endpoint was built
 * on. Some facts moved (graduates went from IKU 1 to IKU 2, off-campus
 * learning from IKU 2 to IKU 3), some indicators have no counterpart any more
 * (practitioners teaching, collaborative classes), and most of the new ones
 * are about institutional governance this system does not hold. So this block
 * says, per indicator, whether the facts are here at all — `ketersediaan` —
 * rather than leaving a dashboard to discover an empty object.
 *
 * Same stance as the legacy keys: counts and buckets, never a weight, a
 * threshold, or a score. The decree weighs off-campus learning by credit band
 * and achievements by rank; the bands are returned, the weights are not.
 *
 * Read with joins into plain rows, one query per population, with degree
 * level resolved from one prodi map: the query count stays fixed however many
 * students the campus has (ADR-034).
 */
class DataIku2026
{
    public const ACUAN = 'Kepmendiktisaintek 358/M/KEP/2025';

    /**
     * What this system holds for each indicator.
     *
     *  - tersedia: the transactional facts are here and returned below.
     *  - sebagian: part of the facts is here; the rest (tracer-study answers,
     *    journal indexing, partner usage letters) is evidence Open Campus
     *    collects.
     *  - belum_tersedia: would belong here, but no record type captures it yet.
     *  - di_luar_cakupan: institutional governance, finance, or programme
     *    reporting — not an academic record (CLAUDE.md §6).
     *
     * @var array<int, array{nama: string, status: string, kunci: ?string}>
     */
    public const KETERSEDIAAN = [
        1 => ['nama' => 'Angka Efisiensi Edukasi', 'status' => 'tersedia', 'kunci' => 'iku_1_efisiensi_edukasi'],
        2 => ['nama' => 'Lulusan bekerja, berwirausaha, atau studi lanjut', 'status' => 'sebagian', 'kunci' => 'iku_2_lulusan'],
        3 => ['nama' => 'Mahasiswa berkegiatan atau berprestasi di luar program studi', 'status' => 'tersedia', 'kunci' => 'iku_3_kegiatan_luar_prodi'],
        4 => ['nama' => 'Dosen dengan rekognisi internasional', 'status' => 'sebagian', 'kunci' => 'iku_4_rekognisi_dosen'],
        5 => ['nama' => 'Luaran kerja sama dan hilirisasi', 'status' => 'sebagian', 'kunci' => 'iku_5_luaran_kerja_sama'],
        6 => ['nama' => 'Publikasi bereputasi internasional', 'status' => 'sebagian', 'kunci' => 'iku_6_publikasi'],
        7 => ['nama' => 'Keterlibatan dalam SDGs', 'status' => 'di_luar_cakupan', 'kunci' => null],
        8 => ['nama' => 'SDM dalam penyusunan kebijakan', 'status' => 'belum_tersedia', 'kunci' => null],
        9 => ['nama' => 'Pendapatan non-UKT', 'status' => 'di_luar_cakupan', 'kunci' => null],
        10 => ['nama' => 'Zona Integritas', 'status' => 'di_luar_cakupan', 'kunci' => null],
        11 => ['nama' => 'Tata kelola dan akuntabilitas', 'status' => 'di_luar_cakupan', 'kunci' => null],
        12 => ['nama' => 'Perencanaan kesejahteraan dosen', 'status' => 'di_luar_cakupan', 'kunci' => null],
    ];

    /** @return array<string, mixed> */
    public function untuk(TahunAkademik $term, string $prodi): array
    {
        $jenjang = Prodi::query()->pluck('jenjang', 'id');
        $prodiId = $prodi === '' ? null : (int) (Prodi::query()->where('kode', $prodi)->value('id') ?? 0);
        $lulusan = $this->lulusanRows($prodiId);

        return [
            'acuan' => self::ACUAN,
            'ketersediaan' => collect(self::KETERSEDIAAN)->mapWithKeys(
                fn (array $iku, int $nomor): array => [(string) $nomor => $iku],
            )->all(),
            'iku_1_efisiensi_edukasi' => $this->efisiensiEdukasi($term, $prodiId, $jenjang, $lulusan),
            'iku_2_lulusan' => $this->lulusan($term, $jenjang, $lulusan),
            'iku_3_kegiatan_luar_prodi' => $this->kegiatanLuarProdi($term, $prodiId, $jenjang),
            ...$this->luaranDosen($term),
        ];
    }

    /**
     * IKU 1 — the two populations the efficiency ratio is built from, per
     * degree level, for the academic year the requested term falls in.
     *
     * The decree measures a year, not a term, so both semesters of that year
     * are read here: a consumer summing two term responses would count every
     * continuing student twice. Graduates are bucketed by how many regular
     * semesters they took here; the standard length is returned beside them
     * and the "on time" cut is the consumer's. Transfers and the statuses the
     * decree leaves out (DO, moved away, long leave) are counted, not removed.
     *
     * @param Collection<int, EducationLevel> $jenjang
     * @param Collection<int, object> $lulusan
     * @return array<string, mixed>
     */
    private function efisiensiEdukasi(TahunAkademik $term, ?int $prodiId, Collection $jenjang, Collection $lulusan): array
    {
        $tahun = substr($term->kode, 0, 4);

        $terdaftar = $this->mahasiswaDari('status_mahasiswa as s', 's.mahasiswa_id', $prodiId)
            ->join('tahun_akademik as t', 't.id', '=', 's.tahun_akademik_id')
            ->whereIn('t.kode', [$tahun.'1', $tahun.'2', $tahun.'3'])
            ->orderBy('t.kode')
            ->get(['s.mahasiswa_id', 's.status', 'm.prodi_id'])
            // Ordered by term, so the last row kept per student is the status
            // the year ends on.
            ->keyBy('mahasiswa_id')
            ->groupBy(fn (object $r): string => $this->kodeJenjang($jenjang, $r->prodi_id));

        $lulus = $lulusan
            ->filter(fn (object $r): bool => str_starts_with((string) $r->kode_lulus, $tahun))
            ->groupBy(fn (object $r): string => $this->kodeJenjang($jenjang, $r->prodi_id));

        return [
            'tahun_akademik' => $tahun.'/'.((int) $tahun + 1),
            'per_jenjang' => $terdaftar->keys()->merge($lulus->keys())->unique()
                ->filter(fn (string $kode): bool => EducationLevel::tryFrom($kode) !== null)
                ->mapWithKeys(function (string $kode) use ($terdaftar, $lulus): array {
                    $j = EducationLevel::from($kode);
                    $diJenjang = $terdaftar->get($kode, collect());
                    $lulusDiJenjang = $lulus->get($kode, collect());

                    return [$kode => [
                        'jenjang' => $j->label(),
                        'masa_studi_standar_semester' => $j->normalSemesters(),
                        'terdaftar' => $diJenjang->count(),
                        'terdaftar_per_status' => $this->cacah($diJenjang, 'status'),
                        'lulus' => $lulusDiJenjang->count(),
                        'lulus_pindahan' => $lulusDiJenjang->whereNotNull('term_masuk_asal')->count(),

                        // A list, not an object keyed by semester count:
                        // numeric keys starting at 0 would serialise as a
                        // bare array.
                        'lulus_per_lama_studi' => $lulusDiJenjang
                            // From first enrolment here to the graduation
                            // term; 0 when either end is unrecorded.
                            ->groupBy(fn (object $r): int => KodeSemester::rentang($r->term_masuk, $r->kode_lulus))
                            ->map(fn ($g, int $semester): array => ['semester' => $semester, 'lulus' => $g->count()])
                            ->sortKeys()
                            ->values(),
                    ]];
                })
                ->sortKeys(),
        ];
    }

    /**
     * IKU 2 — the population a tracer study starts from. Employment, waiting
     * time, and salary band are tracer-study answers collected by Open Campus;
     * whatever status has been fed back is counted, the rest is untraced.
     *
     * @param Collection<int, EducationLevel> $jenjang
     * @param Collection<int, object> $lulusan
     * @return array<string, mixed>
     */
    private function lulusan(TahunAkademik $term, Collection $jenjang, Collection $lulusan): array
    {
        return [
            'total_lulusan' => $lulusan->count(),
            'pada_semester_ini' => $lulusan->where('tahun_akademik_id', $term->id)->count(),
            'per_jenjang' => $lulusan
                ->groupBy(fn (object $r): string => $this->kodeJenjang($jenjang, $r->prodi_id))
                ->map(fn ($g): int => $g->count()),
            'per_tahun_lulus' => $lulusan
                ->groupBy(fn (object $r): string => $r->tanggal_lulus ? substr((string) $r->tanggal_lulus, 0, 4) : 'tidak_diketahui')
                ->map(fn ($g): int => $g->count()),
            'per_status_pekerjaan' => $lulusan
                ->groupBy(fn (object $r): string => $r->status_pekerjaan ?? 'belum_ditelusuri')
                ->map(fn ($g): int => $g->count()),
        ];
    }

    /**
     * IKU 3 — verified off-campus learning and verified achievements.
     *
     * The decree weighs learning by credit band (up to 5, 6–10, above 10) and
     * achievements by level and rank; the bands are returned so the weights,
     * which the ministry revises, stay with the consumer.
     *
     * @param Collection<int, EducationLevel> $jenjang
     * @return array<string, mixed>
     */
    private function kegiatanLuarProdi(TahunAkademik $term, ?int $prodiId, Collection $jenjang): array
    {
        $aktivitas = $this->mahasiswaDari('aktivitas_mahasiswa as a', 'a.mahasiswa_id', $prodiId)
            ->where('a.tahun_akademik_id', $term->id)
            ->where('a.is_verified', true)
            ->whereNull('a.deleted_at')
            ->get(['a.mahasiswa_id', 'a.jenis', 'a.sks_konversi', 'm.prodi_id']);

        $prestasi = $this->mahasiswaDari('poin_mahasiswa as p', 'p.mahasiswa_id', $prodiId)
            ->leftJoin('poin_kategori as k', 'k.id', '=', 'p.poin_kategori_id')
            ->where('p.tahun_akademik_id', $term->id)
            ->where('p.jenis', JenisPoin::Prestasi->value)
            ->where('p.is_verified', true)
            ->whereNull('p.deleted_at')
            ->get(['p.mahasiswa_id', 'k.kode', 'k.tingkat']);

        $aktif = DB::table('mahasiswa')
            ->whereNull('deleted_at')
            ->where('status', StudentStatus::Aktif->value)
            ->when($prodiId !== null, fn (Builder $q) => $q->where('prodi_id', $prodiId))
            ->groupBy('prodi_id')
            ->get(['prodi_id', DB::raw('count(*) as jumlah')]);

        $perMahasiswa = $aktivitas->groupBy('mahasiswa_id');

        return [
            'mahasiswa_aktif_per_jenjang' => $aktif
                ->groupBy(fn (object $r): string => $this->kodeJenjang($jenjang, $r->prodi_id))
                ->map(fn (Collection $g): int => (int) $g->sum('jumlah')),
            'pembelajaran' => [
                'catatan_terverifikasi' => $aktivitas->count(),
                'mahasiswa_terlibat' => $perMahasiswa->count(),
                'mahasiswa_terlibat_per_jenjang' => $perMahasiswa
                    ->groupBy(fn (Collection $g): string => $this->kodeJenjang($jenjang, $g->first()->prodi_id))
                    ->map(fn ($g): int => $g->count()),
                'per_jenis' => $this->cacah($aktivitas, 'jenis'),

                // Credits summed per student first: the decree counts a
                // student's combined activities, not each record on its own.
                'mahasiswa_per_sks_konversi' => $this->bandSks(
                    $perMahasiswa->map(fn (Collection $g): int => (int) $g->sum('sks_konversi')),
                ),
            ],
            'prestasi' => [
                'catatan_terverifikasi' => $prestasi->count(),
                'mahasiswa_terlibat' => $prestasi->pluck('mahasiswa_id')->unique()->count(),
                'per_tingkat' => $prestasi
                    ->groupBy(fn (object $r): string => $r->tingkat ?? 'tidak_disebutkan')
                    ->map(fn ($g): int => $g->count()),
                'per_kategori' => $prestasi
                    ->groupBy(fn (object $r): string => $r->kode ?? 'tidak_disebutkan')
                    ->map(fn ($g): int => $g->count()),
            ],
        ];
    }

    /**
     * IKU 4, 5, and 6 — lecturers' recorded outputs, read three ways.
     *
     * What this system holds is the output, its kind, its reach, and the
     * partner. What the decree also asks for — indexing and quartile, a
     * partner's letter of use, a citation — is evidence a person reviews,
     * which is why all three are `sebagian`.
     *
     * @return array<string, array<string, mixed>>
     */
    private function luaranDosen(TahunAkademik $term): array
    {
        $luaran = DB::table('penugasan_dosen as p')
            ->join('dosen as d', 'd.id', '=', 'p.dosen_id')
            ->whereNull('p.deleted_at')
            ->whereNull('d.deleted_at')
            ->where('p.tahun_akademik_id', $term->id)
            ->where('p.is_verified', true)
            ->whereNotNull('p.luaran_jenis')
            ->get(['p.dosen_id', 'p.luaran_jenis', 'p.tingkat', 'p.mitra_nama', 'p.mitra_jenis', 'd.nuptk']);

        $internasional = $luaran->where('tingkat', TingkatKegiatan::Internasional->value);
        $bermitra = $luaran->filter(fn (object $r): bool => filled($r->mitra_nama));
        $publikasi = $luaran->whereIn('luaran_jenis', [JenisLuaran::Jurnal->value, JenisLuaran::Prosiding->value]);

        return [
            'iku_4_rekognisi_dosen' => [
                'luaran_internasional' => $internasional->count(),
                'dosen_terlibat' => $internasional->pluck('dosen_id')->unique()->count(),
                'dosen_terlibat_ber_nuptk' => $internasional
                    ->filter(fn (object $r): bool => filled($r->nuptk))
                    ->pluck('dosen_id')->unique()->count(),
                'dosen_aktif_ber_nuptk' => Dosen::aktif()->whereNotNull('nuptk')->count(),
                'per_jenis' => $this->cacah($internasional, 'luaran_jenis'),
            ],
            'iku_5_luaran_kerja_sama' => [
                'total_luaran' => $luaran->count(),
                'luaran_bermitra' => $bermitra->count(),
                'per_jenis' => $this->cacah($bermitra, 'luaran_jenis'),
                'per_jenis_mitra' => $bermitra
                    ->groupBy(fn (object $r): string => $r->mitra_jenis ?? 'tidak_disebutkan')
                    ->map(fn ($g): int => $g->count()),
            ],
            'iku_6_publikasi' => [
                'total_publikasi' => $publikasi->count(),
                'per_jenis' => $this->cacah($publikasi, 'luaran_jenis'),
                'per_tingkat' => $publikasi
                    ->groupBy(fn (object $r): string => $r->tingkat ?? 'tidak_disebutkan')
                    ->map(fn ($g): int => $g->count()),
            ],
        ];
    }

    /**
     * Confirmed graduates with what both IKU 1 and IKU 2 need, in one read.
     *
     * @return Collection<int, object>
     */
    private function lulusanRows(?int $prodiId): Collection
    {
        return $this->mahasiswaDari('yudisium as y', 'y.mahasiswa_id', $prodiId)
            ->leftJoin('tahun_akademik as t', 't.id', '=', 'y.tahun_akademik_id')
            ->leftJoin('alumni as al', fn ($j) => $j->on('al.mahasiswa_id', '=', 'y.mahasiswa_id')->whereNull('al.deleted_at'))
            ->where('y.status', 'ditetapkan')
            ->whereNull('y.deleted_at')
            ->get([
                'y.tahun_akademik_id', 'y.tanggal_lulus', 't.kode as kode_lulus',
                'm.prodi_id', 'm.term_masuk', 'm.term_masuk_asal', 'al.status_pekerjaan',
            ]);
    }

    /** A table joined to its live student, optionally narrowed to one prodi. */
    private function mahasiswaDari(string $tabel, string $kolom, ?int $prodiId): Builder
    {
        return DB::table($tabel)
            ->join('mahasiswa as m', 'm.id', '=', $kolom)
            ->whereNull('m.deleted_at')
            ->when($prodiId !== null, fn (Builder $q) => $q->where('m.prodi_id', $prodiId));
    }

    /** @param Collection<int, EducationLevel> $jenjang */
    private function kodeJenjang(Collection $jenjang, mixed $prodiId): string
    {
        return $jenjang->get($prodiId)?->value ?? 'tidak_diketahui';
    }

    /**
     * @param Collection<int, int> $sksPerMahasiswa
     * @return array<string, int>
     */
    private function bandSks(Collection $sksPerMahasiswa): array
    {
        return [
            '0-5' => $sksPerMahasiswa->filter(fn (int $s): bool => $s <= 5)->count(),
            '6-10' => $sksPerMahasiswa->filter(fn (int $s): bool => $s >= 6 && $s <= 10)->count(),
            '11+' => $sksPerMahasiswa->filter(fn (int $s): bool => $s >= 11)->count(),
        ];
    }

    /** @param Collection<int, object> $baris */
    private function cacah(Collection $baris, string $kolom): Collection
    {
        return $baris->groupBy($kolom)->map(fn ($g): int => $g->count());
    }
}
