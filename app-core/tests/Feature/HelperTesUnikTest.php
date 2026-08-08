<?php

declare(strict_types=1);

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
