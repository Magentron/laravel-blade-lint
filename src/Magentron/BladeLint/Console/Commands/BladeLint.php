<?php
/**
 * Copyright (c) 2017-2018,2023 Derks.IT / Jeroen Derks <jeroen@derks.it> All rights reserved.
 * Unauthorized copying of this file, via any medium is strictly prohibited.
 * Proprietary and confidential.
 *
 * This file is part of Laravel Blade Lint.
 *
 * This file is subject to the terms and conditions defined in file 'LICENSE' (also
 * available as an HTML file: 'LICENSE.html'), which is part of this source code package.
 */

namespace Magentron\BladeLint\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class BladeLint
 *
 * @package Magentron\BladeLint\Console\Commands
 */
class BladeLint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blade:lint
                             {--debug            : Enable debug output, which consists of the compiled templates (PHP code)}
                             {--p|processes=auto : The number of test processes to run.}
                             {path?*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Laravel Blade Lint - syntax checking of blade templates';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Blade Lint by Jeroen Derks Copyright © 2017-2018,2023 GPLv3+ License');

        // get blade files
        $blades  = $this->getBladeFileSizes();
        $count   = count($blades);
        $message = sprintf('Found %u blade templates, processing now...', $count);
        $this->info($message, OutputInterface::VERBOSITY_VERBOSE);

        // check syntax for blade files
        $errorCount = $this->checkBladeSyntaxFiles($blades);
        if (0 === $errorCount) {
            $this->info('All Blade templates OK!');
        } else {
            $message = sprintf('Found %u errors in Blade templates!', $errorCount);
            $this->error($message);
        }

        return $errorCount;
    }

    /**
     * Get blade template path names.
     *
     * @return array
     */
    protected function getBladeFileSizes()
    {
        // get view directories
        $blades         = [];
        $files          = [];
        $paths          = $this->argument('path') ?: config('view.paths');
        $verbosityLevel = $this->getOutput()->getVerbosity();

        if (OutputInterface::VERBOSITY_VERBOSE < $verbosityLevel) {
            $this->output->write('Searching for template files in view directories  :', true, OutputInterface::VERBOSITY_VERBOSE);
        } else {
            $this->output->write('Searching for template files...', false, OutputInterface::VERBOSITY_VERBOSE);
        }

        // get all files in view directories
        /** @var SplFileInfo[] $files */
        foreach ($paths as $path) {
            $this->output->write(' - ' . $path, true, OutputInterface::VERBOSITY_VERY_VERBOSE);
            $files = array_merge($files, is_file($path) ? [$path] : File::allFiles($path));
        }
        if (OutputInterface::VERBOSITY_VERBOSE === $verbosityLevel) {
            $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        }

        // get blade templates
        $this->output->write('Determining blade files...', false, OutputInterface::VERBOSITY_VERBOSE);
        foreach ($files as $file) {
            // ensure the file is a string
            if ($file instanceof SplFileInfo) {
                $file = $file->getPathname();
            }

            if ('.blade.php' === substr(strtolower(File::basename($file)), -10)) {
                $stat = stat($file);
                $blades[$file] = empty($stat) ? 0 : $stat[7];
            }
        }
        $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);

        return $blades;
    }

    /**
     * Check syntax of blade files.
     *
     * @param  array   $blades
     * @return int   Number of files with syntax errors.
     */
    protected function checkBladeSyntaxFiles(array $blades)
    {
        // determine maximum length of path names
        $maxLength = array_reduce(array_keys($blades), function ($maxLength, $item) {
            $length = strlen($item);
            if ($length > $maxLength) {
                return $length;
            }

            return $maxLength;
        });

        // foreach blade template saved compiled version to temporary file and run PHP syntax checker
        $errorCount           = 0;
        $maxMessageLength     = 0;
        $verbosityLevel       = $this->getOutput()->getVerbosity();
        $doListFiles          = OutputInterface::VERBOSITY_VERBOSE < $verbosityLevel;
        $writeNewlineFunction = $doListFiles ? 'writeln' : 'write';

        foreach ($this->splitBladesIntoChunks($blades) as $chunkBlades) {
            foreach ($chunkBlades as $file) {
                $length         = $maxLength - strlen($file);
                $message        = sprintf("Compiling %s ...%s%s\r", $file, str_repeat(' ', $length), str_repeat("\x8", $length));
                $messageLength  = strlen($message) - 1;

                if ($maxMessageLength < $messageLength) {
                    $maxMessageLength = $messageLength;
                }

                $this->output->{$writeNewlineFunction}($message, false, OutputInterface::VERBOSITY_VERBOSE);

                // compile the file and send it to the linter process
                $compiled = Blade::compileString(file_get_contents($file));
                if ($this->input->getOption('debug')) {
                    $this->comment($compiled, OutputInterface::VERBOSITY_QUIET);
                }

                if (! $this->lint($compiled, $output, $error)) {
                    ++$errorCount;
                    $error = str_replace('Standard input code', $file, rtrim($error));
                    $this->error($error, OutputInterface::VERBOSITY_QUIET);
                    $maxMessageLength = 0;
                }
            }
        }

        if (!$doListFiles) {
            $this->output->write(str_repeat(' ', $maxMessageLength) . "\r", false, OutputInterface::VERBOSITY_VERBOSE);
        }

        return $errorCount;
    }

    /**
     * Split the files into chunks to be process per CPU core.
     *
     * @param  array $blades
     * @return array
     */
    protected function splitBladesIntoChunks(array $blades)
    {
        // determine the number of processes
        $processes = $this->option('processes');
        if (is_numeric($processes)) {
            settype($processes, 'integer');
        } else {
            $processes = null;
        }
        if (0 >= $processes) {
            $processes = $this->getNumberOfCores();
        }

        $chunks = [];

        asort($blades, SORT_NUMERIC);
        foreach (array_keys($blades) as $index => $file) {
            $chunks[$index % $processes][] = $file;
        }

        return $chunks;
    }

    /**
     * Lint the given PHP code.
     *
     * @param string      $code   The PHP code you want to lint.
     * @param string|null $stdout The output produced by PHP internal linter.
     * @param string|null $stderr The errors produced by PHP internal linter.
     * @return bool
     */
    protected function lint(string $code, string &$stdout = null, string &$stderr = null)
    {
        $descriptorspec = [
            0 => ['pipe', 'r'], // read from stdin
            1 => ['pipe', 'w'], // write to stdout
            2 => ['pipe', 'w'], // write to stderr
        ];

        // open linter process (php -l)
        $process = proc_open('php -l', $descriptorspec, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('unable to open process \'php -l\'');
        }

        fwrite($pipes[0], $code);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        // it is important that you close any pipes before calling
        // +proc_close in order to avoid a deadlock
        $retval = proc_close($process);

        // zero exit code means no error
        return 0 === $retval;
    }

    /**
     * Determine the number of CPU cores, if possible.
     *
     * @return int
     *
     * @see https://gist.github.com/ezzatron/1321581
     */
    protected function getNumberOfCores()
    {
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if (preg_match_all('/^processor/m', $cpuinfo, $matches)) {
                return count($matches[0]);
            }
        } elseif ('WIN' == strtoupper(substr(PHP_OS, 0, 3))) {
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if (false !== $process) {
                fgets($process);
                $numCpus = intval(fgets($process));

                pclose($process);

                return $numCpus;
            }
        } elseif ('Darwin' === PHP_OS) {
            return (int)system('sysctl -n hw.ncpu');
        } else {
            $process = @popen('sysctl -a', 'rb');
            if (false !== $process) {
                $output = stream_get_contents($process);

                pclose($process);

                if (preg_match('/hw.ncpu: (\d+)/', $output, $matches)) {
                    return intval($matches[1][0]);
                }
            }
        }

        // if undetectable, just use 1 core
        return 1;
    }
}
