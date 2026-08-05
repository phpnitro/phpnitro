<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

/**
 * Checks (and if needed, prompts for) a dangerous Android runtime
 * permission — the generic counterpart to CameraButton/AudioRecorder's
 * OWN one-off permission handling: those two already knew exactly which
 * single permission they needed, but a screen wanting to gate some other
 * feature on a permission first (before that feature's own action even
 * exists yet) had no reusable way to ask without hand-wiring its own
 * NativeRenderPocActivity launcher.
 *
 * $permission is one of a fixed whitelist NativeRenderPocActivity itself
 * defines (see its own permissionKeys map) — 'camera', 'microphone',
 * 'location', 'coarse_location', 'contacts', 'calendar', 'notifications',
 * 'bluetooth' — not an arbitrary Android permission string. That
 * whitelist only covers permissions android/app/src/main/AndroidManifest.xml
 * already declares; asking for one this app never declared would throw
 * at the OS level with a confusing message far from the actual mistake,
 * so the Kotlin side rejects an unknown key up front instead
 * ("unknown_permission" comes back through $outputField the same way a
 * real grant/deny does).
 *
 * Result lands in $_GET[$outputField] as 'granted', 'denied', or
 * 'unknown_permission' — check for that value, not just truthiness.
 */
final class PermissionButton implements Widget
{
    private readonly Button $content;

    public function __construct(
        string $permission,
        string $label,
        string $outputField = 'permission_out',
        ?Color $background = null,
    ) {
        $this->content = new Button($label, "device:permission:{$permission}:{$outputField}", background: $background);
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
