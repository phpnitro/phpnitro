<?php

namespace Engine\Connectivity\Tests;

use Engine\Connectivity\ConnectivityBadge;
use PHPUnit\Framework\TestCase;

final class ConnectivityBadgeTest extends TestCase
{
    public function testRendersDataAttributesForJsToPaint(): void
    {
        $html = ConnectivityBadge::make()->render();

        $this->assertStringContainsString('data-connectivity-badge', $html);
        $this->assertStringContainsString('data-online-label="En ligne"', $html);
        $this->assertStringContainsString('data-offline-label="Hors ligne"', $html);
    }

    public function testAcceptsCustomLabelsAndClasses(): void
    {
        $html = ConnectivityBadge::make('Connecté', 'Déconnecté', 'text-green-500', 'text-red-500')->render();

        $this->assertStringContainsString('data-online-label="Connecté"', $html);
        $this->assertStringContainsString('data-offline-label="Déconnecté"', $html);
        $this->assertStringContainsString('data-online-class="text-green-500"', $html);
        $this->assertStringContainsString('data-offline-class="text-red-500"', $html);
    }

    public function testEscapesLabels(): void
    {
        $html = ConnectivityBadge::make('<script>alert(1)</script>')->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
