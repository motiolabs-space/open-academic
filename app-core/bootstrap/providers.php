<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FeederServiceProvider;
use App\Providers\IdpServiceProvider;
use App\Providers\SsoServiceProvider;

return [
    AppServiceProvider::class,
    FeederServiceProvider::class,
    IdpServiceProvider::class,
    SsoServiceProvider::class,
];
