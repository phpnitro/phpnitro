<?php

namespace Engine\Format\Tests;

use Engine\Format\Characters;
use PHPUnit\Framework\TestCase;

final class CharactersTest extends TestCase
{
    public function testLengthCountsGraphemesNotBytes(): void
    {
        $this->assertSame(5, Characters::length('héllo'));
    }

    public function testGraphemesSplitsFlagEmojiAsOneCharacter(): void
    {
        $graphemes = Characters::graphemes('a🇫🇷b');
        $this->assertSame(['a', '🇫🇷', 'b'], $graphemes);
    }

    public function testSubstring(): void
    {
        $this->assertSame('éllo', Characters::substring('héllo', 1));
        $this->assertSame('él', Characters::substring('héllo', 1, 2));
    }

    public function testSubstringKeepsZeroCharacter(): void
    {
        // '0' is falsy in PHP — `?: ''` used to swallow it entirely.
        $this->assertSame('0', Characters::substring('0123', 0, 1));
        $this->assertSame('01', Characters::substring('0123', 0, 2));
    }

    public function testReverse(): void
    {
        $this->assertSame('olléh', Characters::reverse('héllo'));
    }
}
