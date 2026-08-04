<?php

namespace Engine\App\Tests;

use Engine\App\NativeForgotPasswordScreen;
use Engine\App\NativeHomeScreen;
use Engine\App\NativeLoginScreen;
use Engine\App\NativeRegisterScreen;
use Engine\App\NativeResetPasswordScreen;
use Engine\App\NativeWidgetsFormsScreen;
use Engine\Database\Database;
use Engine\I18n\Translator;
use Engine\Native\Constraints;
use Engine\Native\MediaQuery;
use Engine\Native\Tokens;
use Engine\Native\Widget;
use PHPUnit\Framework\TestCase;

/**
 * Not a pixel-perfect visual test (no device/screenshot involved) — just
 * the same guarantee `php -l` + this session's real error-overlay bugs
 * (Chip.php/Badge.php/NativeWidgetsFormsScreen.php all missing a `use`
 * statement) would have caught immediately: every screen's build() runs
 * end to end, layout()s and paint()s against a real Canvas without
 * throwing. Covers the screens make:auth scaffolds (login/register/
 * forgot-password/reset-password) plus home and the widgets-forms demo
 * (exercises the largest single set of widgets in one tree) — not all
 * ~40 screens, same "prove the pattern, not retrofit everything" scope
 * boundary NativeLoginScreen.php's own docblock documents for i18n.
 */
final class NativeScreensSmokeTest extends TestCase
{
    private string $sqlitePath;

    protected function setUp(): void
    {
        $this->sqlitePath = sys_get_temp_dir() . '/phpnitro-screens-test-' . uniqid() . '.sqlite';
        Database::useSqlitePath($this->sqlitePath);
        MediaQuery::init(360.0, 720.0);
        Tokens::init(false);
        Translator::init('fr', __DIR__ . '/../../lang');
        $_GET = [];
        $_SESSION = [];

        // Preferences::$schemaEnsured is a static that can already be
        // true here — some other test file (order isn't guaranteed) may
        // have used Preferences against ITS OWN sqlite file earlier in
        // this same PHP process, and the flag alone gates
        // ensureSchema()'s CREATE TABLE — it doesn't know the
        // connection underneath changed.
        (new \ReflectionClass(\Engine\Preferences\Preferences::class))
            ->getProperty('schemaEnsured')->setValue(null, false);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $reflection->getProperty('connection')->setValue(null, null);
        $reflection->getProperty('sqlitePath')->setValue(null, null);

        // Preferences::$schemaEnsured is a static that outlives this
        // test's own sqlite file (its own tables never get re-created
        // against a fresh Database::connection() otherwise, since the
        // flag alone gates ensureSchema() — see its own source).
        $preferencesReflection = new \ReflectionClass(\Engine\Preferences\Preferences::class);
        $preferencesReflection->getProperty('schemaEnsured')->setValue(null, false);

        @unlink($this->sqlitePath);
    }

    private function assertRenders(Widget $widget): void
    {
        $size = $widget->layout(new Constraints(0, 360, 0, Constraints::INFINITY));
        $this->assertGreaterThan(0.0, $size->height);

        $canvas = new \Engine\Native\Canvas();
        $widget->paint($canvas, 0, 0);
        $this->assertNotSame('', $canvas->toJson());
    }

    public function testHome(): void
    {
        $this->assertRenders(NativeHomeScreen::build(360.0, 720.0));
    }

    public function testLoginWithoutError(): void
    {
        $this->assertRenders(NativeLoginScreen::build(360.0, null));
    }

    public function testLoginWithError(): void
    {
        $this->assertRenders(NativeLoginScreen::build(360.0, 'invalid_credentials'));
    }

    public function testRegisterWithoutError(): void
    {
        $this->assertRenders(NativeRegisterScreen::build(360.0, null));
    }

    public function testRegisterWithError(): void
    {
        $this->assertRenders(NativeRegisterScreen::build(360.0, 'Cet e-mail est déjà utilisé.'));
    }

    public function testForgotPassword(): void
    {
        $this->assertRenders(NativeForgotPasswordScreen::build(360.0, null, null));
    }

    public function testForgotPasswordWithDevResetLink(): void
    {
        $this->assertRenders(NativeForgotPasswordScreen::build(360.0, null, 'phpnitro://reset-password?token=abc'));
    }

    public function testResetPassword(): void
    {
        $this->assertRenders(NativeResetPasswordScreen::build(360.0, null, null));
    }

    public function testWidgetsForms(): void
    {
        $this->assertRenders(NativeWidgetsFormsScreen::build(360.0));
    }
}
