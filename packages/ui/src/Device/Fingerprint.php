<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Device;

/**
 * Shows a real androidx.biometric prompt (fingerprint/face unlock — see
 * NativeDeviceBridge.kt's showBiometricPrompt()) — an action-string
 * builder, not a widget: attach Fingerprint::authenticateAction() to any
 * Button.
 *
 * No permission to request — BIOMETRIC_STRONG availability is checked at
 * prompt time, not beforehand. Result lands in $_GET[$outputField] as
 * "Authentifié" on success, or a human-readable reason on failure/
 * unavailability ("Aucune empreinte/visage enregistré sur ce téléphone.",
 * etc. — see NativeDeviceBridge.kt's biometricUnavailableReason()).
 */
final class Fingerprint
{
    public static function authenticateAction(string $outputField = 'biometric_out'): string
    {
        return "device:biometric:{$outputField}";
    }

    public static function result(string $outputField = 'biometric_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
