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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;

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
     * The number of child worker processes to use.
     *
     * @var int|null
     */
    protected $processCount = null;

    /**
     * Process ID's of the child worker processes.
     *
     * @var int[]
     */
    protected $workerPids = array();

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Blade Lint by Jeroen Derks Copyright © 2017-2018,2023 GPLv3+ License');

        // get blade files
        $blades = $this->getBladeFileSizes();
        $count  = count($blades);

        // determine number of process to use
        /** @var int $processCount */
        $processCount = (int)min($count, $this->getProcessCount());

        $message = sprintf('Found %u blade templates, processing now...%s', $count, (1 < $processCount ? " [{$processCount} processes]" : ''));
        $this->info($message, OutputInterface::VERBOSITY_VERBOSE);

        // check syntax for blade files
        $errorCount = $this->checkBladeSyntaxFiles($blades);
        if (0 === $errorCount) {
            $this->info('All Blade templates OK! ');
        } else {
            $message = sprintf('Found %u errors in Blade templates! ', $errorCount);
            $this->error($message);
        }

        return $errorCount;
    }

    /**
     * Get blade template path names.
     *
     * @return int[]
     */
    protected function getBladeFileSizes()
    {
        // get view directories
        /** @var SplFileInfo[] $files */
        $files = array();

        /** @var string[] $paths */
        $paths = $this->argument('path') ?: config('view.paths');

        $blades         = array();
        $verbosityLevel = $this->getOutput()->getVerbosity();

        if (OutputInterface::VERBOSITY_VERBOSE < $verbosityLevel) {
            $this->output->write('Searching for template files in view directories  :', true, OutputInterface::VERBOSITY_VERBOSE);
        } else {
            $this->output->write('Searching for template files...', false, OutputInterface::VERBOSITY_VERBOSE);
        }

        // get all files in view directories
        foreach ($paths as $path) {
            $this->output->write(' - ' . $path, true, OutputInterface::VERBOSITY_VERY_VERBOSE);
            $files = array_merge($files, is_file($path) ? array($path) : File::allFiles($path));
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
                $stat          = stat($file);
                $blades[$file] = empty($stat) ? 0 : $stat[7];
            }
        }
        $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);

        return $blades;
    }

    /**
     * Check syntax of blade files.
     *
     * @param  int[] $blades
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

        $chunkedBlades = $this->splitBladesIntoChunks($blades);
        $processCount  = count($chunkedBlades);

        $flags = array(
            '--processes' => 1,
            '--quiet'     => true,
        );

        /** @var string $commandName */
        $commandName = $this->getName();

        if (1 === $processCount) {
            $this->installSignalHandlers();
        }

        $errorCount = 0;
        foreach ($chunkedBlades as $files) {
            if (1 === $processCount) {
                $errorCount += $this->processFiles($files, $maxLength);
            } else {
                $parameters         = $flags;
                $parameters['path'] = $files;

                $pid = pcntl_fork();
                if (0 > $pid) {
                    $error = error_get_last();
                    $error = null === $error ? '' : implode(': ', array_filter($error));
                    $this->error("Failed to fork child process: {$error}");
                    break;
                }
                if (0 < $pid) {
                    $this->workerPids[] = $pid;
                } else {
                    return Artisan::call($commandName, $parameters, $this->output);
                }
            }
        }

        if (1 === $processCount) {
            return $errorCount;
        }

        return $this->waitForWorkers();
    }

    /**
     * Process files: foreach blade template saved compiled version to temporary file and run PHP syntax checker.
     *
     * @param  string[] $files
     * @param  int      $maxLength
     * @return int
     */
    protected function processFiles($files, $maxLength)
    {
        $errorCount           = 0;
        $maxMessageLength     = 0;
        $verbosityLevel       = $this->getOutput()->getVerbosity();
        $doListFiles          = OutputInterface::VERBOSITY_VERBOSE < $verbosityLevel;
        $writeNewlineFunction = $doListFiles ? 'writeln' : 'write';

        foreach ($files as $file) {
            $length        = $maxLength - strlen($file);
            $message       = sprintf("Compiling %s ...%s%s\r", $file, str_repeat(' ', $length), str_repeat("\x8", $length));
            $messageLength = strlen($message) - 1;

            if ($maxMessageLength < $messageLength) {
                $maxMessageLength = $messageLength;
            }

            $this->output->{$writeNewlineFunction}($message, false, OutputInterface::VERBOSITY_VERBOSE);

            // compile the file and send it to the linter process
            $contents = @file_get_contents($file);
            $compiled = false === $contents ? '' : Blade::compileString($contents);
            if ($this->input->getOption('debug')) {
                $this->comment($compiled, OutputInterface::VERBOSITY_QUIET);
            }

            $output = $error = '';
            if (! $this->lint($compiled, $output, $error)) {
                ++$errorCount;
                $line   = (string)strtok(trim($output), "\n");
                $output = str_replace('Standard input code', $file, $line);
                $this->error($output, OutputInterface::VERBOSITY_QUIET);
                $maxMessageLength = 0;
            }
        }

        if (! $doListFiles) {
            $this->output->write(str_repeat(' ', $maxMessageLength) . "\r", false, OutputInterface::VERBOSITY_VERBOSE);
        }

        return $errorCount;
    }

    /**
     * Lint the given PHP code.
     *
     * @param  string $code   The PHP code you want to lint.
     * @param  string $stdout The output produced by PHP internal linter.
     * @param  string $stderr The errors produced by PHP internal linter.
     * @return bool
     */
    protected function lint($code, &$stdout, &$stderr)
    {
        $descriptorspec = array(
            0 => array('pipe', 'r'), // read from stdin
            1 => array('pipe', 'w'), // write to stdout
            2 => array('pipe', 'w'), // write to stderr
        );

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
        $exitcode = proc_close($process);

        // zero exit code means no error
        return 0 === $exitcode;
    }

    /**
     * Split the files into chunks to be process per CPU core.
     *
     * @param  int[]      $blades
     * @return string[][]
     */
    protected function splitBladesIntoChunks(array $blades)
    {
        $processCount = $this->getProcessCount();

        // sort the files by size so smaller files are process first
        asort($blades, SORT_NUMERIC);

        // divide the files over max. $processes chunks
        $chunks = array();
        foreach (array_keys($blades) as $index => $file) {
            $chunks[$index % $processCount][] = $file;
        }

        return $chunks;
    }

    /**
     * Retrieve the number of child worker processes to use (1 = no child worker processes).
     *
     * @return int
     */
    protected function getProcessCount()
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
        /** @var int $processes */
        if (1 < $processes && ! extension_loaded('posix')) {
            $this->output->warning('PHP extension posix not loaded, multi-processing support disabled.');
            $processes = 1;
        }

        return $processes;
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
            $cpuinfo = @file_get_contents('/proc/cpuinfo');
            if (false !== $cpuinfo && preg_match_all('/^processor/m', $cpuinfo, $matches)) {
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
            return (int)exec('sysctl -n hw.ncpu');
        } else {
            $process = @popen('sysctl -a', 'rb');
            if (false !== $process) {
                $output = stream_get_contents($process);

                pclose($process);

                if (false !== $output && preg_match('/hw.ncpu: (\d+)/', $output, $matches)) {
                    return intval($matches[1][0]);
                }
            }
        }

        // if undetectable, just use 1 core
        return 1;
    }

    /**
     * Install the signal handlers.
     *
     * @return void
     */
    protected function installSignalHandlers()
    {
        for ($i = 1; $i <= 15; ++$i) {
            if (SIGKILL === $i) {
                continue;
            }

            pcntl_signal($i, array($this, 'signalHandler'), false);
        }
    }

    /**
     * Handle signals in a very basic manner.
     *
     * @param int   $signo
     * @param mixed $siginfo
     *
     * @return void
     */
    public function signalHandler($signo, $siginfo)
    {
        $this->waitForWorkers(true);

        exit(127);
    }

    /**
     * Wait for child worker process to finish.
     *
     * @param  bool $doKill
     * @return int
     */
    protected function waitForWorkers($doKill = false)
    {
        $errorCount = 0;

        foreach ($this->workerPids as $pid) {
            // kill the child process first, if necessary
            if ($doKill) {
                posix_kill($pid, SIGKILL);
            }

            // retrieve the child process status and exit code
            if ($pid === pcntl_waitpid($pid, $status)) {
                /** @var int|false $exitcode */
                $exitcode = pcntl_wexitstatus($status);
                if (false !== $exitcode) {
                    $errorCount = 127 === $exitcode || 127 === $errorCount ? 127 : ($errorCount + $exitcode);
                }
            }
        }

        return $errorCount;
    }
}
