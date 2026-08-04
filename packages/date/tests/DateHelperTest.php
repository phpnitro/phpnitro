<?php

namespace Engine\Date\Tests;

use Engine\Date\DateHelper;
use PHPUnit\Framework\TestCase;

final class DateHelperTest extends TestCase
{
    public function testParseAcceptsStringOrInstance(): void
    {
        $fromString = DateHelper::parse('2026-07-21');
        $fromInstance = DateHelper::parse(new \DateTimeImmutable('2026-07-21'));

        $this->assertSame('2026-07-21', $fromString->format('Y-m-d'));
        $this->assertSame('2026-07-21', $fromInstance->format('Y-m-d'));
    }

    public function testToIso(): void
    {
        $this->assertSame(
            (new \DateTimeImmutable('2026-07-21 12:00:00'))->format(DATE_ATOM),
            DateHelper::toIso('2026-07-21 12:00:00'),
        );
    }

    public function testAddDaysHoursMinutes(): void
    {
        $this->assertSame('2026-07-24', DateHelper::addDays('2026-07-21', 3)->format('Y-m-d'));
        $this->assertSame('2026-07-18', DateHelper::addDays('2026-07-21', -3)->format('Y-m-d'));
        $this->assertSame('15:00:00', DateHelper::addHours('2026-07-21 12:00:00', 3)->format('H:i:s'));
        $this->assertSame('12:30:00', DateHelper::addMinutes('2026-07-21 12:00:00', 30)->format('H:i:s'));
    }

    public function testDiffs(): void
    {
        $from = '2026-07-21 12:00:00';
        $to = '2026-07-22 14:30:00';

        $this->assertSame(95_400, DateHelper::diffInSeconds($from, $to));
        $this->assertSame(1590, DateHelper::diffInMinutes($from, $to));
        $this->assertSame(26, DateHelper::diffInHours($from, $to));
        $this->assertSame(1, DateHelper::diffInDays($from, $to));
    }

    public function testIsPastAndIsFuture(): void
    {
        $this->assertTrue(DateHelper::isPast('2000-01-01'));
        $this->assertFalse(DateHelper::isFuture('2000-01-01'));
        $this->assertTrue(DateHelper::isFuture('2999-01-01'));
        $this->assertFalse(DateHelper::isPast('2999-01-01'));
    }

    public function testIsToday(): void
    {
        $this->assertTrue(DateHelper::isToday(DateHelper::now()));
        $this->assertFalse(DateHelper::isToday('2000-01-01'));
    }

    public function testIsWeekend(): void
    {
        $this->assertTrue(DateHelper::isWeekend('2026-07-25')); // a Saturday
        $this->assertTrue(DateHelper::isWeekend('2026-07-26')); // a Sunday
        $this->assertFalse(DateHelper::isWeekend('2026-07-21')); // a Tuesday
    }

    public function testStartAndEndOfDay(): void
    {
        $this->assertSame('2026-07-21 00:00:00', DateHelper::startOfDay('2026-07-21 15:30:00')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-21 23:59:59', DateHelper::endOfDay('2026-07-21 15:30:00')->format('Y-m-d H:i:s'));
    }

    public function testStartAndEndOfMonth(): void
    {
        $this->assertSame('2026-07-01 00:00:00', DateHelper::startOfMonth('2026-07-21')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-31 23:59:59', DateHelper::endOfMonth('2026-07-21')->format('Y-m-d H:i:s'));
    }

    public function testAge(): void
    {
        $this->assertSame(26, DateHelper::age('2000-07-21', '2026-07-21'));
        $this->assertSame(25, DateHelper::age('2000-07-22', '2026-07-21'));
    }

    public function testHumanizeJustNow(): void
    {
        $now = new \DateTimeImmutable('2026-07-21 12:00:00');
        $this->assertSame("à l'instant", DateHelper::humanize($now->modify('-5 seconds'), $now));
    }

    public function testHumanizePast(): void
    {
        $now = new \DateTimeImmutable('2026-07-21 12:00:00');
        $this->assertSame('il y a 3 heures', DateHelper::humanize($now->modify('-3 hours'), $now));
        $this->assertSame('il y a 1 jour', DateHelper::humanize($now->modify('-1 day'), $now));
    }

    public function testHumanizeFuture(): void
    {
        $now = new \DateTimeImmutable('2026-07-21 12:00:00');
        $this->assertSame('dans 2 jours', DateHelper::humanize($now->modify('+2 days'), $now));
    }

    public function testHumanizeMoisIsInvariant(): void
    {
        $now = new \DateTimeImmutable('2026-07-21 12:00:00');
        $this->assertSame('il y a 2 mois', DateHelper::humanize($now->modify('-2 months'), $now));
    }
}
