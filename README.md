# Laravel Blade Lint

[![Latest Stable Version](http://poser.pugx.org/magentron/laravel-blade-lint/v)](https://packagist.org/packages/magentron/laravel-blade-lint)
[![Total Downloads](http://poser.pugx.org/magentron/laravel-blade-lint/downloads)](https://packagist.org/packages/magentron/laravel-blade-lint)
[![License](http://poser.pugx.org/magentron/laravel-blade-lint/license)](https://packagist.org/packages/magentron/laravel-blade-lint)
[![StyleCI](https://github.styleci.io/repos/134142812/shield)](https://github.styleci.io/repos/134142812)
<br>
[![Laravel 10.x](https://img.shields.io/badge/Laravel-10.x-green.svg)](https://github.com/laravel/framework/tree/10.x)
[![Laravel 9.x](https://img.shields.io/badge/Laravel-9.x-green.svg)](https://github.com/laravel/framework/tree/9.x)
[![Laravel 8.x](https://img.shields.io/badge/Laravel-8.x-green.svg)](https://github.com/laravel/framework/tree/8.x)
[![Laravel 7.x](https://img.shields.io/badge/Laravel-7.x-green.svg)](https://github.com/laravel/framework/tree/7.x)
[![Laravel 6.x](https://img.shields.io/badge/Laravel-6.x-green.svg)](https://github.com/laravel/framework/tree/6.x)
[![Laravel 5.x](https://img.shields.io/badge/Laravel-5.x-green.svg)](https://github.com/laravel/framework/tree/5.8)

Laravel console command to check syntax of blade templates.

# Requirements

Perhaps it works with lesser versions as well, but this is untested.

- PHP 5.6 or above, with at least the [PCNTL extension](https://www.php.net/manual/en/book.pcntl.php)
- Laravel 5.4 or above

# Installation

Add package via composer:

    composer require --dev magentron/laravel-blade-lint

For Laravel version < 5.5, edit `config/app.php`, add the following to
the `providers` array:

    Magentron\BladeLinter\Providers\ServiceProvider::class,

# Usage

From the command line, run:

    php artisan blade:lint

It attempts to determine automatically the number of CPU cores that your
system has and to use all cores by starting child worker processes
(requires the [PHP POSIX extension](https://www.php.net/manual/en/book.posix.php)).

You can use different levels of verbosity for somewhat more detailed
information.

# Author
 
[Jeroen Derks](https://www.phpfreelancer.nl), a.k.a [Magentron](https://github.com/Magentron)

# License

Laravel Blade Lint is free software: you can redistribute it and/or
modify it under the terms of the GNU General Public License as published
by the Free Software Foundation, either version 3 of the License, or (at
your option) any later version.

Laravel Blade Lint is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with Laravel Blade Lint.  If not, see <http://www.gnu.org/licenses/>.
