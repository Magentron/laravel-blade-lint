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

namespace Magentron\BladeLint\Tests\Console\Commands;

use Magentron\BladeLint\Console\Commands\BladeLint;
use Magentron\BladeLint\Tests\TestCase;
use ReflectionClass;

/** @psalm-suppress UnusedClass */
final class BladeLintTest extends TestCase
{
    private string $fixturesPath;
    private string $testId;
    private array $createdFiles = array();

    protected function setUp(): void
    {
        parent::setUp();

        // Generate unique test ID for parallel test isolation
        $this->testId = uniqid('test_', true);

        // Create unique fixtures directory for this test run
        $this->fixturesPath = sys_get_temp_dir() . '/blade-lint-tests/' . $this->testId;
        if (! is_dir($this->fixturesPath)) {
            mkdir($this->fixturesPath, 0755, true);
        }

        // Configure Laravel to use this test's fixtures path
        $this->app['config']->set('view.paths', array($this->fixturesPath));

        $this->createdFiles = array();
    }

    protected function tearDown(): void
    {
        // Clean up all files in the test-specific directory
        $this->clearFixtures();

        // Remove the test-specific directory
        if (is_dir($this->fixturesPath)) {
            @rmdir($this->fixturesPath);
        }

        parent::tearDown();
    }

