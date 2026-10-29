<?php

declare(strict_types=1);
use Database\Factories\ProdiFactory;

/**
 * Pest helper functions are global, and PHP fatals on a redeclaration.
 *
 * The failure mode is nasty: a filtered run of the new file passes, so the
 * collision is invisible until somebody runs the whole suite — and then it is
 * not one failing test but a fatal error that stops every test after it. This
 * has now happened three times in this repo (`tagihanUji`, `pesertaKelas`,
 * `kelasUji`), each time discovered the expensive way.
 *
 * So the suite checks itself. One test, one second, and the next collision is
 * reported by name at the point it is introduced rather than as a fatal.
 */
it('tidak punya nama fungsi helper yang bertabrakan antar berkas tes', function () {
    $deklarasi = [];

    $berkas = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('tests'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($berkas as $satu) {
        if ($satu->getExtension() !== 'php') {
            continue;
        }

        preg_match_all(
            '/^function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m',
            (string) file_get_contents($satu->getPathname()),
            $cocok,
        );

        foreach ($cocok[1] as $nama) {
            $deklarasi[$nama][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $satu->getPathname());
        }
    }

    $bentrok = array_filter($deklarasi, fn (array $tempat): bool => count($tempat) > 1);

    expect($bentrok)->toBe([], $bentrok === [] ? '' : sprintf(
        "Nama helper tes berikut dideklarasikan di lebih dari satu berkas:\n%s\n"
            .'Fungsi Pest bersifat global — beri akhiran khas modul, misalnya kelasKurikulumUji().',
        collect($bentrok)
            ->map(fn (array $tempat, string $nama): string => '  '.$nama.' → '.implode(', ', $tempat))
            ->implode("\n"),
    ));
});

/*
 * Same failure class, one level down: a factory that can draw a value a test
 * also pins by hand. ProdiFactory drew from 55201–55299 while ten tests create
 * prodi '55201' themselves, so whether a test passed depended on the dice —
 * three times now (PmbTest and MahasiswaPindahanTest in PR #22, IkuData2026Test
 * in PR #34). Every five-digit prodi code a test writes literally must sit
 * outside the factory's range.
 */
it('tidak membiarkan ProdiFactory membangkitkan kode prodi yang dipatok tes secara tetap', function () {
    $tetap = [];

    $berkas = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('tests'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($berkas as $satu) {
        if ($satu->getExtension() === 'php') {
            preg_match_all("/Prodi::factory\(\)->create\(\[[^\]]*'kode' => '(\d{5})'/", (string) file_get_contents($satu->getPathname()), $cocok);
            array_push($tetap, ...$cocok[1]);
        }
    }

    expect($tetap)->not->toBeEmpty();

    // Each draw from a fresh unique pool: the factory's name list is short,
    // and only the code range is under test here; uniqueness within one test
    // is fake()->unique()'s job.
    $dibangkitkan = collect(range(1, 300))->map(function (): string {
        fake()->unique(true);

        return ProdiFactory::new()->raw(['fakultas_id' => 0])['kode'];
    });

    expect($dibangkitkan->every(fn (string $kode): bool => preg_match('/^8\d{4}$/', $kode) === 1))->toBeTrue()
        ->and($dibangkitkan->intersect($tetap)->values()->all())->toBe([]);
});
