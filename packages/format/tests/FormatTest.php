<?php

namespace Engine\Format\Tests;

use Engine\Format\Format;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    public function testNumberWithFrenchDefaults(): void
    {
        $this->assertSame('1 234', Format::number(1234));
        $this->assertSame('1 234,50', Format::number(1234.5, 2));
    }

    public function testCurrencyKnownCode(): void
    {
        $this->assertSame('1 234,50 €', Format::currency(1234.5, 'EUR'));
        $this->assertSame('1 000,00 FCFA', Format::currency(1000, 'XOF'));
    }

    public function testCurrencyUnknownCodeFallsBackToCode(): void
    {
        $this->assertSame('10,00 ZZZ', Format::currency(10, 'ZZZ'));
    }

    public function testDateFrench(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');

        $this->assertSame('21 juillet 2026', Format::date($date));
    }

    public function testDateEnglish(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');

        $this->assertSame('July 2026', Format::date($date, 'MMMM yyyy', 'en'));
    }

    public function testRelativeTimePast(): void
    {
        $now = new \DateTimeImmutable('2026-07-21 12:00:00');
        $threeHoursAgo = $now->modify('-3 hours');

        $this->assertSame('il y a 3 heures', Format::relativeTime($threeHoursAgo, $now));
    }

    public function testRelativeTimeFuture(): void
    {
        $now = new \DateTimeImmutable('2026-07-21 12:00:00');
        $inTwoDays = $now->modify('+2 days');

        $this->assertSame('dans 2 jours', Format::relativeTime($inTwoDays, $now));
    }

    public function testRelativeTimeJustNow(): void
    {
        $now = new \DateTimeImmutable('2026-07-21 12:00:00');
        $tenSecondsAgo = $now->modify('-10 seconds');

        $this->assertSame("à l'instant", Format::relativeTime($tenSecondsAgo, $now));
    }
}
