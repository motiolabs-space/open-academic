<?php

declare(strict_types=1);

use App\Enums\SemesterType;

it('menyusun kode semester sesuai encoding PDDIKTI', function () {
    expect(SemesterType::Ganjil->termCode(2026))->toBe('20261')
        ->and(SemesterType::Genap->termCode(2026))->toBe('20262')
        ->and(SemesterType::Antara->termCode(2026))->toBe('20263');
});

it('membaca kembali semester dan tahun dari kode', function () {
    expect(SemesterType::fromTermCode('20262'))->toBe(SemesterType::Genap)
        ->and(SemesterType::startYearFromTermCode('20262'))->toBe(2026);
});

it('menghasilkan kode yang valid untuk seluruh jenis semester', function () {
    foreach (SemesterType::cases() as $semester) {
        expect($semester->termCode(2026))->toBeValidTermCode();
    }
});

it('menamai tahun akademik dengan rentang dua tahun', function () {
    expect(SemesterType::Ganjil->academicYearLabel(2026))->toBe('2026/2027 Ganjil');
});
