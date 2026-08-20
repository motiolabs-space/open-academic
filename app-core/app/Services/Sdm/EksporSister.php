<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\Models\Sdm\Dosen;
use App\Models\Sdm\JabatanFungsionalDosen;
use App\Models\Sdm\MutasiDosen;
use App\Models\Sdm\PangkatDosen;
use App\Models\Sdm\RiwayatPendidikanDosen;
use App\Models\Sdm\SertifikasiDosen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The lecturer record, one file per SISTER data group.
 *
 * SISTER asks for a person's history, not their current values, and it asks
 * group by group. This produces exactly that shape — while there is still
 * nothing to send it to.
 *
 * Two things it refuses to do, both for the same reason:
 *
 *  1. **It does not export a group the application cannot record.** Four of
 *     the twelve groups have a table and a relation and no way to fill either.
 *     Their export would be an empty file, and an empty file reads as "this
 *     campus has nobody with a professional membership" when it means "this
 *     application cannot store one". The catalogue says which is which.
 *
 *  2. **It does not export family members.** SISTER holds them; a CSV that
 *     circulates by email is a different channel from a ministry submission,
 *     and names and birthdates of a lecturer's children have no business in
 *     the careless one. Same rule that already keeps NIK out of the shared
 *     shape.
 *
 * @see docs/BKD-SISTER.md
 */
class EksporSister
{
    /**
     * Every SISTER group, whether it can be exported, and why not when it
     * cannot.
     *
     * Deliberately includes the groups that produce nothing. A catalogue that
     * listed only what works would let a campus believe its portfolio is
     * complete because everything it can see is green.
     *
     * @return array<string, array{label: string, tersedia: bool, alasan: string|null, catatan: string|null, baris: int}>
     */
    public function katalog(): array
    {
        $katalog = [];
        $jumlah = $this->jumlahSemua();

        foreach (self::GRUP as $kunci => $meta) {
            $katalog[$kunci] = [
                'label' => $meta['label'],
                'tersedia' => $meta['tersedia'],
                'alasan' => $meta['alasan'],

                // Carried beside an available group whose count of zero would
                // otherwise be read as a fact about the campus.
                'catatan' => $meta['catatan'] ?? null,

                'baris' => $meta['tersedia'] ? ($jumlah[$kunci] ?? 0) : 0,
            ];
        }

        return $katalog;
    }

    /** One group as CSV. */
    public function csv(string $grup): StreamedResponse
    {
        $meta = self::GRUP[$grup] ?? null;

        if ($meta === null || !$meta['tersedia']) {
            throw new RuntimeException(sprintf(
                'Grup SISTER "%s" tidak dapat diekspor: %s',
                $grup,
                $meta['alasan'] ?? 'grup tidak dikenal.',
            ));
        }

        return $this->alirkan("sister-{$grup}.csv", $meta['header'], $this->baris($grup));
    }

    /* ---------------------------------------------------------------------
     | Groups
     |-------------------------------------------------------------------- */

