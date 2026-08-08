<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\NeoFeederClient;
use Illuminate\Support\ServiceProvider;

class FeederServiceProvider extends ServiceProvider
{
    /**
     * Which Feeder client the application talks to is a configuration choice,
     * never a code branch inside the sync services. A campus without a Feeder
     * installation runs the whole module against the fake and still sees a real
     * ledger, real validation, and real error handling.
     */
    public function register(): void
    {
        $this->app->singleton(FeederClientInterface::class, fn (): FeederClientInterface => match (config('feeder.driver')) {
            'live' => new NeoFeederClient,
            default => new FakeFeederClient,
        });
    }
}
