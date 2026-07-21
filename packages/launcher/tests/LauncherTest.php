<?php

namespace Engine\Launcher\Tests;

use Engine\Launcher\Launcher;
use PHPUnit\Framework\TestCase;

final class LauncherTest extends TestCase
{
    public function testOpenUrl(): void
    {
        $this->assertSame(
            'phpxDevice.launchUrl("https:\/\/example.com")',
            Launcher::openUrl('https://example.com'),
        );
    }

    public function testCallStripsNonPhoneCharacters(): void
    {
        $this->assertSame('phpxDevice.launchUrl("tel:+22952757156")', Launcher::call('+229 52 75 71 56'));
    }

    public function testSmsWithoutBody(): void
    {
        $this->assertSame('phpxDevice.launchUrl("tel:1234")', str_replace('sms:', 'tel:', Launcher::sms('1234')));
    }

    public function testSmsEncodesBody(): void
    {
        $result = Launcher::sms('1234', 'Salut ça va ?');

        $this->assertStringContainsString('sms:1234?body=', $result);
    }

    public function testEmailWithSubjectAndBody(): void
    {
        $result = Launcher::email('contact@example.com', 'Bonjour', 'Un message');

        $this->assertStringContainsString('mailto:contact%40example.com?', $result);
        $this->assertStringContainsString('subject=Bonjour', $result);
        $this->assertStringContainsString('body=Un+message', $result);
    }

    public function testEmailWithoutSubjectOrBody(): void
    {
        $this->assertSame('phpxDevice.launchUrl("mailto:contact%40example.com")', Launcher::email('contact@example.com'));
    }
}
