<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\BarisSimkatmawa;
use App\DTOs\Kemahasiswaan\FilterSimkatmawa;
use App\Enums\JenisPoin;
use App\Enums\KategoriSimkatmawa;
use App\Enums\TingkatSimkatmawa;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Support\EksporTabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Verified achievements, laid out as the four SIMKATMAWA tables (ADR-037 §1).
 *
 * SIMKATMAWA is the ministry's student-affairs reporting system, and since its
 * 2026 guide also where IKU 3 counts achievements from. This class only
 * prepares the tables: there is no sending — a campus uploads or keys them in
 * itself, and nothing here talks to the ministry.
 *
 * Same stance as FeederValidator: every row that would be refused is reported
 * **before** the download, all at once, with the reason, rather than
 * discovered one at a time on the ministry's screen. Rows that would be
 * refused are left out of the file; rows that would be accepted at a lower
 * score are kept and flagged.
 *
 * Column names and rules are this project's reading of the SIMKATMAWA 2026
 * guide (Belmawa, February 2026), written in our own words — the guide itself
 * is not copied into this repository (CLAUDE.md §10).
 */
class EksporSimkatmawa
{
    /**
     * Columns per table, in file order. Header text is in lang/id/simkatmawa.php.
     *
     * @var array<string, array<int, string>>
     */
    public const KOLOM = [
        'kompetisi' => [
            'nim', 'nama', 'prodi_kode', 'prodi', 'judul', 'bidang', 'tingkat', 'moda', 'bentuk',
            'peringkat', 'penyelenggara', 'tanggal_mulai', 'tanggal_selesai', 'jumlah_pt', 'jumlah_negara',
            'tautan_sertifikat', 'tautan_bukti', 'tautan_surat_tugas', 'pembimbing_nidn', 'pembimbing_nama',
        ],
        'rekognisi' => [
            'nim', 'nama', 'prodi_kode', 'prodi', 'jenis_rekognisi', 'judul', 'tingkat', 'penyelenggara',
            'tanggal_mulai', 'tanggal_selesai', 'tautan_sertifikat', 'tautan_bukti',
        ],
        'sertifikasi' => [
            'nim', 'nama', 'prodi_kode', 'prodi', 'judul', 'tingkat', 'penyelenggara', 'nomor_sertifikat',
            'tanggal_mulai', 'tanggal_selesai', 'tautan_sertifikat', 'tautan_bukti',
        ],
        'pembimbing' => [
            'pembimbing_nidn', 'pembimbing_nuptk', 'pembimbing_nama', 'nim', 'nama', 'kategori', 'judul',
            'tingkat', 'peringkat', 'tanggal_mulai',
        ],
    ];

    /**
     * Words the guide says mark an exhibition rather than a contest at
     * international level; such events count as recognition, not competition.
     */
    private const PENANDA_PAMERAN = ['invention', 'innovation', 'exhibition', 'convention'];

    /**
     * All four tables for a period, keyed by category.
     *
     * @return array<string, Collection<int, BarisSimkatmawa>>
     */
    public function tabel(FilterSimkatmawa $filter): array
    {
        $prestasi = $this->query($filter)
            ->where('is_verified', true)
            ->get()
            ->map(fn (PoinMahasiswa $p): BarisSimkatmawa => $this->baris($p));

        $tabel = [];

        foreach (KategoriSimkatmawa::cases() as $kategori) {
            $tabel[$kategori->value] = $kategori === KategoriSimkatmawa::Pembimbing
                ? $this->pembimbing($prestasi)
                : $prestasi->filter(fn (BarisSimkatmawa $b): bool => $b->kategori === $kategori)->values();
        }

        return $tabel;
    }

    /**
     * Claims in the period that would be reported once verified.
     *
     * Shown beside the tables so an empty report reads as "nothing verified
     * yet" when that is what it is, not as "nothing happened".
     */
    public function menungguVerifikasi(FilterSimkatmawa $filter): int
    {
        return $this->query($filter)->menunggu()->count();
    }

    /**
     * One achievement's findings, for the screen that fixes them; null when
     * its catalogue entry is not reported to SIMKATMAWA.
     */
    public function periksa(PoinMahasiswa $poin): ?BarisSimkatmawa
    {
        $poin->loadMissing(['mahasiswa.prodi', 'kategori', 'dosenPembimbing']);

        return $poin->kategori?->simkatmawa_kategori === null ? null : $this->baris($poin);
    }

    /** @return array<int, string> header row of one table */
    public function judul(KategoriSimkatmawa $kategori): array
    {
        return array_map(
            fn (string $kolom): string => __('simkatmawa.kolom.'.$kolom),
            self::KOLOM[$kategori->value],
        );
    }

