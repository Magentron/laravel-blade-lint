# Laravel Blade Lint

[![Laravel 5.7](https://img.shields.io/badge/Laravel-5.7-green.svg)](https://github.com/laravel/framework/tree/5.7)
[![Laravel 5.6](https://img.shields.io/badge/Laravel-5.6-green.svg)](https://github.com/laravel/framework/tree/5.6)
[![Laravel 5.5](https://img.shields.io/badge/Laravel-5.5-green.svg)](https://github.com/laravel/framework/tree/5.5)
[![Laravel 5.4](https://img.shields.io/badge/Laravel-5.4-green.svg)](https://github.com/laravel/framework/tree/5.4)

Laravel console command to check syntax of blade templates.

# Requirements

Perhaps it works with lesser versions as well, but this is untested.

- PHP 5.6 or above
- Laravel 5.4 or above

# Installation

Add package via composer:

    composer require magentron/laravel-blade-lint

For Laravel version < 5.5, edit `config/app.php`, add the following to
the `providers` array:

    Magentron\BladeLinter\Providers\ServiceProvider::class,

# Usage

From the command line, run:

    php artisan blade:lint

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
