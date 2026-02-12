<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoShapeValue;
use Jackardios\ElasticQueryWizard\Filters\GeoShapeFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class GeoShapeFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_envelope_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[-10.0, 10.0], [10.0, -10.0]],
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'geo_shape' => [
                'boundary' => [
                    'shape' => [
                        'type' => 'envelope',
                        'coordinates' => [[-10.0, 10.0], [10.0, -10.0]],
                    ],
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_builds_polygon_query(): void
    {
        $coordinates = [
            [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]],
        ];

        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'polygon',
                    'coordinates' => $coordinates,
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        // Note: es-scout-driver wraps polygon coordinates in an extra array level
        $this->assertEquals([
            'geo_shape' => [
                'boundary' => [
                    'shape' => [
                        'type' => 'polygon',
                        'coordinates' => [$coordinates],
                    ],
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_builds_point_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'location' => [
                    'type' => 'point',
                    'coordinates' => [37.62, 55.75],
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('location'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'point',
                        'coordinates' => [37.62, 55.75],
                    ],
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_builds_circle_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'location' => [
                    'type' => 'circle',
                    'coordinates' => [37.62, 55.75],
                    'radius' => '10km',
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('location'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'circle',
                        'coordinates' => [37.62, 55.75],
                        'radius' => '10km',
                    ],
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_builds_indexed_shape_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'indexed_shape',
                    'index' => 'shapes',
                    'id' => 'region_123',
                    'path' => 'location',
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'geo_shape' => [
                'boundary' => [
                    'indexed_shape' => [
                        'index' => 'shapes',
                        'id' => 'region_123',
                        'path' => 'location',
                    ],
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_builds_indexed_shape_query_with_default_path(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'indexed_shape',
                    'index' => 'shapes',
                    'id' => 'region_123',
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'geo_shape' => [
                'boundary' => [
                    'indexed_shape' => [
                        'index' => 'shapes',
                        'id' => 'region_123',
                        'path' => 'shape',
                    ],
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_relation_parameter(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[-10.0, 10.0], [10.0, -10.0]],
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary')->relation('within'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'geo_shape' => [
                'boundary' => [
                    'shape' => [
                        'type' => 'envelope',
                        'coordinates' => [[-10.0, 10.0], [10.0, -10.0]],
                    ],
                    'relation' => 'within',
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_ignore_unmapped(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'point',
                    'coordinates' => [37.62, 55.75],
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary')->ignoreUnmapped());
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        // Note: ignore_unmapped is placed at geo_shape level, not inside the field object
        $this->assertEquals([
            'geo_shape' => [
                'boundary' => [
                    'shape' => [
                        'type' => 'point',
                        'coordinates' => [37.62, 55.75],
                    ],
                ],
                'ignore_unmapped' => true,
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_throws_for_unknown_type(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('has unknown shape type');

        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'unknown_shape',
                    'coordinates' => [0, 0],
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();
    }

    /** @test */
    public function it_throws_for_missing_type(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('has unknown shape type `null`');

        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'coordinates' => [0, 0],
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();
    }

    /** @test */
    public function it_throws_for_invalid_envelope(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('envelope requires coordinates');

        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[0, 0]], // Only one point instead of two
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();
    }

    /** @test */
    public function it_throws_for_invalid_polygon(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('polygon requires coordinates');

        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'polygon',
                    'coordinates' => [], // Empty coordinates
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();
    }

    /** @test */
    public function it_throws_for_invalid_point(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('point requires coordinates');

        $wizard = $this
            ->createElasticWizardWithFilters([
                'location' => [
                    'type' => 'point',
                    'coordinates' => [0], // Only one value instead of two
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('location'));
        $wizard->build();
    }

    /** @test */
    public function it_throws_for_invalid_circle(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('circle requires coordinates');

        $wizard = $this
            ->createElasticWizardWithFilters([
                'location' => [
                    'type' => 'circle',
                    'coordinates' => [37.62, 55.75],
                    'radius' => 100, // Numeric instead of string
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('location'));
        $wizard->build();
    }

    /** @test */
    public function it_throws_for_invalid_indexed_shape(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('indexed_shape requires');

        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'indexed_shape',
                    'index' => 'shapes',
                    // Missing 'id'
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();
    }

    /** @test */
    public function it_does_not_add_query_for_empty_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['boundary' => []])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_does_not_add_query_for_null_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['boundary' => null])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_does_not_add_query_for_non_array_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['boundary' => 'invalid'])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_uses_alias_correctly(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'area' => [
                    'type' => 'point',
                    'coordinates' => [37.62, 55.75],
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary', 'area'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('geo_shape', $queries[0]);
        $this->assertArrayHasKey('boundary', $queries[0]['geo_shape']);
    }

    /** @test */
    public function it_returns_correct_type(): void
    {
        $filter = GeoShapeFilter::make('boundary');

        $this->assertEquals('geo_shape', $filter->getType());
    }

    /** @test */
    public function it_combines_relation_and_ignore_unmapped(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[-10.0, 10.0], [10.0, -10.0]],
                ],
            ])
            ->allowedFilters(
                GeoShapeFilter::make('boundary')
                    ->relation('intersects')
                    ->ignoreUnmapped()
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        // Note: relation is inside field object, ignore_unmapped is at geo_shape level
        $this->assertEquals([
            'geo_shape' => [
                'boundary' => [
                    'shape' => [
                        'type' => 'envelope',
                        'coordinates' => [[-10.0, 10.0], [10.0, -10.0]],
                    ],
                    'relation' => 'intersects',
                ],
                'ignore_unmapped' => true,
            ],
        ], $queries[0]);
    }
}
