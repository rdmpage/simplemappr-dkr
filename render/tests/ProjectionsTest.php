<?php

use PHPUnit\Framework\TestCase;

class ProjectionsTest extends TestCase
{
    public function testAllProjectionsHaveRequiredKeys(): void
    {
        foreach (Projections::all() as $code => $proj) {
            $this->assertArrayHasKey('name', $proj, "Projection $code missing 'name'");
            $this->assertArrayHasKey('proj', $proj, "Projection $code missing 'proj'");
            $this->assertArrayHasKey('extent', $proj, "Projection $code missing 'extent'");
            $this->assertCount(4, $proj['extent'], "Projection $code extent must have 4 values");
        }
    }

    public function testExtentsAreOrdered(): void
    {
        foreach (Projections::all() as $code => $proj) {
            [$minX, $minY, $maxX, $maxY] = $proj['extent'];
            $this->assertLessThan($maxX, $minX, "Projection $code: minX must be less than maxX");
            $this->assertLessThan($maxY, $minY, "Projection $code: minY must be less than maxY");
        }
    }

    public function testGetReturnsKnownProjection(): void
    {
        $proj = Projections::get('epsg:4326');
        $this->assertNotNull($proj);
        $this->assertEquals('Geographic (WGS84)', $proj['name']);
        $this->assertEquals([-180, -90, 180, 90], $proj['extent']);
    }

    public function testGetIsCaseInsensitive(): void
    {
        $this->assertEquals(Projections::get('epsg:4326'), Projections::get('EPSG:4326'));
    }

    public function testGetReturnsNullForUnknown(): void
    {
        $this->assertNull(Projections::get('epsg:9999'));
    }

    public function testExistsReturnsTrueForKnown(): void
    {
        $this->assertTrue(Projections::exists('epsg:4326'));
        $this->assertTrue(Projections::exists('esri:102015'));
    }

    public function testExistsReturnsFalseForUnknown(): void
    {
        $this->assertFalse(Projections::exists('epsg:0000'));
    }
}