    /**
     * @var array<string, array{label: string, tersedia: bool, alasan: string|null, catatan?: string, header: array<int, string>}>
     */
    private const GRUP = [

        'biodata' => [
            'label' => 'Biodata Dosen',
            'tersedia' => true,
            'alasan' => null,
            'header' => ['NIDN', 'NIP', 'Nama', 'Gelar Depan', 'Gelar Belakang',
                'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Program Studi',
                'Status Kepegawaian', 'Pendidikan Tertinggi', 'Praktisi'],
        ],

        'riwayat_pendidikan' => [
            'label' => 'Riwayat Pendidikan',
            'tersedia' => true,
            'alasan' => null,
            'header' => ['NIDN', 'Nama', 'Jenjang', 'Perguruan Tinggi', 'Program Studi',
                'Bidang Ilmu', 'Negara', 'Luar Negeri', 'Tahun Masuk', 'Tahun Lulus',
                'Gelar', 'Nomor Ijazah'],
        ],

        'jabatan_fungsional' => [
            'label' => 'Jabatan Fungsional',
            'tersedia' => true,
            'alasan' => null,
            'header' => ['NIDN', 'Nama', 'Jabatan', 'Angka Kredit', 'Nomor SK',
                'Tanggal SK', 'TMT', 'Berlaku'],
        ],

        /*
         * Pangkat and mutasi carry a caveat rather than a refusal.
         *
         * Their export works and RiwayatKepegawaianService can write them, but
         * nothing in the application calls it — no screen, no route. So a
         * campus reads "0 baris" and concludes it has no promotions on record,
         * when what it has is no way to record one. The count is true and the
         * conclusion is false, which is why the note travels with the row.
         */
        'pangkat' => [
            'label' => 'Pangkat & Golongan',
            'tersedia' => true,
            'alasan' => null,
            'catatan' => 'Servisnya siap, tetapi belum ada layar untuk mencatat kenaikan pangkat.',
            'header' => ['NIDN', 'Nama', 'Pangkat', 'Golongan', 'TMT', 'Nomor SK', 'Tanggal SK'],
        ],

        'sertifikasi' => [
            'label' => 'Sertifikasi & Pelatihan',
            'tersedia' => true,
            'alasan' => null,
            'header' => ['NIDN', 'Nama', 'Jenis', 'Nama Sertifikat', 'Nomor',
                'Penyelenggara', 'Bidang', 'Tanggal', 'Berlaku Sampai'],
        ],

        'mutasi' => [
            'label' => 'Mutasi & Penempatan',
            'tersedia' => true,
            'alasan' => null,
            'catatan' => 'Servisnya siap, tetapi belum ada layar untuk mencatat mutasi.',
            'header' => ['NIDN', 'Nama', 'Jenis', 'Unit Asal', 'Unit Tujuan',
                'TMT', 'Nomor SK', 'Keterangan'],
        ],

        /*
         * Below: groups whose tables exist and whose data cannot get in.
         *
         * Listed rather than omitted. A group missing from the screen looks
         * like a group SISTER does not ask about.
         */

        'penghargaan_sanksi' => [
            'label' => 'Penghargaan & Sanksi',
            'tersedia' => false,
            'alasan' => 'Tabelnya ada, tetapi belum ada layar untuk mengisinya.',
            'header' => [],
        ],

        'bahasa' => [
            'label' => 'Kemampuan Bahasa',
            'tersedia' => false,
            'alasan' => 'Tabelnya ada, tetapi belum ada layar untuk mengisinya.',
            'header' => [],
        ],

        'organisasi' => [
            'label' => 'Organisasi Profesi',
            'tersedia' => false,
            'alasan' => 'Tabelnya ada, tetapi belum ada layar untuk mengisinya.',
            'header' => [],
        ],

        'keluarga' => [
            'label' => 'Anggota Keluarga',
            'tersedia' => false,
            'alasan' => 'Sengaja tidak diekspor: nama dan tanggal lahir anggota keluarga '
                .'tidak dikirim lewat berkas yang beredar melalui surel.',
            'header' => [],
        ],

    ];

    /* ---------------------------------------------------------------------
     | Counting
     |-------------------------------------------------------------------- */

    /**
     * How many rows each group would export — every group, in one query.
     *
     * Two earlier versions were wrong in the same direction. The first called
     * baris() and counted the result, hydrating every lecturer and every child
     * row of six groups to display six numbers. The second issued one COUNT per
     * group, which took the BKD screen from 16 queries to 22 against a budget
     * of 20. Both were caught by the query-budget test, which is what it is
     * for.
     *
     * Each count is a scalar subquery, assembled from the models themselves
     * rather than hand-written SQL — so soft deletes and the active scope come
     * along, and none of it drifts when they change.
     *
     * @return array<string, int>
     */
    private function jumlahSemua(): array
    {
        /** @var array<string, class-string<Model>> $anak */
        $anak = [
            'riwayat_pendidikan' => RiwayatPendidikanDosen::class,
            'jabatan_fungsional' => JabatanFungsionalDosen::class,
            'pangkat' => PangkatDosen::class,
            'sertifikasi' => SertifikasiDosen::class,
            'mutasi' => MutasiDosen::class,
        ];

        $query = DB::query()->selectSub(
            Dosen::aktif()->selectRaw('count(*)')->getQuery(),
            'biodata',
        );

        foreach ($anak as $kunci => $model) {
            // Counted through the lecturer rather than on the child table
            // alone: rows belonging to an inactive lecturer are not exported,
            // and counting them would promise more than the file delivers.
            $query->selectSub(
                $model::query()
                    ->whereHas('dosen', fn (Builder $q): Builder => $q->aktif())
                    ->selectRaw('count(*)')
                    ->getQuery(),
                $kunci,
            );
        }

        return array_map(intval(...), (array) $query->first());
    }

    /* ---------------------------------------------------------------------
     | Rows
     |-------------------------------------------------------------------- */

    /** @return Collection<int, array<int, mixed>> */
    private function baris(string $grup): Collection
    {
        return match ($grup) {
            'biodata' => $this->biodata(),
            'riwayat_pendidikan' => $this->riwayatPendidikan(),
            'jabatan_fungsional' => $this->jabatanFungsional(),
            'pangkat' => $this->pangkat(),
            'sertifikasi' => $this->sertifikasi(),
            'mutasi' => $this->mutasi(),
            default => collect(),
        };
    }