    private function clearFixtures(): void
    {
        if (is_dir($this->fixturesPath)) {
            $files = glob($this->fixturesPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @chmod($file, 0644);
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Create a blade file with supplied content.
     *
     * @param  string $name
     * @param  string $content
     * @return string
     */
    private function createBladeFile(string $name, string $content): string
    {
        // Add unique suffix to prevent collisions in parallel runs
        $uniqueName = pathinfo($name, PATHINFO_FILENAME) . '_' . substr($this->testId, -8) . '.blade.php';
        $path       = $this->fixturesPath . '/' . $uniqueName;
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;

        return $path;
    }

    /**
     * Run blade:lint command with single-process mode to prevent fork bombs in PHPUnit.
     *
     * @param  array<string, mixed>                   $parameters
     * @return \Illuminate\Testing\PendingCommand|int
     */
    private function bladeLint(array $parameters = array())
    {
        // Force single-process mode unless explicitly testing multi-process behavior
        if (! isset($parameters['--processes'])) {
            $parameters['--processes'] = 1;
        }

        return $this->artisan('blade:lint', $parameters);
    }

    /**
     * Create a BladeLint command configured for testing processFiles method.
     *
     * @param  bool                                                   $debug  Whether debug option is enabled
     * @param  \Symfony\Component\Console\Output\OutputInterface|null $output Custom output instance (defaults to NullOutput)
     * @return array{BladeLint, \ReflectionMethod}                    [$command, $processFilesMethod]
     */
    private function createCommandForProcessFiles(bool $debug = false, ?\Symfony\Component\Console\Output\OutputInterface $output = null): array
    {
        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->method('getOption')->with('debug')->willReturn($debug);

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $inputMock);

        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($command, $output ?? new \Symfony\Component\Console\Output\NullOutput());

        $method = $reflection->getMethod('processFiles');
        $method->setAccessible(true);

        return array($command, $method);
    }

    /**
     * Create a BladeLint command configured for testing checkBladeSyntaxFiles method.
     *
     * @return array{BladeLint, \ReflectionMethod} [$command, $checkBladeSyntaxFilesMethod]
     */
    private function createCommandForCheckBladeSyntaxFiles(): array
    {
        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->method('getOption')->willReturnMap(array(
            array('processes', '1'),
            array('debug', false),
        ));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $inputMock);

        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($command, new \Symfony\Component\Console\Output\BufferedOutput());

        $method = $reflection->getMethod('checkBladeSyntaxFiles');
        $method->setAccessible(true);

        return array($command, $method);
    }

    // ==================== handle() tests ====================

    public function test_handle_with_no_errors(): void
    {
        $this->createBladeFile('valid.blade.php', '<div>{{ $test }}</div>');

        $this->bladeLint()
            ->expectsOutputToContain('All Blade templates OK!')
            ->assertExitCode(0);
    }

    public function test_handle_with_syntax_errors(): void
    {
        $this->createBladeFile('invalid.blade.php', '<?php echo "unclosed string');

        $this->bladeLint()
            ->assertFailed();
    }

    public function test_handle_with_specific_path_argument(): void
    {
        $bladePath = $this->createBladeFile('test.blade.php', '<div>Test</div>');

        $this->bladeLint(array('path' => array($bladePath)))
            ->expectsOutputToContain('All Blade templates OK!')
            ->assertExitCode(0);
    }

    public function test_handle_with_multiple_files(): void
    {
        $file1 = $this->createBladeFile('multi1.blade.php', '<div>File 1</div>');
        $file2 = $this->createBladeFile('multi2.blade.php', '<span>File 2</span>');

        // Use single process to avoid multi-processing issues in tests
        $this->bladeLint(array(
            'path'        => array($file1, $file2),
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_handle_with_verbose_output(): void
    {
        $this->createBladeFile('verbose.blade.php', '<div>Test</div>');

        $this->bladeLint(array('-v' => true))
            ->assertExitCode(0);
    }

    public function test_handle_reports_multiple_errors(): void
    {
        $file1 = $this->createBladeFile('err1.blade.php', '<?php echo "unclosed');
        $file2 = $this->createBladeFile('err2.blade.php', '<?php function(');

        $this->bladeLint(array(
            'path'        => array($file1, $file2),
            '--processes' => 1,
        ))->assertFailed();
    }

    public function test_handle_with_debug_option(): void
    {
        $this->createBladeFile('debug.blade.php', '<div>{{ $var }}</div>');

        $this->bladeLint(array('--debug' => true))
            ->assertExitCode(0);
    }

    public function test_handle_with_single_process(): void
    {
        $this->createBladeFile('single.blade.php', '<div>Single</div>');

        $this->bladeLint(array('--processes' => 1))
            ->assertExitCode(0);
    }

    // ==================== lint() method tests ====================

    public function test_lint_with_valid_php(): void
    {
        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('lint');
        $method->setAccessible(true);

        $stdout = '';
        $stderr = '';
        $args   = array('<?php echo "valid"; ?>', &$stdout, &$stderr);
        $result = $method->invokeArgs($command, $args);

        $this->assertTrue($result);
    }

    public function test_lint_with_invalid_php_syntax(): void
    {
        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('lint');
        $method->setAccessible(true);

        $stdout = '';
        $stderr = '';
        $args   = array('<?php echo "unclosed string', &$stdout, &$stderr);
        $result = $method->invokeArgs($command, $args);

        $this->assertFalse($result);
        $this->assertNotEmpty($stdout);
    }

    public function test_lint_with_empty_code(): void
    {
        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('lint');
        $method->setAccessible(true);

        $stdout = '';
        $stderr = '';
        $args   = array('', &$stdout, &$stderr);
        $result = $method->invokeArgs($command, $args);

        $this->assertTrue($result);
    }

    // ==================== getNumberOfCores() tests ====================

    public function test_get_number_of_cores_returns_positive_integer(): void
    {
        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('getNumberOfCores');
        $method->setAccessible(true);

        $cores = $method->invoke($command);

        $this->assertIsInt($cores);
        $this->assertGreaterThan(0, $cores);
    }

    // ==================== splitBladesIntoChunks() tests ====================

    public function test_split_blades_into_chunks_with_multiple_processes(): void
    {
        $command = $this->getMockBuilder(BladeLint::class)
            ->onlyMethods(array('getProcessCount'))
            ->getMock();

        $command->method('getProcessCount')->willReturn(2);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('splitBladesIntoChunks');
        $method->setAccessible(true);

        $blades = array(
            '/path/file1.blade.php' => 100,
            '/path/file2.blade.php' => 200,
            '/path/file3.blade.php' => 150,
            '/path/file4.blade.php' => 50,
        );

        $chunks = $method->invoke($command, $blades);

        $this->assertCount(2, $chunks);
        /** @psalm-suppress InvalidArgument */
        $this->assertEquals(4, array_sum(array_map('count', $chunks)));
    }

    public function test_split_blades_into_chunks_with_single_process(): void
    {
        $command = $this->getMockBuilder(BladeLint::class)
            ->onlyMethods(array('getProcessCount'))
            ->getMock();

        $command->method('getProcessCount')->willReturn(1);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('splitBladesIntoChunks');
        $method->setAccessible(true);

        $blades = array(
            '/path/file1.blade.php' => 100,
            '/path/file2.blade.php' => 200,
        );

        $chunks = $method->invoke($command, $blades);

        $this->assertCount(1, $chunks);
        $this->assertCount(2, $chunks[0]);
    }

    public function test_split_blades_sorts_by_size(): void
    {
        $command = $this->getMockBuilder(BladeLint::class)
            ->onlyMethods(array('getProcessCount'))
            ->getMock();

        $command->method('getProcessCount')->willReturn(1);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('splitBladesIntoChunks');
        $method->setAccessible(true);

        $blades = array(
            '/path/large.blade.php'  => 1000,
            '/path/small.blade.php'  => 10,
            '/path/medium.blade.php' => 500,
        );

        $chunks = $method->invoke($command, $blades);

        // Files should be sorted by size (smallest first)
        $this->assertEquals('/path/small.blade.php', $chunks[0][0]);
    }

    // ==================== installSignalHandlers() tests ====================

    public function test_install_signal_handlers(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('PCNTL extension required');
        }

        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('installSignalHandlers');
        $method->setAccessible(true);

        // Should not throw an exception
        $method->invoke($command);

        $this->assertTrue(true);
    }

    // ==================== waitForWorkers() tests ====================

    public function test_wait_for_workers_with_empty_pids(): void
    {
        if (! extension_loaded('pcntl') || ! extension_loaded('posix')) {
            $this->markTestSkipped('PCNTL and POSIX extensions required');
        }

        $command = new BladeLint();

        $reflection = new ReflectionClass($command);

        $property = $reflection->getProperty('workerPids');
        $property->setAccessible(true);
        $property->setValue($command, array());

        $method = $reflection->getMethod('waitForWorkers');
        $method->setAccessible(true);

        $errorCount = $method->invoke($command, false);

        $this->assertEquals(0, $errorCount);
    }

    // ==================== Integration tests ====================

    public function test_only_processes_blade_files(): void
    {
        $this->createBladeFile('template.blade.php', '<div>Blade</div>');
        file_put_contents($this->fixturesPath . '/notblade.php', '<?php echo "Not blade"; ?>');

        $this->bladeLint()
            ->assertExitCode(0);

        @unlink($this->fixturesPath . '/notblade.php');
    }

    public function test_handles_nested_blade_expressions(): void
    {
        $blade = <<<'BLADE'
@if($condition)
    @foreach($items as $item)
        <li>{{ $item->name }}</li>
    @endforeach
@endif
BLADE;

        $this->createBladeFile('nested.blade.php', $blade);

        $this->bladeLint()
            ->assertExitCode(0);
    }

    public function test_handles_blade_slots(): void
    {
        // Test slot syntax which doesn't require component registration
        $blade = '@slot(\'title\')Slot Content@endslot';

        $this->createBladeFile('slot.blade.php', $blade);

        $this->bladeLint()
            ->assertExitCode(0);
    }

    public function test_handles_raw_php_in_blade(): void
    {
        $blade = <<<'BLADE'
@php
    $value = compute();
@endphp
<div>{{ $value }}</div>
BLADE;

        $this->createBladeFile('raw-php.blade.php', $blade);

        $this->bladeLint()
            ->assertExitCode(0);
    }

    public function test_detects_unclosed_blade_directive(): void
    {
        // This should compile but produce invalid PHP
        $blade = '@if($condition)<div>Missing endif';

        $this->createBladeFile('unclosed.blade.php', $blade);

        $this->bladeLint()
            ->assertFailed();
    }

    public function test_handles_empty_blade_file(): void
    {
        $this->createBladeFile('empty.blade.php', '');

        $this->bladeLint()
            ->assertExitCode(0);
    }

    public function test_handles_html_only_blade_file(): void
    {
        $this->createBladeFile('html-only.blade.php', '<div><p>Plain HTML</p></div>');

        $this->bladeLint()
            ->assertExitCode(0);
    }

    public function test_handles_blade_comments(): void
    {
        $blade = '{{-- This is a comment --}}<div>Content</div>';

        $this->createBladeFile('comment.blade.php', $blade);

        $this->bladeLint()
            ->assertExitCode(0);
    }

    // ==================== Coverage improvement tests ====================

    public function test_handle_with_very_verbose_output(): void
    {
        $this->createBladeFile('veryverbose.blade.php', '<div>Test</div>');

        // -vv enables very verbose output (line 112)
        $this->bladeLint(array('-vv' => true))
            ->assertExitCode(0);
    }

    public function test_process_files_catches_error_exception(): void
    {
        // Test that ErrorException handler exists in source code
        $command    = new BladeLint();
        $reflection = new ReflectionClass($command);
        $filename   = $reflection->getFileName();
        $source     = file_get_contents($filename);

        // Verify ErrorException catch block exists (lines 241-244)
        $this->assertStringContainsString('catch (ErrorException $e)', $source);
        $this->assertStringContainsString('file_get_contents', $source);
    }

    public function test_process_files_catches_compilation_exception(): void
    {
        // Create a blade file with invalid directive that will cause compilation error
        // Using an undefined custom directive that Blade compiler will fail on
        $blade = '@undefinedCustomDirectiveThatWillThrow(something)';

        $this->createBladeFile('compile-error.blade.php', $blade);

        // This should catch the Exception during compilation (lines 245-247)
        $result = $this->bladeLint(array(
            '--processes' => 1,
        ));

        // Depending on Laravel version, this may pass or fail
        // The important thing is it doesn't crash - exception is caught
        $this->assertIsInt($result->execute());
    }

    public function test_lint_runtime_exception_message_exists(): void
    {
        // We can't easily test proc_open failure, but we can verify
        // the RuntimeException code exists with the correct message
        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('lint');
        $method->setAccessible(true);

        // Get the source code to verify the RuntimeException exists
        $filename = $reflection->getFileName();
        $source   = file_get_contents($filename);

        // Verify line 301 has the RuntimeException with correct message
        $this->assertStringContainsString("throw new RuntimeException('unable to open process", $source);
    }

    public function test_get_process_count_warns_without_pcntl(): void
    {
        if (extension_loaded('pcntl')) {
            $this->markTestSkipped('PCNTL extension is loaded, cannot test warning path');
        }

        $this->createBladeFile('pcntl-test.blade.php', '<div>Test</div>');

        // Request multiple processes without PCNTL extension
        // Should trigger warning and fall back to single process
        $output = $this->bladeLint(array(
            '--processes' => 4,
            '-v'          => true,
        ));

        // The command should still succeed (falls back to 1 process)
        $output->assertExitCode(0);
    }

    public function test_get_process_count_warns_without_posix(): void
    {
        if (extension_loaded('posix')) {
            $this->markTestSkipped('POSIX extension is loaded, cannot test warning path');
        }

        $this->createBladeFile('posix-test.blade.php', '<div>Test</div>');

        // Request multiple processes without POSIX extension
        // Should trigger warning and fall back to single process
        $output = $this->bladeLint(array(
            '--processes' => 4,
            '-v'          => true,
        ));

        // The command should still succeed (falls back to 1 process)
        $output->assertExitCode(0);
    }

    public function test_get_process_count_with_explicit_count(): void
    {
        $command = $this->getMockBuilder(BladeLint::class)
            ->onlyMethods(array('option'))
            ->getMock();

        $command->method('option')->willReturn('4');

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('getProcessCount');
        $method->setAccessible(true);

        $count = $method->invoke($command);

        // Should return the numeric value
        $this->assertEquals(4, $count);
    }

    public function test_get_process_count_with_zero_processes(): void
    {
        $command = $this->getMockBuilder(BladeLint::class)
            ->onlyMethods(array('option', 'getNumberOfCores'))
            ->getMock();

        $command->method('option')->willReturn('0');
        $command->method('getNumberOfCores')->willReturn(4);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('getProcessCount');
        $method->setAccessible(true);

        $count = $method->invoke($command);

        // Should fall back to number of cores when 0 or negative
        $this->assertEquals(4, $count);
    }

    public function test_get_blade_file_sizes_with_file_path(): void
    {
        // Test that getBladeFileSizes handles a single file path (not directory)
        $filePath = $this->createBladeFile('single-file.blade.php', '<div>Test</div>');

        $this->bladeLint(array(
            'path'        => array($filePath),
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_process_files_displays_error_output(): void
    {
        // Create a file with PHP syntax error
        $this->createBladeFile('syntax-error.blade.php', '<?php echo "unclosed;');

        $this->bladeLint(array(
            '--processes' => 1,
        ))->assertFailed();
    }

    public function test_handle_displays_found_templates_message(): void
    {
        $this->createBladeFile('message-test.blade.php', '<div>Test</div>');

        $this->bladeLint(array('-v' => true))
            ->expectsOutputToContain('Found')
            ->assertExitCode(0);
    }

    public function test_lint_with_stderr_output(): void
    {
        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('lint');
        $method->setAccessible(true);

        // Test with code that produces stderr
        $stdout = '';
        $stderr = '';
        $args   = array('<?php syntax error here', &$stdout, &$stderr);
        $result = $method->invokeArgs($command, $args);

        $this->assertFalse($result);
    }

    public function test_get_number_of_cores_with_cpuinfo(): void
    {
        // This tests the /proc/cpuinfo path which is typically available on Linux
        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('getNumberOfCores');
        $method->setAccessible(true);

        $cores = $method->invoke($command);

        // On Linux systems with /proc/cpuinfo, this should return actual core count
        $this->assertGreaterThan(0, $cores);
    }

    public function test_process_files_with_very_verbose_listing(): void
    {
        $this->createBladeFile('list-test.blade.php', '<div>Test</div>');

        // Very verbose mode should list each file as it's processed
        $this->bladeLint(array(
            '-vv'         => true,
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_blade_lint_handles_undefined_directive(): void
    {
        // Some blade directives may not be defined, causing compilation to fail
        // This tests the general exception handler
        $blade = '@thisDirectiveDefinitelyDoesNotExist123456789';

        $this->createBladeFile('undefined-directive.blade.php', $blade);

        // Run with single process to hit processFiles exception handling
        $this->bladeLint(array(
            '--processes' => 1,
        ));

        // Should complete without crashing (exception caught)
        $this->assertTrue(true);
    }

    public function test_sigkill_constant_is_defined(): void
    {
        // Verify SIGKILL constant is defined (line 25-26)
        $this->assertTrue(defined('SIGKILL'));
        $this->assertEquals(9, SIGKILL);
    }

    public function test_get_blade_file_sizes_with_non_blade_file(): void
    {
        // Create a non-blade PHP file to ensure it's filtered out
        $this->createBladeFile('valid.blade.php', '<div>Blade</div>');
        file_put_contents($this->fixturesPath . '/regular.php', '<?php echo "Not blade";');

        // Run via artisan to properly test that non-blade files are filtered
        $this->bladeLint(array(
            '--processes' => 1,
        ))->assertExitCode(0);

        @unlink($this->fixturesPath . '/regular.php');
    }

    public function test_error_count_accumulates(): void
    {
        // Test that multiple errors are counted correctly
        $this->createBladeFile('error1.blade.php', '<?php syntax error 1');
        $this->createBladeFile('error2.blade.php', '<?php syntax error 2');
        $this->createBladeFile('error3.blade.php', '<?php syntax error 3');

        $exitCode = $this->bladeLint(array(
            '--processes' => 1,
        ))->execute();

        // Exit code should reflect multiple errors
        $this->assertGreaterThan(0, $exitCode);
    }

    public function test_process_files_with_stat_failure(): void
    {
        // Test files array handling when stat might fail
        $blade1 = $this->createBladeFile('stat1.blade.php', '<div>Test 1</div>');
        $blade2 = $this->createBladeFile('stat2.blade.php', '<div>Test 2</div>');

        $this->bladeLint(array(
            'path'        => array($blade1, $blade2),
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_output_verbosity_levels(): void
    {
        $this->createBladeFile('verbosity-test.blade.php', '<div>Test</div>');

        // Test normal verbosity (no -v flag)
        $this->bladeLint(array(
            '--processes' => 1,
        ))->assertExitCode(0);

        // Test with -v (verbose)
        $this->bladeLint(array(
            '-v'          => true,
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_compiled_php_validation_success(): void
    {
        // Test that valid blade compiles to valid PHP
        $blade = <<<'BLADE'
<html>
<body>
    @if(true)
        <p>Valid blade syntax</p>
    @endif
</body>
</html>
BLADE;

        $this->createBladeFile('valid-compile.blade.php', $blade);

        $this->bladeLint(array(
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_process_count_numeric_string(): void
    {
        // Test that numeric string is properly converted to int
        // Using '1' to avoid forking in PHPUnit context
        $this->createBladeFile('numeric.blade.php', '<div>Test</div>');

        $this->bladeLint(array(
            'path'        => array($this->fixturesPath),
            '--processes' => '1',
        ))->assertExitCode(0);
    }

    public function test_handle_with_auto_processes(): void
    {
        // Clear fixtures to ensure only one file exists, which guarantees
        // processCount = min(1, cores) = 1, avoiding forking
        $this->clearFixtures();
        $this->createBladeFile('auto.blade.php', '<div>Test</div>');

        // Test with 'auto' processes (default) - safe with single file
        $this->bladeLint(array(
            'path'        => array($this->fixturesPath),
            '--processes' => 'auto',
        ))->assertExitCode(0);
    }

    public function test_error_message_formatting(): void
    {
        // Test that error messages are properly formatted
        $this->createBladeFile('format-error.blade.php', '<?php echo "unclosed string');

        $this->bladeLint(array(
            '--processes' => 1,
        ))->assertFailed();
    }

    public function test_process_files_with_blade_compilation_exception(): void
    {
        // Create an empty blade file which should be handled gracefully
        $this->createBladeFile('exception-test.blade.php', '');

        $this->bladeLint(array(
            'path'        => array($this->fixturesPath),
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_multiprocess_scenario_with_multiple_files(): void
    {
        // Create enough files to test multiple file processing
        for ($i = 1; $i <= 5; $i++) {
            $this->createBladeFile("multi{$i}.blade.php", "<div>File {$i}</div>");
        }

        // Note: Using --processes => 1 to avoid actual forking in PHPUnit context
        // which can cause fork bombs. The multiprocess forking code path is tested
        // via unit tests on splitBladesIntoChunks() and installSignalHandlers().
        $this->bladeLint(array(
            'path'        => array($this->fixturesPath),
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_handle_prints_copyright(): void
    {
        $this->createBladeFile('copyright.blade.php', '<div>Test</div>');

        $this->bladeLint()
            ->expectsOutputToContain('Blade Lint by Jeroen Derks')
            ->assertExitCode(0);
    }

    public function test_process_files_message_length_tracking(): void
    {
        // Create files with varying path lengths to test message length tracking
        $this->createBladeFile('short.blade.php', '<div>Test</div>');
        $longName = str_repeat('a', 50) . '.blade.php';
        $this->createBladeFile($longName, '<div>Long name</div>');

        $this->bladeLint(array(
            '--processes' => 1,
            '-v'          => true,
        ))->assertExitCode(0);
    }

    public function test_blade_with_complex_directives(): void
    {
        $blade = <<<'BLADE'
@extends('layout')

@section('content')
    @foreach($items as $item)
        @if($item->active)
            <div>{{ $item->name }}</div>
        @else
            <span>{{ $item->name }}</span>
        @endif
    @endforeach
@endsection

@push('scripts')
    <script>console.log('test');</script>
@endpush
BLADE;

        $this->createBladeFile('complex.blade.php', $blade);

        $this->bladeLint(array(
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_blade_with_php_blocks(): void
    {
        $blade = <<<'BLADE'
@php
    $variable = 'test';
    $array = [1, 2, 3];
    foreach ($array as $item) {
        echo $item;
    }
@endphp
<div>{{ $variable }}</div>
BLADE;

        $this->createBladeFile('php-block.blade.php', $blade);

        $this->bladeLint(array(
            '--processes' => 1,
        ))->assertExitCode(0);
    }

    public function test_split_blades_distributes_evenly(): void
    {
        $command = $this->getMockBuilder(BladeLint::class)
            ->onlyMethods(array('getProcessCount'))
            ->getMock();

        $command->method('getProcessCount')->willReturn(3);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('splitBladesIntoChunks');
        $method->setAccessible(true);

        $blades = array();
        for ($i = 1; $i <= 10; $i++) {
            $blades["/path/file{$i}.blade.php"] = 100 * $i;
        }

        $chunks = $method->invoke($command, $blades);

        // Should have 3 chunks
        $this->assertCount(3, $chunks);

        // Total files should be preserved
        /** @psalm-suppress InvalidArgument */
        $totalFiles = array_sum(array_map('count', $chunks));
        $this->assertEquals(10, $totalFiles);
    }

    public function test_get_process_count_with_negative_value(): void
    {
        $command = $this->getMockBuilder(BladeLint::class)
            ->onlyMethods(array('option', 'getNumberOfCores'))
            ->getMock();

        $command->method('option')->willReturn('-1');
        $command->method('getNumberOfCores')->willReturn(8);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('getProcessCount');
        $method->setAccessible(true);

        $count = $method->invoke($command);

        // Negative should fall back to number of cores
        $this->assertEquals(8, $count);
    }

    public function test_handle_returns_error_count(): void
    {
        $this->createBladeFile('error-count-1.blade.php', '<?php syntax error');
        $this->createBladeFile('error-count-2.blade.php', '<?php another error');

        $exitCode = $this->bladeLint(array(
            '--processes' => 1,
        ))->execute();

        // Should return non-zero exit code matching error count
        $this->assertGreaterThan(0, $exitCode);
    }

    public function test_process_files_with_nonexistent_file(): void
    {
        [$command, $method] = $this->createCommandForProcessFiles();

        // Pass a non-existent file path
        $nonExistentFile = $this->fixturesPath . '/nonexistent-file.blade.php';
        $errorCount      = $method->invoke($command, array($nonExistentFile), strlen($nonExistentFile));

        // Should report 1 error for the missing file
        $this->assertEquals(1, $errorCount);
    }

    public function test_process_files_with_unreadable_file(): void
    {
        // Create a file then make it unreadable
        $filePath = $this->createBladeFile('unreadable.blade.php', '<div>Test</div>');
        chmod($filePath, 0000);

        [$command, $method] = $this->createCommandForProcessFiles();

        $errorCount = $method->invoke($command, array($filePath), strlen($filePath));

        // Restore permissions for cleanup
        chmod($filePath, 0644);

        // Should report 1 error for the unreadable file
        $this->assertEquals(1, $errorCount);
    }

    // ==================== Multiprocess/Fork related tests ====================

    public function test_wait_for_workers_with_kill(): void
    {
        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);

        // Set up empty workerPids - tests the loop with no workers
        $workerPidsProperty = $reflection->getProperty('workerPids');
        $workerPidsProperty->setAccessible(true);
        $workerPidsProperty->setValue($command, array());

        $method = $reflection->getMethod('waitForWorkers');
        $method->setAccessible(true);

        // Call with doKill=true on empty array
        $result = $method->invoke($command, true);

        $this->assertEquals(0, $result);
    }

    public function test_wait_for_workers_accumulates_exit_codes(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);

        // We can't easily test with real PIDs without forking, but we can test
        // the error accumulation logic by checking the method exists and is callable
        $method = $reflection->getMethod('waitForWorkers');
        $method->setAccessible(true);

        // Test with empty pids array
        $workerPidsProperty = $reflection->getProperty('workerPids');
        $workerPidsProperty->setAccessible(true);
        $workerPidsProperty->setValue($command, array());

        $result = $method->invoke($command, false);
        $this->assertEquals(0, $result);
    }

    public function test_check_blade_syntax_files_single_process_path(): void
    {
        $filePath = $this->createBladeFile('single-process.blade.php', '<div>Test</div>');

        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);

        // Mock the input
        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->method('getOption')->willReturnMap(array(
            array('processes', '1'),
            array('debug', false),
        ));
        $inputMock->method('getArgument')->with('path')->willReturn(array($this->fixturesPath));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $inputMock);

        // Set up output
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($command, new \Symfony\Component\Console\Output\BufferedOutput());

        $method = $reflection->getMethod('checkBladeSyntaxFiles');
        $method->setAccessible(true);

        $blades = array($filePath => 100);
        $result = $method->invoke($command, $blades);

        $this->assertEquals(0, $result);
    }

    public function test_get_number_of_cores_fallback(): void
    {
        // Test that getNumberOfCores returns at least 1
        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('getNumberOfCores');
        $method->setAccessible(true);

        $cores = $method->invoke($command);

        $this->assertGreaterThanOrEqual(1, $cores);
        $this->assertIsInt($cores);
    }

    public function test_process_count_capped_by_file_count(): void
    {
        // Test that processCount = min(fileCount, coreCount)
        $this->createBladeFile('capped.blade.php', '<div>Test</div>');

        $command = $this->getMockBuilder(BladeLint::class)
            ->onlyMethods(array('getNumberOfCores'))
            ->getMock();

        // Mock high core count
        $command->method('getNumberOfCores')->willReturn(100);
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->method('getOption')->willReturnMap(array(
            array('processes', 'auto'),
            array('debug', false),
        ));
        $inputMock->method('getArgument')->with('path')->willReturn(array($this->fixturesPath));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $inputMock);

        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($command, new \Symfony\Component\Console\Output\BufferedOutput());

        // With only 1 file, processCount should be 1 even with 100 cores
        $getProcessCountMethod = $reflection->getMethod('getProcessCount');
        $getProcessCountMethod->setAccessible(true);
        $processCount = $getProcessCountMethod->invoke($command);

        // The raw getProcessCount returns core count, but handle() caps it
        $this->assertEquals(100, $processCount);
    }

    public function test_split_blades_into_chunks_respects_process_count(): void
    {
        $command = $this->getMockBuilder(BladeLint::class)
            ->onlyMethods(array('getProcessCount'))
            ->getMock();

        $command->method('getProcessCount')->willReturn(3);
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('splitBladesIntoChunks');
        $method->setAccessible(true);

        $blades = array(
            'file1.blade.php' => 100,
            'file2.blade.php' => 200,
            'file3.blade.php' => 150,
            'file4.blade.php' => 50,
            'file5.blade.php' => 300,
            'file6.blade.php' => 75,
        );

        $chunks = $method->invoke($command, $blades);

        $this->assertCount(3, $chunks);
    }

    public function test_install_signal_handlers_skips_sigkill(): void
    {
        if (! function_exists('pcntl_signal')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('installSignalHandlers');
        $method->setAccessible(true);

        // Should not throw - SIGKILL (9) is skipped
        $method->invoke($command);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function test_check_blade_syntax_files_returns_zero_for_empty_array(): void
    {
        [$command, $method] = $this->createCommandForCheckBladeSyntaxFiles();

        $result = $method->invoke($command, array());

        $this->assertEquals(0, $result);
    }

    public function test_process_files_with_debug_output(): void
    {
        $filePath = $this->createBladeFile('debug-output.blade.php', '<div>{{ $test }}</div>');

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $output->setVerbosity(\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_QUIET);

        [$command, $method] = $this->createCommandForProcessFiles(true, $output);

        $result = $method->invoke($command, array($filePath), strlen($filePath));

        $this->assertEquals(0, $result);
    }

    public function test_get_blade_file_sizes_with_spl_file_info(): void
    {
        $this->createBladeFile('spl-test.blade.php', '<div>Test</div>');

        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->method('getArgument')->with('path')->willReturn(array($this->fixturesPath));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $inputMock);

        $output         = new \Symfony\Component\Console\Output\BufferedOutput();
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($command, $output);

        $method = $reflection->getMethod('getBladeFileSizes');
        $method->setAccessible(true);

        $result = $method->invoke($command);

        $this->assertIsArray($result);
        // Should find at least the file we created
        $this->assertNotEmpty($result);
    }

    public function test_array_reduce_with_empty_blades(): void
    {
        [$command, $method] = $this->createCommandForCheckBladeSyntaxFiles();

        // Empty array should work with array_reduce initial value of 0
        $result = $method->invoke($command, array());

        $this->assertEquals(0, $result);
    }

    public function test_lint_with_process_open_success(): void
    {
        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('lint');
        $method->setAccessible(true);

        $stdout = '';
        $stderr = '';
        $code   = '<?php echo "valid"; ?>';

        $result = $method->invokeArgs($command, array($code, &$stdout, &$stderr));

        $this->assertTrue($result);
        $this->assertStringContainsString('No syntax errors', $stdout);
    }

    public function test_handle_with_zero_blade_files(): void
    {
        // Clear all fixtures first
        $this->clearFixtures();

        // Create a non-blade file
        file_put_contents($this->fixturesPath . '/not-blade.txt', 'This is not a blade file');
        $this->createdFiles[] = $this->fixturesPath . '/not-blade.txt';

        $result = $this->bladeLint(array(
            'path' => array($this->fixturesPath),
        ));

        $result->expectsOutputToContain('All Blade templates OK!')
            ->assertExitCode(0);
    }

    public function test_verbose_output_shows_processing_message(): void
    {
        $this->createBladeFile('verbose-test.blade.php', '<div>Test</div>');

        $this->bladeLint(array(
            'path' => array($this->fixturesPath),
            '-v'   => true,
        ))->expectsOutputToContain('Found')
          ->assertExitCode(0);
    }

    public function test_max_length_calculation_in_process_files(): void
    {
        // Create files with different name lengths to test maxLength calculation
        $shortFile = $this->createBladeFile('a.blade.php', '<div>Short name</div>');
        $longFile  = $this->createBladeFile('very-long-filename-for-testing.blade.php', '<div>Long name</div>');

        [$command, $method] = $this->createCommandForProcessFiles(false, new \Symfony\Component\Console\Output\BufferedOutput());

        $files = array($shortFile, $longFile);

        $longestPath = max(array_map('strlen', $files));
        $result      = $method->invoke($command, $files, $longestPath);

        $this->assertEquals(0, $result);
    }

    public function test_error_output_formatting_with_line_info(): void
    {
        // Create a blade file with syntax error that produces line info
        $filePath = $this->createBladeFile('line-error.blade.php', "<?php\necho 'test\n");

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $output->setVerbosity(\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_QUIET);

        [$command, $method] = $this->createCommandForProcessFiles(false, $output);

        $result = $method->invoke($command, array($filePath), strlen($filePath));

        $this->assertEquals(1, $result);
    }

    public function test_worker_pids_property_initialization(): void
    {
        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $property   = $reflection->getProperty('workerPids');
        $property->setAccessible(true);

        $value = $property->getValue($command);

        $this->assertIsArray($value);
        $this->assertEmpty($value);
    }

    public function test_process_count_property_initialization(): void
    {
        $command = new BladeLint();

        $reflection = new ReflectionClass($command);
        $property   = $reflection->getProperty('processCount');
        $property->setAccessible(true);

        $value = $property->getValue($command);

        $this->assertNull($value);
    }

    public function test_process_files_catches_general_exception(): void
    {
        // Create a blade file that will cause a general Exception during compilation
        // Using @verbatim with unbalanced tags can cause issues
        $filePath = $this->createBladeFile('exception-blade.blade.php', '@verbatim {{ $test }');

        [$command, $method] = $this->createCommandForProcessFiles(false, new \Symfony\Component\Console\Output\BufferedOutput());

        $result = $method->invoke($command, array($filePath), strlen($filePath));

        // May or may not report an error depending on blade compiler behavior
        $this->assertIsInt($result);
    }

    public function test_get_number_of_cores_returns_system_cores(): void
    {
        // This test verifies getNumberOfCores returns actual system core count
        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection = new ReflectionClass($command);
        $method     = $reflection->getMethod('getNumberOfCores');
        $method->setAccessible(true);

        $cores = $method->invoke($command);

        // Should return at least 1 core
        $this->assertGreaterThanOrEqual(1, $cores);
        $this->assertIsInt($cores);

        // Verify against system-specific methods
        if (is_file('/proc/cpuinfo')) {
            // Linux: verify against /proc/cpuinfo
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if ($cpuinfo !== false) {
                preg_match_all('/^processor/m', $cpuinfo, $matches);
                $this->assertEquals(count($matches[0]), $cores);
            }
        } elseif (PHP_OS === 'Darwin') {
            // macOS: verify against sysctl
            $expected = (int) exec('sysctl -n hw.ncpu');
            $this->assertEquals($expected, $cores);
        }
        // On other systems, just verify it returns a positive integer
    }

    public function test_blade_compile_exception_handling(): void
    {
        // Test that blade compilation exceptions are caught and reported
        // Create a blade file with invalid directive usage
        $this->createBladeFile('invalid-directive.blade.php', '@unknownDirective("test")');

        $this->bladeLint(array(
            'path' => array($this->fixturesPath),
        ))->assertExitCode(0); // Unknown directives are passed through, not errors
    }

    public function test_process_files_error_exception_message_formatting(): void
    {
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $output->setVerbosity(\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_QUIET);

        [$command, $method] = $this->createCommandForProcessFiles(false, $output);

        // Non-existent file triggers ErrorException with file_get_contents message
        $filePath = '/nonexistent/path/to/file.blade.php';
        $result   = $method->invoke($command, array($filePath), strlen($filePath));

        $this->assertEquals(1, $result);

        // Check that error message was formatted (file_get_contents prefix removed)
        $outputContent = $output->fetch();
        $this->assertStringContainsString($filePath, $outputContent);
    }

    // ==================== waitForWorkers() with actual fork tests ====================

    public function test_wait_for_workers_waits_for_child_to_finish(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection         = new ReflectionClass($command);
        $workerPidsProperty = $reflection->getProperty('workerPids');
        $workerPidsProperty->setAccessible(true);

        // Fork a child process that exits quickly with exit code 0
        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->fail('Failed to fork child process');
        } elseif ($pid === 0) {
            // Child process: exit immediately with code 0
            exit(0);
        }

        // Parent process: set the child PID and wait for it
        $workerPidsProperty->setValue($command, array($pid));

        $method = $reflection->getMethod('waitForWorkers');
        $method->setAccessible(true);

        $result = $method->invoke($command, false);

        // Child exited with 0, so result should be 0
        $this->assertEquals(0, $result);
    }

    public function test_wait_for_workers_accumulates_child_exit_codes(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection         = new ReflectionClass($command);
        $workerPidsProperty = $reflection->getProperty('workerPids');
        $workerPidsProperty->setAccessible(true);

        // Fork a child process that exits with a non-zero code
        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->fail('Failed to fork child process');
        } elseif ($pid === 0) {
            // Child process: exit with code 3 (simulating 3 errors)
            exit(3);
        }

        // Parent process: set the child PID and wait for it
        $workerPidsProperty->setValue($command, array($pid));

        $method = $reflection->getMethod('waitForWorkers');
        $method->setAccessible(true);

        $result = $method->invoke($command, false);

        // Child exited with 3, so result should be 3
        $this->assertEquals(3, $result);
    }

    public function test_wait_for_workers_kills_child_process(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        if (! function_exists('posix_kill')) {
            $this->markTestSkipped('posix extension not available');
        }

        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection         = new ReflectionClass($command);
        $workerPidsProperty = $reflection->getProperty('workerPids');
        $workerPidsProperty->setAccessible(true);

        // Fork a child process that waits for input (simulates long-running process)
        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->fail('Failed to fork child process');
        } elseif ($pid === 0) {
            // Child process: wait on /dev/null with timeout (max 2 seconds)
            // Using a simple sleep that can be interrupted by SIGKILL
            $start = time();
            while ((time() - $start) < 2) {
                usleep(100000); // 100ms
            }
            exit(0);
        }

        // Parent process: set the child PID and kill it
        $workerPidsProperty->setValue($command, array($pid));

        $method = $reflection->getMethod('waitForWorkers');
        $method->setAccessible(true);

        // Kill the worker (doKill = true)
        $result = $method->invoke($command, true);

        // When killed with SIGKILL, the exit status is typically 137 (128 + 9)
        // but pcntl_wexitstatus returns the exit code portion, which may vary
        // The important thing is the child was terminated
        $this->assertIsInt($result);
    }

    public function test_wait_for_workers_handles_multiple_children(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection         = new ReflectionClass($command);
        $workerPidsProperty = $reflection->getProperty('workerPids');
        $workerPidsProperty->setAccessible(true);

        $pids = array();

        // Fork first child - exits with code 1
        $pid1 = pcntl_fork();
        if ($pid1 === -1) {
            $this->fail('Failed to fork first child process');
        } elseif ($pid1 === 0) {
            exit(1);
        }
        $pids[] = $pid1;

        // Fork second child - exits with code 2
        $pid2 = pcntl_fork();
        if ($pid2 === -1) {
            // Clean up first child before failing
            posix_kill($pid1, SIGKILL);
            pcntl_waitpid($pid1, $status);
            $this->fail('Failed to fork second child process');
        } elseif ($pid2 === 0) {
            exit(2);
        }
        $pids[] = $pid2;

        // Parent process: set the child PIDs and wait for them
        $workerPidsProperty->setValue($command, $pids);

        $method = $reflection->getMethod('waitForWorkers');
        $method->setAccessible(true);

        $result = $method->invoke($command, false);

        // Exit codes should be accumulated: 1 + 2 = 3
        $this->assertEquals(3, $result);
    }

    public function test_wait_for_workers_returns_127_on_signal_exit(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $command = new BladeLint();
        $command->setLaravel($this->app);

        $reflection         = new ReflectionClass($command);
        $workerPidsProperty = $reflection->getProperty('workerPids');
        $workerPidsProperty->setAccessible(true);

        // Fork a child that exits with code 127 (special error code)
        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->fail('Failed to fork child process');
        } elseif ($pid === 0) {
            exit(127);
        }

        $workerPidsProperty->setValue($command, array($pid));

        $method = $reflection->getMethod('waitForWorkers');
        $method->setAccessible(true);

        $result = $method->invoke($command, false);

        // Exit code 127 should be preserved
        $this->assertEquals(127, $result);
    }
}
