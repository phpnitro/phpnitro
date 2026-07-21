<?php

namespace Engine\Countries\Tests;

use Engine\Countries\Continent;
use Engine\Countries\Countries;
use PHPUnit\Framework\TestCase;

final class CountriesTest extends TestCase
{
    public function testAllReturnsAllCountries(): void
    {
        $countries = Countries::all();

        // 194 UN member/independent states at the time this data was
        // generated (see DATA_LICENSE.md) — a sanity range, not an exact
        // pin, since sovereignty/recognition changes over time.
        $this->assertGreaterThan(190, count($countries));
        $this->assertLessThan(200, count($countries));
    }

    public function testFindByAlpha2Code(): void
    {
        $france = Countries::find('FR');

        $this->assertNotNull($france);
        $this->assertSame('France', $france->name);
        $this->assertSame('Paris', $france->capital);
        $this->assertSame(Continent::EUROPE, $france->continent);
        $this->assertSame('+33', $france->callingCode);
        $this->assertSame('EUR', $france->currency);
    }

    public function testFindByAlpha3CodeIsCaseInsensitive(): void
    {
        $benin = Countries::find('ben');

        $this->assertNotNull($benin);
        $this->assertSame('Benin', $benin->name);
        $this->assertSame('Bénin', $benin->nameFr);
    }

    public function testFindReturnsNullForUnknownCode(): void
    {
        $this->assertNull(Countries::find('XX'));
    }

    public function testFlagIsComputedFromIsoCode(): void
    {
        $japan = Countries::find('JP');

        $this->assertSame("\u{1F1EF}\u{1F1F5}", $japan->flag());
    }

    public function testCitiesIncludesCapitalForMajorCountry(): void
    {
        $france = Countries::find('FR');
        $cities = $france->cities();

        $this->assertContains('Paris', $cities);
        $this->assertGreaterThan(1, count($cities));
    }

    public function testSearchMatchesFrenchOrEnglishName(): void
    {
        $results = Countries::search('allemagne');

        $this->assertNotEmpty($results);
        $this->assertSame('Germany', $results[0]->name);
    }

    public function testSearchIsCaseInsensitiveOnEnglishName(): void
    {
        $results = Countries::search('GERMANY');

        $this->assertNotEmpty($results);
        $this->assertSame('Germany', $results[0]->name);
    }

    public function testByContinentFiltersCorrectly(): void
    {
        $european = Countries::byContinent(Continent::EUROPE);

        $this->assertNotEmpty($european);
        foreach ($european as $country) {
            $this->assertSame(Continent::EUROPE, $country->continent);
        }
    }

    public function testContinentLabelsAreFrench(): void
    {
        $this->assertSame('Amérique du Nord', Continent::NORTH_AMERICA->label());
        $this->assertSame('Océanie', Continent::OCEANIA->label());
    }
}