    /** @return Collection<int, array<int, mixed>> */
    private function biodata(): Collection
    {
        return $this->dosen(['prodi'])->map(fn (Dosen $d): array => [
            $d->nidn,
            $d->nip,
            $d->namaLengkap(),
            $d->gelar_depan,
            $d->gelar_belakang,
            $d->jenis_kelamin?->value,
            $d->tempat_lahir,
            $d->tanggal_lahir?->toDateString(),
            $d->prodi?->kode,
            $d->status_kepegawaian,
            $d->pendidikan_tertinggi?->value,
            $d->is_praktisi ? 'ya' : 'tidak',
        ]);
    }

    /** @return Collection<int, array<int, mixed>> */
    private function riwayatPendidikan(): Collection
    {
        return $this->dosen(['riwayatPendidikan'])
            ->flatMap(fn (Dosen $d): array => $d->riwayatPendidikan
                ->map(fn ($r): array => [
                    $d->nidn,
                    $d->namaLengkap(),
                    $r->jenjang->value,
                    $r->perguruan_tinggi,
                    $r->program_studi,
                    $r->bidang_ilmu,
                    $r->negara,
                    $r->luarNegeri() ? 'ya' : 'tidak',
                    $r->tahun_masuk,
                    $r->tahun_lulus,
                    $r->gelar,
                    $r->nomor_ijazah,
                ])->all());
    }

    /** @return Collection<int, array<int, mixed>> */
    private function jabatanFungsional(): Collection
    {
        return $this->dosen(['riwayatJabatan'])
            ->flatMap(fn (Dosen $d): array => $d->riwayatJabatan
                ->map(fn ($j): array => [
                    $d->nidn,
                    $d->namaLengkap(),
                    $j->jabatan->value,
                    $j->angkaKredit(),
                    $j->nomor_sk,
                    $j->tanggal_sk?->toDateString(),
                    $j->tmt->toDateString(),
                    $j->berlaku() ? 'ya' : 'tidak',
                ])->all());
    }

    /** @return Collection<int, array<int, mixed>> */
    private function pangkat(): Collection
    {
        return $this->dosen(['pangkat'])
            ->flatMap(fn (Dosen $d): array => $d->pangkat
                ->sortByDesc('tmt')
                ->map(fn ($p): array => [
                    $d->nidn,
                    $d->namaLengkap(),
                    $p->pangkat,
                    $p->golongan,
                    $p->tmt->toDateString(),
                    $p->nomor_sk,
                    $p->tanggal_sk?->toDateString(),
                ])->values()->all());
    }

    /** @return Collection<int, array<int, mixed>> */
    private function sertifikasi(): Collection
    {
        return $this->dosen(['sertifikasi'])
            ->flatMap(fn (Dosen $d): array => $d->sertifikasi
                ->map(fn ($s): array => [
                    $d->nidn,
                    $d->namaLengkap(),
                    $s->jenis->value,
                    $s->nama,
                    $s->nomor,
                    $s->penyelenggara,
                    $s->bidang,
                    $s->tanggal->toDateString(),
                    $s->berlaku_sampai?->toDateString(),
                ])->all());
    }

    /** @return Collection<int, array<int, mixed>> */
    private function mutasi(): Collection
    {
        return $this->dosen(['mutasi.unitAsal', 'mutasi.unitTujuan'])
            ->flatMap(fn (Dosen $d): array => $d->mutasi
                ->sortByDesc('tmt')
                ->map(fn ($m): array => [
                    $d->nidn,
                    $d->namaLengkap(),
                    $m->jenis,
                    $m->unitAsal?->nama,
                    $m->unitTujuan?->nama,
                    $m->tmt->toDateString(),
                    $m->nomor_sk,
                    $m->keterangan,
                ])->values()->all());
    }

    /**
     * @param array<int, string> $relasi
     * @return Collection<int, Dosen>
     */
    private function dosen(array $relasi): Collection
    {
        return Dosen::aktif()->with($relasi)->orderBy('nama')->get();
    }

    /* ---------------------------------------------------------------------
     | Output
     |-------------------------------------------------------------------- */

    /**
     * @param array<int, string> $header
     * @param Collection<int, array<int, mixed>> $baris
     */
    private function alirkan(string $namaBerkas, array $header, Collection $baris): StreamedResponse
    {
        $pemisah = (string) config('bkd.ekspor.pemisah_csv');
        $bom = (bool) config('bkd.ekspor.bom_utf8');

        return response()->streamDownload(function () use ($header, $baris, $pemisah, $bom): void {
            $keluaran = fopen('php://output', 'wb');

            // Same reason as the BKD export: without the BOM, Excel on an
            // Indonesian Windows install renders every accented name as rubbish.
            if ($bom) {
                fwrite($keluaran, "\xEF\xBB\xBF");
            }

            fputcsv($keluaran, $header, $pemisah, '"', '\\');

            foreach ($baris as $satu) {
                fputcsv($keluaran, $satu, $pemisah, '"', '\\');
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
