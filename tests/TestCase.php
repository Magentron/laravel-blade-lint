<?php

/**
 * Copyright (c) 2026 Derks.IT / Jeroen Derks <jeroen@derks.it> All rights reserved.
 * Unauthorized copying of this file, via any medium is strictly prohibited.
 * Proprietary and confidential.
 *
 * This file is part of Laravel Blade Lint.
 *
 * This file is subject to the terms and conditions defined in file 'LICENSE' (also
 * available as an HTML file: 'LICENSE.html'), which is part of this source code package.
 */

declare(strict_types=1);

namespace Magentron\BladeLint\Tests;

use Magentron\BladeLint\Providers\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return array(
            ServiceProvider::class,
        );
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    #[\Override]
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default view paths for testing
        $app['config']->set('view.paths', array(
            __DIR__ . '/fixtures/views',
        ));
    }
}
