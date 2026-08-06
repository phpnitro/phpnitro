<?php

namespace Engine\Tests\Golden;

use Engine\Database\Database;
use Engine\Native\MediaQuery;
use Engine\Native\Tokens;
use Engine\Preferences\Preferences;

/**
 * Whole-screen fixtures — a much wider regression net than
 * LayoutPrimitivesGoldenTest's isolated cases: NativeWidgetsFormsScreen
 * alone exercises dozens of widgets (SelectBox, DatePicker, Slider,
 * PinCodeField, QrCode, GoogleFontText, FontAwesome icons...) in one
 * pass. A mismatch here is less precise about WHICH widget broke, but
 * catches interaction effects between widgets (a Flex sizing change
 * rippling into a sibling's position) an isolated fixture never would.
 *
 * Every piece of process-wide static state these screens can reach is
 * reset in setUp() — $_GET, MediaQuery, Tokens' dark-mode stack, AND
 * Database/Preferences (NativeHomeScreen reads a persisted counter via
 * Preferences::get()). Without the last one, this test doesn't just
 * risk a flaky fixture: caught for real, it connected to — and wrote a
 * "preferences" table into — this project's own real default
 * var/data.sqlite the first time this ran, then a completely unrelated
 * DatabaseTest started failing on a later run because Database's own
 * connection-reuse had a genuine bug (now fixed, see Database::
 * useSqlitePath()'s docblock) that made it keep reusing that same
 * wrong connection even after asking for a fresh path.
 */
final class ScreensGoldenTest extends GoldenTestCase
{
    private string $sqlitePath;

    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [];
        MediaQuery::init(400.0, 800.0);
        Tokens::init(false);

        $this->sqlitePath = sys_get_temp_dir() . '/phpnitro-golden-test-' . uniqid() . '.sqlite';
        Database::useSqlitePath($this->sqlitePath);
        (new \ReflectionClass(Preferences::class))->getProperty('schemaEnsured')->setValue(null, false);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $reflection->getProperty('connection')->setValue(null, null);
        $reflection->getProperty('sqlitePath')->setValue(null, null);
        @unlink($this->sqlitePath);
        parent::tearDown();
    }

    public function testNativeHomeScreen(): void
    {
        $this->assertMatchesGolden('screen_home', \Engine\App\NativeHomeScreen::build(400.0, 800.0));
    }

    public function testNativeWidgetsFormsScreen(): void
    {
        $this->assertMatchesGolden(
            'screen_widgets_forms',
            \Engine\App\NativeWidgetsFormsScreen::build(400.0, null),
            400.0,
            4000.0,
        );
    }

    public function testNativeDeviceScreen(): void
    {
        $this->assertMatchesGolden('screen_device', \Engine\App\NativeDeviceScreen::build(400.0, 3000.0));
    }
}
