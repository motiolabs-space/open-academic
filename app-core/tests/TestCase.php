<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Screens are tested without a Vite build.
     *
     * Every portal layout calls @vite, which throws when `build/manifest.json`
     * is absent. On a developer machine the manifest is nearly always there,
     * so the suite looked green for two months while CI — which runs Pest
     * before `npm run build` — answered 500 on every screen test: 170
     * failures, all of them this one. The suite must not depend on an
     * artefact that git ignores.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
