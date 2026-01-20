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

namespace Magentron\BladeLint\Tests\Providers;

use Illuminate\Support\Facades\Artisan;
use Magentron\BladeLint\Tests\TestCase;

/** @psalm-suppress UnusedClass */
final class ServiceProviderTest extends TestCase
{
    public function test_blade_lint_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('blade:lint', $commands);
        $this->assertInstanceOf(
            \Magentron\BladeLint\Console\Commands\BladeLint::class,
            $commands['blade:lint']
        );
    }
}