    /**
     * One table as a file — the reportable rows only.
     *
     * Rows with a galat are left out, because the ministry would refuse them
     * anyway; the screen that offers this download lists them first.
     */
    public function unduh(FilterSimkatmawa $filter, KategoriSimkatmawa $kategori, string $format): StreamedResponse|BinaryFileResponse
    {
        $baris = $this->tabel($filter)[$kategori->value]
            ->filter(fn (BarisSimkatmawa $b): bool => $b->siap())
            ->map(fn (BarisSimkatmawa $b): array => array_values($b->nilai))
            ->values();

        $nama = sprintf('simkatmawa-%s-%s-%s', $kategori->value, $filter->dari->format('Ymd'), $filter->sampai->format('Ymd'));

        return EksporTabel::unduh($nama, $format, $this->judul($kategori), $baris);
    }

    /** @return Builder<PoinMahasiswa> */
    private function query(FilterSimkatmawa $filter): Builder
    {
        return PoinMahasiswa::query()
            ->with(['mahasiswa.prodi', 'kategori', 'dosenPembimbing'])
            ->where('jenis', JenisPoin::Prestasi->value)
            ->whereHas('kategori', fn (Builder $q) => $q->whereNotNull('simkatmawa_kategori'))
            // Half-open on date strings: SQLite stores a date cast as
            // `Y-m-d 00:00:00`, which sorts after a `Y-m-d` upper bound and
            // would drop the period's last day. MySQL compares DATE the same.
            ->where('tanggal', '>=', $filter->dari->toDateString())
            ->where('tanggal', '<', $filter->sampai->addDay()->toDateString())
            ->when($filter->prodiId !== null, fn (Builder $q) => $q->whereHas(
                'mahasiswa',
                fn (Builder $m) => $m->where('prodi_id', $filter->prodiId),
            ))
            ->orderBy('tanggal')
            ->orderBy('id');
    }

    private function baris(PoinMahasiswa $p): BarisSimkatmawa
    {
        $kategori = $p->kategori->simkatmawa_kategori;
        $tingkat = $this->tingkat($p);
        $galat = [];
        $peringatan = [];

        // --- what every category needs ------------------------------------
        if ($tingkat === null) {
            $galat[] = $p->kategori->tingkat === 'internal'
                ? __('simkatmawa.galat.tingkat_internal')
                : __('simkatmawa.galat.tingkat_kosong');
        } elseif (!in_array($tingkat, $kategori->tingkatSah(), true)) {
            $galat[] = __('simkatmawa.galat.tingkat_tidak_sah', ['tingkat' => $tingkat->label(), 'kategori' => $kategori->label()]);
        }

        if (blank($p->penyelenggara)) {
            $galat[] = __('simkatmawa.galat.penyelenggara_kosong');
        }

        if (blank($p->tautan_sertifikat)) {
            $galat[] = __('simkatmawa.galat.sertifikat_kosong');
        }

        foreach (['tautan_sertifikat', 'tautan_bukti', 'tautan_surat_tugas'] as $kolom) {
            if (filled($p->{$kolom}) && !$this->tautanSah((string) $p->{$kolom})) {
                $galat[] = __('simkatmawa.galat.tautan_tidak_sah', ['kolom' => __('simkatmawa.kolom.'.$kolom)]);
            }
        }

        if (blank($p->tautan_bukti)) {
            $peringatan[] = __('simkatmawa.peringatan.tanpa_tautan_bukti');
        }

        if (blank($p->mahasiswa->feeder_id)) {
            $peringatan[] = __('simkatmawa.peringatan.belum_pddikti');
        }

        // --- per category -------------------------------------------------
        if ($kategori === KategoriSimkatmawa::Kompetisi) {
            $this->periksaKompetisi($p, $tingkat, $galat, $peringatan);
        }

        if ($kategori === KategoriSimkatmawa::Rekognisi && $p->jenis_rekognisi === null) {
            $galat[] = __('simkatmawa.galat.jenis_rekognisi_kosong');
        }

        if ($kategori === KategoriSimkatmawa::Sertifikasi && blank($p->nomor_sertifikat)) {
            $galat[] = __('simkatmawa.galat.nomor_sertifikat_kosong');
        }

        return new BarisSimkatmawa($kategori, $p, $this->nilai($kategori, $p, $tingkat), $galat, $peringatan);
    }

    /**
     * @param array<int, string> $galat
     * @param array<int, string> $peringatan
     */
    private function periksaKompetisi(PoinMahasiswa $p, ?TingkatSimkatmawa $tingkat, array &$galat, array &$peringatan): void
    {
        foreach (['bidang_lomba', 'moda_pelaksanaan', 'bentuk_kepesertaan', 'peringkat'] as $kolom) {
            if ($p->{$kolom} === null) {
                $galat[] = __('simkatmawa.galat.wajib_kompetisi', ['kolom' => __('simkatmawa.isian.'.$kolom)]);
            }
        }

        // The contest is scored by its field: institutions below
        // international level, countries at it.
        if ($tingkat === TingkatSimkatmawa::Internasional) {
            if ((int) $p->jumlah_negara_peserta < 1) {
                $galat[] = __('simkatmawa.galat.jumlah_negara_kosong');
            }

            if (collect(self::PENANDA_PAMERAN)->contains(fn (string $kata): bool => str_contains(strtolower($p->judul), $kata))) {
                $peringatan[] = __('simkatmawa.peringatan.judul_pameran');
            }
        } elseif ($tingkat !== null && (int) $p->jumlah_pt_peserta < 1) {
            $galat[] = __('simkatmawa.galat.jumlah_pt_kosong');
        }

        if (blank($p->tautan_surat_tugas)) {
            $peringatan[] = __('simkatmawa.peringatan.tanpa_surat_tugas');
        }

        if ($p->dosen_pembimbing_id === null) {
            $peringatan[] = __('simkatmawa.peringatan.tanpa_pembimbing');
        }
    }

