<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidTermCode', function () {
    // PDDIKTI term encoding: YYYYS, S = 1 (Ganjil) | 2 (Genap) | 3 (Antara).
    expect((string) $this->value)->toMatch('/^\d{4}[123]$/');

    return $this;
});
