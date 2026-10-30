<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\BarisLulusan;
use App\DTOs\Kemahasiswaan\FilterLulusan;
use App\DTOs\Kemahasiswaan\KontakLulusanData;
use App\Jobs\RecordActivityLogJob;
use App\Models\Kemahasiswaan\Alumni;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Support\EksporTabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Graduates as the national tracer study needs them (ADR-037 §2).
 *
 * The tracer study is answered by graduates themselves on the ministry's site,
 * signing in with their student number and the data the campus vouches for.
 * What the campus owes it is a list that is complete and contactable — so
 * this class checks every graduate's contact details, produces the list, and
 * records each time somebody downloads it, because the file holds personal
 * data of people who no longer have an account here to see it from.
 *
 * Not here: the questionnaire, its answers beyond the employment status, and
 * any IKU arithmetic. Those belong to Open Campus (CLAUDE.md §1, §6).
 */
class DataLulusanService
{
    public const KOLOM_EKSPOR = [
        'nim', 'nama', 'prodi_kode', 'prodi', 'jenjang', 'tahun_masuk', 'tahun_lulus', 'tanggal_lulus',
        'surel', 'telepon', 'kontak_diverifikasi', 'catatan',
    ];

    /**
     * Every graduate in the filter, with what is wrong with their contact.
     *
     * @return Collection<int, BarisLulusan>
     */
    public function daftar(FilterLulusan $filter): Collection
    {
        $baris = $this->query($filter)->get()->map(fn (Alumni $a): BarisLulusan => $this->periksa($a));

        return $filter->hanyaPerluVerifikasi
            ? $baris->filter(fn (BarisLulusan $b): bool => $b->perluVerifikasi())->values()
            : $baris;
    }

    /**
     * Graduation years present, newest first, for the filter.
     *
     * @return array<int, int>
     */
    public function tahunLulus(): array
    {
        return Alumni::query()->distinct()->orderByDesc('tahun_lulus')->pluck('tahun_lulus')
            ->map(fn ($t): int => (int) $t)->all();
    }

    /**
     * One graduate's contact, judged.
     *
     * Three outcomes: complete (a personal address, a phone that looks like an
     * Indonesian number, confirmed recently), needing verification, or no
     * contact at all. The campus account address counts as a contact but not
     * a complete one: it is the one that stops working after graduation.
     */
    public function periksa(Alumni $alumni): BarisLulusan
    {
        $tanda = [];
        $surel = $alumni->email_pribadi;
        $telepon = $alumni->telepon;

        if (blank($surel)) {
            $tanda[] = 'surel_kosong';
        } elseif (filter_var($surel, FILTER_VALIDATE_EMAIL) === false) {
            $tanda[] = 'surel_tidak_sah';
        } elseif (strcasecmp((string) $surel, (string) $alumni->mahasiswa?->email) === 0) {
            $tanda[] = 'surel_kampus';
        }

        if (blank($telepon)) {
            $tanda[] = 'telepon_kosong';
        } elseif (!self::teleponSah((string) $telepon)) {
            $tanda[] = 'telepon_tidak_sah';
        }

        if ($alumni->kontak_diverifikasi_at === null) {
            $tanda[] = 'belum_diverifikasi';
        } elseif ($alumni->kontak_diverifikasi_at->lt(now()->subMonths($this->masaBerlakuBulan()))) {
            $tanda[] = 'verifikasi_lama';
        }

        if ($alumni->kontak_tidak_dibagikan) {
            $tanda[] = 'tidak_dibagikan';
        }

        $status = match (true) {
            blank($surel) && blank($telepon) => BarisLulusan::KOSONG,
            $tanda === [] => BarisLulusan::LENGKAP,
            default => BarisLulusan::PERLU_VERIFIKASI,
        };

        return new BarisLulusan($alumni, $status, $tanda);
    }

    /**
     * Records the contact details staff confirmed with the graduate.
     *
     * Written to the alumni row, not to the student record: the student row
     * is what PDDIKTI received, and a graduate's new phone number is not a
     * correction to it.
     */
    public function verifikasiKontak(Alumni $alumni, KontakLulusanData $data, Staff $staf): Alumni
    {
        $alumni->update([
            'email_pribadi' => $data->emailPribadi,
            'telepon' => $data->telepon,
            'kontak_tidak_dibagikan' => $data->tidakDibagikan,
            'kontak_diverifikasi_at' => now(),
            'kontak_diverifikasi_by_staff_id' => $staf->id,
        ]);

        return $alumni->refresh();
    }

