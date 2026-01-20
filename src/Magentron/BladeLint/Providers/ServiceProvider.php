<?php

/**
 * Copyright (c) 2017-2018 Derks.IT / Jeroen Derks <jeroen@derks.it> All rights reserved.
 * Unauthorized copying of this file, via any medium is strictly prohibited.
 * Proprietary and confidential.
 *
 * This file is part of Laravel Blade Lint.
 *
 * This file is subject to the terms and conditions defined in file 'LICENSE' (also
 * available as an HTML file: 'LICENSE.html'), which is part of this source code package.
 */

namespace Magentron\BladeLint\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * @psalm-suppress UnusedClass - Laravel service provider loaded via package discovery
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * @var string[]
     */
    protected $commands = array(
        'Magentron\BladeLint\Console\Commands\BladeLint',
    );

    /**
     * Register the service provider.
     *
     * @return void
     *
     * @psalm-suppress MissingOverrideAttribute - Override attribute requires PHP 8.3+
     */
    public function register()
    {
        $this->commands($this->commands);
    }
}
