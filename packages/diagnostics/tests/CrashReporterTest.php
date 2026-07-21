<?php

namespace Engine\Diagnostics\Tests;

use Engine\Diagnostics\CrashReporter;
use PHPUnit\Framework\TestCase;

final class CrashReporterTest extends TestCase
{
    public function testReportWithoutInstallDoesNothingAndDoesNotThrow(): void
    {
        // No install() called in this test — reportUrl is null internally.
        // Nothing to assert on directly (no observable side effect without
        // a real endpoint), just that it never throws.
        CrashReporter::report('TestType', 'message', 'file.php:1', '');
        $this->addToAssertionCount(1);
    }

    public function testReportToUnreachableUrlDoesNotThrow(): void
    {
        CrashReporter::install('http://127.0.0.1:1/unreachable');

        try {
            CrashReporter::report('TestType', 'message', 'file.php:1', 'trace');
            $this->addToAssertionCount(1);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
