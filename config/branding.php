<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Institution Identity
    |--------------------------------------------------------------------------
    |
    | Open Academic is white-labelled per institution. These values are the
    | fallback defaults; a running installation may override them through the
    | settings table (Pengaturan > Branding), which is read at runtime by the
    | BrandingService and cached.
    |
    */

    'institution' => [
        'name' => env('BRAND_INSTITUTION_NAME', 'Universitas Nusantara Digital'),
        'short_name' => env('BRAND_INSTITUTION_SHORT', 'UND'),

        // PDDIKTI institution code (kode perguruan tinggi).
        'code' => env('BRAND_INSTITUTION_CODE', '001001'),
    ],

    'logo_path' => env('BRAND_LOGO_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Design Tokens ("Midnight Executive")
    |--------------------------------------------------------------------------
    |
    | Only the two brand colours are tenant-configurable. Every other token
    | lives in resources/css/app.css so the visual system stays coherent.
    |
    */

    'colors' => [
        'primary' => env('BRAND_PRIMARY_COLOR', '#1E2761'),
        'accent' => env('BRAND_ACCENT_COLOR', '#C9A961'),
    ],

];
