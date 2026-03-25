<?php

use PHPUnit\Framework\TestCase;

class MapfileGeneratorTest extends TestCase
{
    private MapfileGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MapfileGenerator('/maps', '/fonts');
    }

    private function generate(array $overrides = []): string
    {
        return $this->generator->generate(array_merge([
            'projection' => 'epsg:4326',
            'layers'     => ['outline'],
            'points'     => [],
            'output'     => 'png',
            'width'      => 900,
            'height'     => 450,
        ], $overrides));
    }

    public function testOutputIsValidMapfile(): void
    {
        $mapfile = $this->generate();
        $this->assertStringStartsWith('MAP', $mapfile);
        $this->assertStringEndsWith("END\n", $mapfile);
    }

    public function testMapDimensionsAreSet(): void
    {
        $mapfile = $this->generate(['width' => 800, 'height' => 400]);
        $this->assertStringContainsString('SIZE 800 400', $mapfile);
    }

    public function testGeographicProjectionUsesDD(): void
    {
        $mapfile = $this->generate(['projection' => 'epsg:4326']);
        $this->assertStringContainsString('UNITS DD', $mapfile);
    }

    public function testProjectedCrsUsesMeters(): void
    {
        $mapfile = $this->generate(['projection' => 'esri:102009']);
        $this->assertStringContainsString('UNITS METERS', $mapfile);
    }

    public function testDefaultExtentUsedForProjectedCrs(): void
    {
        $mapfile = $this->generate(['projection' => 'esri:102009']);
        $extent = Projections::get('esri:102009')['extent'];
        $this->assertStringContainsString(
            "EXTENT {$extent[0]} {$extent[1]} {$extent[2]} {$extent[3]}",
            $mapfile
        );
    }

    public function testRawBboxBypassesDefaultExtent(): void
    {
        $mapfile = $this->generate([
            'projection' => 'esri:102009',
            'bbox'       => [100000, 200000, 500000, 600000],
            'raw_bbox'   => true,
        ]);
        $this->assertStringContainsString('EXTENT 100000 200000 500000 600000', $mapfile);
    }

    public function testWrap180AppliedForLambertProjection(): void
    {
        $mapfile = $this->generate(['projection' => 'esri:102015']);
        $this->assertStringContainsString('PROCESSING "WRAP=180"', $mapfile);
    }

    public function testWrap180NotAppliedForSouthPole(): void
    {
        $mapfile = $this->generate(['projection' => 'epsg:102019']);
        $this->assertStringNotContainsString('PROCESSING "WRAP=180"', $mapfile);
    }

    public function testWrap180NotAppliedForNorthPole(): void
    {
        $mapfile = $this->generate(['projection' => 'epsg:102017']);
        $this->assertStringNotContainsString('PROCESSING "WRAP=180"', $mapfile);
    }

    public function testPolarProjectionUsesLandOutline(): void
    {
        $mapfile = $this->generate([
            'projection' => 'epsg:102019',
            'layers'     => ['outline'],
        ]);
        $this->assertStringContainsString('ne_10m_land', $mapfile);
    }

    public function testNonPolarProjectionUsesCoastline(): void
    {
        $mapfile = $this->generate([
            'projection' => 'esri:102015',
            'layers'     => ['outline'],
        ]);
        $this->assertStringContainsString('ne_10m_coastline', $mapfile);
    }

    public function testLayersAppearInMapfile(): void
    {
        $mapfile = $this->generate(['layers' => ['outline', 'countries']]);
        $this->assertStringContainsString('NAME "outline"', $mapfile);
        $this->assertStringContainsString('NAME "countries"', $mapfile);
    }

    public function testUnknownLayersAreSkipped(): void
    {
        $mapfile = $this->generate(['layers' => ['outline', 'nonexistent']]);
        $this->assertStringNotContainsString('NAME "nonexistent"', $mapfile);
    }

    public function testPointLayerIsGenerated(): void
    {
        $mapfile = $this->generate([
            'points' => [[
                'legend'      => 'Test species',
                'shape'       => 'circle',
                'size'        => 8,
                'color'       => [255, 0, 0],
                'coordinates' => [['lat' => 45.5, 'lon' => -75.5]],
            ]],
        ]);
        $this->assertStringContainsString('NAME "points_0"', $mapfile);
        $this->assertStringContainsString('Test species', $mapfile);
        $this->assertStringContainsString('-75.5 45.5', $mapfile);
    }
}