    /**
     * The tracer list as a file, logged before it is produced.
     *
     * Every graduate in the filter, including those without contact: the
     * tracer study needs the whole population, and a graduate missing from
     * the file is one the ministry counts as untraced without anyone knowing
     * why. Contact cells are blank for a graduate who refused to share them.
     * No NIK, address or date of birth — none is needed to sign in to the
     * tracer study, and this file travels by email.
     */
    public function ekspor(FilterLulusan $filter, string $format, Staff $staf, string $keperluan): StreamedResponse|BinaryFileResponse
    {
        $baris = $this->daftar($filter);

        $this->catatEkspor($filter, $format, $staf, $keperluan, $baris->count());

        $isi = $baris->map(function (BarisLulusan $b): array {
            $a = $b->alumni;
            $m = $a->mahasiswa;
            $bagikan = !$a->kontak_tidak_dibagikan;

            return [
                $m->nim,
                $m->nama,
                $m->prodi?->kode,
                $m->prodi?->nama,
                $m->prodi?->jenjang?->label(),
                $m->angkatan,
                (int) $a->tahun_lulus,
                $m->yudisium?->tanggal_lulus?->toDateString(),
                $bagikan ? $a->email_pribadi : null,
                $bagikan ? $a->telepon : null,
                $a->kontak_diverifikasi_at?->toDateString(),
                collect($b->tanda)->map(fn (string $t): string => __('lulusan.tanda.'.$t, ['bulan' => $this->masaBerlakuBulan()]))->implode('; '),
            ];
        });

        $nama = sprintf('lulusan-tracer-%s-%s', $filter->tahunLulus ?? 'semua', now()->format('Ymd'));

        return EksporTabel::unduh($nama, $format, $this->judulEkspor(), $isi);
    }

    /** @return array<int, string> */
    public function judulEkspor(): array
    {
        return array_map(fn (string $k): string => __('lulusan.kolom.'.$k), self::KOLOM_EKSPOR);
    }

    /**
     * A mobile or landline number as Indonesians write it: 0…, 62… or +62…,
     * then nine to thirteen more digits; spaces, dots and dashes ignored.
     */
    public static function teleponSah(string $telepon): bool
    {
        $angka = preg_replace('/[\s.\-()]/', '', $telepon) ?? '';

        return preg_match('/^(\+62|62|0)[1-9]\d{7,12}$/', $angka) === 1;
    }

    /** @return Builder<Alumni> */
    private function query(FilterLulusan $filter): Builder
    {
        return Alumni::query()
            ->with(['mahasiswa.prodi', 'mahasiswa.yudisium'])
            ->whereHas('mahasiswa', fn (Builder $q) => $q->when(
                $filter->prodiId !== null,
                fn (Builder $m) => $m->where('prodi_id', $filter->prodiId),
            ))
            ->when($filter->tahunLulus !== null, fn (Builder $q) => $q->where('tahun_lulus', $filter->tahunLulus))
            ->orderByDesc('tahun_lulus')
            ->orderBy(
                Mahasiswa::query()->select('nim')->whereColumn('mahasiswa.id', 'alumni.mahasiswa_id'),
            );
    }

    /**
     * One activity-log row per download, filed under a synthetic subject so
     * it shows on the Log Aktivitas screen beside the records it exposed.
     */
    private function catatEkspor(FilterLulusan $filter, string $format, Staff $staf, string $keperluan, int $jumlah): void
    {
        RecordActivityLogJob::dispatch([
            'subject_type' => 'EksporTracer',
            'subject_id' => 0,
            'subject_label' => __('lulusan.ekspor.judul'),
            'event' => 'exported',
            'description' => Str::limit(__('lulusan.ekspor.log', [
                'jumlah' => $jumlah,
                'format' => strtoupper($format),
                'keperluan' => $keperluan,
            ]), 255, ''),
            'changes' => array_filter([
                'tahun_lulus' => $filter->tahunLulus,
                'prodi_id' => $filter->prodiId,
                'jumlah' => $jumlah,
                'format' => $format,
                'keperluan' => $keperluan,
            ], fn ($v): bool => $v !== null),
            'causer_type' => $staf->getMorphClass(),
            'causer_id' => $staf->getKey(),
            'causer_name' => $staf->nama,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    private function masaBerlakuBulan(): int
    {
        return (int) config('kemahasiswaan.lulusan.verifikasi_berlaku_bulan', 12);
    }
}
