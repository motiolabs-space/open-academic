<?php

declare(strict_types=1);

use App\Enums\GradeLetter;

it('memetakan skor ke huruf sesuai skala terkonfigurasi', function (float $skor, string $huruf) {
    expect(GradeLetter::fromScore($skor)->value)->toBe($huruf);
})->with([
    'batas bawah A' => [80.0, 'A'],
    'A' => [95.5, 'A'],
    'AB' => [75.0, 'AB'],
    'B' => [70.0, 'B'],
    'BC' => [66.4, 'BC'],
    'C' => [55.0, 'C'],
    'D' => [45.0, 'D'],
    'E' => [44.9, 'E'],
    'nol' => [0.0, 'E'],
]);

it('memberi bobot mutu sesuai skala', function () {
    expect(GradeLetter::A->weight())->toBe(4.00)
        ->and(GradeLetter::C->weight())->toBe(2.00)
        ->and(GradeLetter::E->weight())->toBe(0.00);
});

it('menganggap semua huruf selain E sebagai lulus', function () {
    foreach (GradeLetter::cases() as $huruf) {
        expect($huruf->isPassing())->toBe($huruf !== GradeLetter::E);
    }
});

it('menerima prasyarat mulai dari nilai minimum yang dikonfigurasi', function () {
    config(['academic.krs.prerequisite_min_grade' => 'C']);

    expect(GradeLetter::B->satisfiesPrerequisite())->toBeTrue()
        ->and(GradeLetter::C->satisfiesPrerequisite())->toBeTrue()
        ->and(GradeLetter::D->satisfiesPrerequisite())->toBeFalse();
});