    /**
     * One row per supervised achievement, for the supervisor table.
     *
     * Only achievements that are themselves reportable: a supervision
     * credited for a row the ministry never received points at nothing.
     *
     * @param Collection<int, BarisSimkatmawa> $prestasi
     * @return Collection<int, BarisSimkatmawa>
     */
    private function pembimbing(Collection $prestasi): Collection
    {
        return $prestasi
            ->filter(fn (BarisSimkatmawa $b): bool => $b->poin->dosenPembimbing !== null)
            ->map(function (BarisSimkatmawa $b): BarisSimkatmawa {
                $p = $b->poin;
                $dosen = $p->dosenPembimbing;
                $galat = [];

                if (blank($dosen->nidn) && blank($dosen->nuptk)) {
                    $galat[] = __('simkatmawa.galat.pembimbing_tanpa_nomor');
                }

                if (!$b->siap()) {
                    $galat[] = __('simkatmawa.galat.prestasi_belum_lengkap');
                }

                return new BarisSimkatmawa(KategoriSimkatmawa::Pembimbing, $p, [
                    'pembimbing_nidn' => $dosen->nidn,
                    'pembimbing_nuptk' => $dosen->nuptk,
                    'pembimbing_nama' => $dosen->nama,
                    'nim' => $p->mahasiswa->nim,
                    'nama' => $p->mahasiswa->nama,
                    'kategori' => $b->kategori->label(),
                    'judul' => $p->judul,
                    'tingkat' => $b->nilai['tingkat'],
                    'peringkat' => $p->peringkat?->label(),
                    'tanggal_mulai' => $p->tanggal->toDateString(),
                ], $galat);
            })
            ->values();
    }

    /**
     * The row's own level, or the catalogue's when it says the same thing.
     *
     * The catalogue's nasional and internasional mean what SIMKATMAWA means;
     * its lokal could be a district or a province, so it settles nothing.
     */
    private function tingkat(PoinMahasiswa $p): ?TingkatSimkatmawa
    {
        return $p->tingkat ?? match ($p->kategori->tingkat) {
            'nasional' => TingkatSimkatmawa::Nasional,
            'internasional' => TingkatSimkatmawa::Internasional,
            default => null,
        };
    }

    /** @return array<string, string|int|null> */
    private function nilai(KategoriSimkatmawa $kategori, PoinMahasiswa $p, ?TingkatSimkatmawa $tingkat): array
    {
        $dosen = $p->dosenPembimbing;

        $semua = [
            'nim' => $p->mahasiswa->nim,
            'nama' => $p->mahasiswa->nama,
            'prodi_kode' => $p->mahasiswa->prodi?->kode,
            'prodi' => $p->mahasiswa->prodi?->nama,
            'judul' => $p->judul,
            'bidang' => $p->bidang_lomba?->label(),
            'tingkat' => $tingkat?->label(),
            'moda' => $p->moda_pelaksanaan?->label(),
            'bentuk' => $p->bentuk_kepesertaan?->label(),
            'peringkat' => $p->peringkat?->label(),
            'jenis_rekognisi' => $p->jenis_rekognisi?->label(),
            'penyelenggara' => $p->penyelenggara,
            'nomor_sertifikat' => $p->nomor_sertifikat,
            'tanggal_mulai' => $p->tanggal->toDateString(),
            'tanggal_selesai' => $p->tanggal_selesai?->toDateString(),
            'jumlah_pt' => $p->jumlah_pt_peserta,
            'jumlah_negara' => $p->jumlah_negara_peserta,
            'tautan_sertifikat' => $p->tautan_sertifikat,
            'tautan_bukti' => $p->tautan_bukti,
            'tautan_surat_tugas' => $p->tautan_surat_tugas,
            'pembimbing_nidn' => $dosen?->nidn ?? $dosen?->nuptk,
            'pembimbing_nama' => $dosen?->nama,
        ];

        $nilai = [];

        foreach (self::KOLOM[$kategori->value] as $kolom) {
            $nilai[$kolom] = $semua[$kolom] ?? null;
        }

        return $nilai;
    }

    private function tautanSah(string $tautan): bool
    {
        return filter_var($tautan, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($tautan, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
