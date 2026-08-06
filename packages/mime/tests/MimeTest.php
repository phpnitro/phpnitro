<?php

namespace Engine\Mime\Tests;

use Engine\Mime\Mime;
use PHPUnit\Framework\TestCase;

final class MimeTest extends TestCase
{
    public function testGuessFromExtension(): void
    {
        $this->assertSame('image/jpeg', Mime::guessFromExtension('jpg'));
        $this->assertSame('image/jpeg', Mime::guessFromExtension('.JPEG'));
        $this->assertSame('application/pdf', Mime::guessFromExtension('pdf'));
    }

    public function testGuessFromExtensionUnknownFallsBackToOctetStream(): void
    {
        $this->assertSame('application/octet-stream', Mime::guessFromExtension('xyz123'));
    }

    public function testGuessFromPathUsesExtensionWhenFileDoesNotExist(): void
    {
        $this->assertSame('text/csv', Mime::guessFromPath('/nowhere/report.csv'));
    }

    public function testGuessFromPathSniffsRealFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mime_test_');
        file_put_contents($path, '{"a":1}');
        rename($path, $path . '.json');
        $path .= '.json';

        try {
            $this->assertSame('application/json', Mime::guessFromPath($path));
        } finally {
            unlink($path);
        }
    }

    public function testExtensionFor(): void
    {
        $this->assertSame('pdf', Mime::extensionFor('application/pdf'));
        $this->assertNull(Mime::extensionFor('application/does-not-exist'));
    }
}
