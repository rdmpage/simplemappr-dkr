<?php

use PHPUnit\Framework\TestCase;

class LayersTest extends TestCase
{
    public function testAllLayersHaveRequiredKeys(): void
    {
        foreach (Layers::all() as $name => $layer) {
            $this->assertArrayHasKey('name', $layer, "Layer $name missing 'name'");
            $this->assertArrayHasKey('data', $layer, "Layer $name missing 'data'");
            $this->assertArrayHasKey('type', $layer, "Layer $name missing 'type'");
        }
    }

    public function testLayerTypesAreValid(): void
    {
        $validTypes = ['LINE', 'POLYGON', 'POINT', 'RASTER'];
        foreach (Layers::all() as $name => $layer) {
            $this->assertContains($layer['type'], $validTypes, "Layer $name has invalid type");
        }
        foreach (Layers::raster() as $name => $layer) {
            $this->assertEquals('RASTER', $layer['type'], "Raster layer $name must have type RASTER");
        }
    }

    public function testGetReturnsVectorLayer(): void
    {
        $layer = Layers::get('outline');
        $this->assertNotNull($layer);
        $this->assertEquals('LINE', $layer['type']);
    }

    public function testGetReturnsRasterLayer(): void
    {
        $layer = Layers::get('relief');
        $this->assertNotNull($layer);
        $this->assertEquals('RASTER', $layer['type']);
    }

    public function testGetReturnsNullForUnknown(): void
    {
        $this->assertNull(Layers::get('nonexistent_layer'));
    }

    public function testExistsChecksVectorAndRaster(): void
    {
        $this->assertTrue(Layers::exists('countries'));
        $this->assertTrue(Layers::exists('relief'));
        $this->assertFalse(Layers::exists('bogus'));
    }

    public function testOutlineUsesCoastlineData(): void
    {
        $layer = Layers::get('outline');
        $this->assertStringContainsString('ne_10m_coastline', $layer['data']);
    }

    public function testCountriesUsesBoundaryLinesData(): void
    {
        $layer = Layers::get('countries');
        $this->assertStringContainsString('boundary_lines', $layer['data']);
    }
}
