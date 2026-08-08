<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Enums\SemesterType;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Support\Portal;
use Illuminate\Support\Facades\DB;

/**
 * The academic calendar — the one row every other module reads.
 *
 * Almost nothing here is CRUD. Which term is active decides what a student
 * sees, which term a lecturer grades into, and which term every Feeder payload
 * is stamped with. Getting it wrong mid-semester does not produce an error
 * message; it produces grades filed against the wrong semester.
 */
class TahunAkademikService
{
    /**
     * Makes one term the active one, and only one.
     *
     * `EnsureTermIsActive` and `Portal::term()` both assume a single row with
     * is_active. Two active terms is not a cosmetic inconsistency: whichever
     * one the database happens to return first becomes the semester a KRS is
     * filed under.
     */
    public function aktifkan(TahunAkademik $term): TahunAkademik
    {
        if ($term->is_locked) {
            throw new AturanAkademikException(
                'Semester yang sudah dikunci tidak dapat diaktifkan kembali. Buka kuncinya lebih dulu.',
            );
        }

        DB::transaction(function () use ($term): void {
            TahunAkademik::query()
                ->where('is_active', true)
                ->whereKeyNot($term->getKey())
                ->update(['is_active' => false]);

            $term->update(['is_active' => true]);
        });

        // The active term is memoised per request; without this the operator
        // would still be looking at the old one on the very next page.
        Portal::lupakanTerm();

        return $term->refresh();
    }

    /**
     * Closes a term to any further change.
     *
     * The point at which a semester's grades stop being editable is an
     * administrative decision with consequences for what has already been
     * reported to PDDIKTI, so it is deliberate and audited rather than implied
     * by a date passing.
     */
    public function kunci(TahunAkademik $term): TahunAkademik
    {
        if ($term->is_active) {
            throw new AturanAkademikException(
                'Semester yang sedang berjalan tidak dapat dikunci. Aktifkan semester lain lebih dulu.',
            );
        }

        $term->update(['is_locked' => true]);
        $term->recordActivity('locked', 'Semester dikunci; tidak ada lagi perubahan yang diizinkan.');

        return $term->refresh();
    }

    public function bukaKunci(TahunAkademik $term, string $alasan): TahunAkademik
    {
        if (blank($alasan)) {
            throw new AturanAkademikException('Pembukaan kunci semester wajib disertai alasan.');
        }

        $term->update(['is_locked' => false]);
        $term->recordActivity('unlocked', 'Kunci semester dibuka. Alasan: '.$alasan);

        return $term->refresh();
    }

    /**
     * Creates a term, deriving what can be derived.
     *
     * The PDDIKTI code is not free text — it is the start year followed by the
     * semester digit, and a typo in it silently misfiles a whole semester of
     * reporting. So it is computed here rather than typed by an operator.
     *
     * @param array<string, mixed> $data
     */
    public function buat(array $data): TahunAkademik
    {
        $semester = $data['semester'] instanceof SemesterType
            ? $data['semester']
            : SemesterType::from((string) $data['semester']);

        $kode = $this->kode((int) $data['tahun_mulai'], $semester);

        if (TahunAkademik::withTrashed()->where('kode', $kode)->exists()) {
            throw new AturanAkademikException("Semester dengan kode {$kode} sudah ada.");
        }

        return TahunAkademik::create([
            ...$this->bersihkan($data),
            'kode' => $kode,
            'semester' => $semester,
            'nama' => $this->nama((int) $data['tahun_mulai'], $semester),
            'is_active' => false,
            'is_locked' => false,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function perbarui(TahunAkademik $term, array $data): TahunAkademik
    {
        if ($term->is_locked) {
            throw new AturanAkademikException('Semester terkunci tidak dapat diubah.');
        }

        $term->update($this->bersihkan($data));

        return $term->refresh();
    }

    /** The PDDIKTI term code: start year followed by the semester digit. */
    public function kode(int $tahunMulai, SemesterType $semester): string
    {
        return $tahunMulai.$semester->value;
    }

    public function nama(int $tahunMulai, SemesterType $semester): string
    {
        return sprintf('%d/%d %s', $tahunMulai, $tahunMulai + 1, $semester->label());
    }

    /**
     * Only the calendar fields, never the flags.
     *
     * is_active and is_locked have their own audited entry points; letting them
     * arrive through a form would route around both.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function bersihkan(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'tahun_mulai',
            'tanggal_mulai',
            'tanggal_selesai',
            'krs_mulai',
            'krs_selesai',
            'krs_perubahan_selesai',
            'nilai_mulai',
            'nilai_selesai',
        ]));
    }
}
