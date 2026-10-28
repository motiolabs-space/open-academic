<?php

declare(strict_types=1);

use App\Support\Csv;

it('menetralkan sel teks yang akan dibaca sebagai rumus', function (string $sel) {
    expect(Csv::sel($sel))->toBe("'".$sel);
})->with(['=1+1', '+1+1', '-1+1', '@SUM(A1)', "\t=1", "\r=1", '=HYPERLINK("x")']);

it('membiarkan angka dan teks biasa apa adanya', function (mixed $sel) {
    expect(Csv::sel($sel))->toBe($sel);
})->with([
    'angka negatif' => -150000,
    'teks angka negatif' => '-150000',
    'desimal' => '3.25',
    'teks biasa' => 'Budi Santoso',
    'tanda hubung di tengah' => 'Teknik - Informatika',
    'kosong' => '',
    'null' => null,
]);
