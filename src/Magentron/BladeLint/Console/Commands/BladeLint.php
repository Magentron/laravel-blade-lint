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

namespace Magentron\BladeLint\Console\Commands;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;
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
    protected $signature = 'blade:lint';

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
        $this->info('Blade Lint by Jeroen Derks Copyright © 2017-2018 GPLv3+ License');

        // get blade files
        $blades  = $this->getBladeFiles();
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
    protected function getBladeFiles()
    {
        // get view directories
        $blades         = [];
        $files          = [];
        $paths          = config('view.paths');
        $verbosityLevel = $this->getOutput()->getVerbosity();

        if (OutputInterface::VERBOSITY_VERBOSE < $verbosityLevel) {
            $this->output->write('Searching for template files in view directories  :', true, OutputInterface::VERBOSITY_VERBOSE);
        } else {
            $this->output->write('Searching for template files...', false, OutputInterface::VERBOSITY_VERBOSE);
        }

        // get all files in view directories
        foreach ($paths as $path) {
            $this->output->write(' - ' . $path, true, OutputInterface::VERBOSITY_VERY_VERBOSE);
            $files = array_merge($files, File::allFiles($path));
        }
        if (OutputInterface::VERBOSITY_VERBOSE === $verbosityLevel) {
            $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        }

        // get blade templates
        $this->output->write('Determining blade files...', false, OutputInterface::VERBOSITY_VERBOSE);
        foreach ($files as $file) {
            if ('.blade.php' === substr(strtolower(File::basename($file)), -10)) {
                $blades[] = $file;
            }
        }
        $this->output->writeln('', OutputInterface::VERBOSITY_VERBOSE);

        return $blades;
    }

    /**
     * Check syntax of blade files.
     *
     * @param  array   $blades
     * @return integer         Number of files with syntax errors.
     */
    protected function checkBladeSyntaxFiles(array $blades)
    {
        // create temporary file
        $temporaryFile = $this->getTemporaryFile();

        // determine maximum length of path names
        $maxLength = array_reduce($blades, function ($maxLength, $item) {
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

        foreach ($blades as $file)
        {
            $length         = $maxLength - strlen($file);
            $message        = sprintf("Compiling %s ...%s%s\r", $file, str_repeat(' ', $length), str_repeat("\x8", $length));
            $messageLength = strlen($message) - 1;

            if ($maxMessageLength < $messageLength) {
                $maxMessageLength = $messageLength;
            }

            $this->output->{$writeNewlineFunction}($message, false, OutputInterface::VERBOSITY_VERBOSE);

            // compile the file and save it to the temporary file
            file_put_contents($temporaryFile, Blade::compileString(file_get_contents($file)));

            // run PHP lint on the temporary file
            $output  = null;
            $return  = null;
            $command = sprintf('php -l %s 2>&1', escapeshellarg($temporaryFile));
            exec($command, $output, $return);
            if (0 !== $return) {
                $message = str_replace($temporaryFile, $file, trim($output[0]));
                ++$errorCount;

                $this->error($message, OutputInterface::VERBOSITY_QUIET);
                $maxMessageLength = 0;
            }
        }

        if (!$doListFiles) {
            $this->output->write(str_repeat(' ', $maxMessageLength) . "\r", false, OutputInterface::VERBOSITY_VERBOSE);
        }

        return $errorCount;
    }

    /**
     * Get a single temporary file which will be deleted at the end of script execution.
     *
     * @return string
     */
    protected function getTemporaryFile()
    {
        // create temporary file
        $temporaryFile = tempnam(sys_get_temp_dir(), 'blade.lint.' . getmypid() . '.php.');

        // remove it at the end of script execution
        register_shutdown_function(function() use ($temporaryFile) {
            // remove the temporary file
            @unlink($temporaryFile);
        });

        return $temporaryFile;
    }
}
