<?php

namespace Engine\I18n\Tests;

use Engine\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    private string $langDir;

    protected function setUp(): void
    {
        $this->langDir = sys_get_temp_dir() . '/phpnitro-i18n-test-' . uniqid();
        mkdir($this->langDir);
        file_put_contents($this->langDir . '/fr.php', "<?php\nreturn ['greeting' => 'Bonjour {name}', 'title' => 'Accueil'];\n");
        file_put_contents($this->langDir . '/en.php', "<?php\nreturn ['greeting' => 'Hello {name}'];\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->langDir . '/fr.php');
        @unlink($this->langDir . '/en.php');
        @rmdir($this->langDir);
    }

    public function testTranslatesKnownKey(): void
    {
        Translator::init('fr', $this->langDir);

        $this->assertSame('Accueil', Translator::t('title'));
    }

    public function testInterpolatesParams(): void
    {
        Translator::init('fr', $this->langDir);

        $this->assertSame('Bonjour Ronaldo', Translator::t('greeting', ['name' => 'Ronaldo']));
    }

    public function testMissingKeyFallsBackToKeyItself(): void
    {
        Translator::init('fr', $this->langDir);

        $this->assertSame('unknown.key', Translator::t('unknown.key'));
    }

    public function testHasDistinguishesRealTranslationFromFallback(): void
    {
        Translator::init('fr', $this->langDir);

        $this->assertTrue(Translator::has('title'));
        $this->assertFalse(Translator::has('unknown.key'));
    }

    public function testLocaleSwitch(): void
    {
        Translator::init('en', $this->langDir);

        $this->assertSame('en', Translator::locale());
        $this->assertSame('Hello Ronaldo', Translator::t('greeting', ['name' => 'Ronaldo']));
    }

    public function testMissingLangFileLeavesEmptyTranslations(): void
    {
        Translator::init('de', $this->langDir);

        $this->assertSame('title', Translator::t('title'));
        $this->assertFalse(Translator::has('title'));
    }

    public function testInitResetsPreviousTranslations(): void
    {
        Translator::init('fr', $this->langDir);
        $this->assertTrue(Translator::has('title'));

        Translator::init('en', $this->langDir);
        $this->assertFalse(Translator::has('title'));
    }
}
