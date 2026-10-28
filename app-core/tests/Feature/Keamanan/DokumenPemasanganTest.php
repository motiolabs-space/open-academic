<?php

declare(strict_types=1);

/**
 * The Nginx example in DEPLOYMENT.md keeps app-core/ out of reach.
 *
 * app-core/ sits inside the document root (TATA-LETAK.md). Apache refuses it
 * through two .htaccess files, but Nginx never reads them, and the example
 * used to point `root` at a public/ directory that no longer exists — with no
 * rule for app-core at all. Whoever fixed the root to make it work published
 * the OAuth private key and the logs. The example is the configuration most
 * installations copy, so it is pinned like code.
 */
function blokNginx(): string
{
    $isi = (string) file_get_contents(base_path('../docs/DEPLOYMENT.md'));

    preg_match('/```nginx\n(.*?)```/s', $isi, $m);

    return $m[1] ?? '';
}

it('mengarahkan root ke direktori yang benar-benar ada', function () {
    preg_match('/^\s*root\s+([^;]+);/m', blokNginx(), $m);

    expect($m[1] ?? null)->not->toBeNull()
        ->and($m[1])->not->toEndWith('/public');
});

it('menolak app-core, docs, dan tests sebelum blok PHP', function () {
    $blok = blokNginx();

    $appCore = strpos($blok, 'location ^~ /app-core/');
    $php = strpos($blok, 'location ~ \.php$');

    expect($appCore)->not->toBeFalse()
        ->and($php)->not->toBeFalse()
        ->and($appCore)->toBeLessThan($php)
        ->and($blok)->toContain('location ~ ^/(docs|tests)/');
});
