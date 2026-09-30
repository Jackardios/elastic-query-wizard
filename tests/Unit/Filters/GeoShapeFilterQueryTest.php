<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoShapeValue;
use Jackardios\ElasticQueryWizard\Filters\GeoShapeFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class GeoShapeFilterQueryTest extends UnitTestCase
{
    #[Test]
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

    #[Test]
    public function it_builds_polygon_query(): void
    {
        // GeoJSON polygon format: coordinates = [outer_ring, hole1, hole2, ...]
        $outerRing = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]];
        $coordinates = [$outerRing];

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
        // es-scout-driver polygon() expects outer ring and wraps it in array
        $this->assertEquals([
            'geo_shape' => [
                'boundary' => [
                    'shape' => [
                        'type' => 'polygon',
                        'coordinates' => [$outerRing],
                    ],
                ],
            ],
        ], $queries[0]);
    }

    #[Test]
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

    #[Test]
    public function it_builds_indexed_shape_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'indexed_shape',
                    'id' => 'region_123',
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary')->indexedShapes('shapes', 'location'));
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

    #[Test]
    public function it_builds_indexed_shape_query_with_default_path(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'indexed_shape',
                    'id' => 'region_123',
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary')->indexedShapes('shapes'));
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_throws_for_unknown_type(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('Unsupported shape type `unknown_shape`');

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

    #[Test]
    public function it_throws_for_missing_type(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('Expected a shape `type`');

        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'coordinates' => [0, 0],
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();
    }

    #[Test]
    public function it_throws_for_invalid_envelope(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('An envelope expects coordinates');

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

    #[Test]
    public function it_throws_for_invalid_polygon(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('A polygon expects coordinates');

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

    #[Test]
    public function it_throws_for_invalid_point(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('A point expects coordinates');

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

    #[Test]
    public function it_throws_for_invalid_indexed_shape(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('An indexed shape expects');

        $wizard = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'indexed_shape',
                ],
            ])
            ->allowedFilters(GeoShapeFilter::make('boundary')->indexedShapes('shapes'));
        $wizard->build();
    }

    #[Test]
    public function it_does_not_add_query_for_empty_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['boundary' => []])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_does_not_add_query_for_null_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['boundary' => null])
            ->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_rejects_a_value_that_is_not_a_shape(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('Expected a shape `type`');

        $this
            ->createElasticWizardWithFilters(['boundary' => 'invalid'])
            ->allowedFilters(GeoShapeFilter::make('boundary'))
            ->build();
    }

    #[Test]
    public function an_indexed_shape_is_refused_unless_the_filter_enables_indexed_shapes(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);
        $this->expectExceptionMessage('Unsupported shape type `indexed_shape`');

        $this
            ->createElasticWizardWithFilters(['boundary' => ['type' => 'indexed_shape', 'id' => 'region_123']])
            ->allowedFilters(GeoShapeFilter::make('boundary'))
            ->build();
    }

    #[Test]
    public function the_client_cannot_choose_the_index_or_path_of_an_indexed_shape(): void
    {
        foreach (['index' => 'users', 'path' => 'email'] as $key => $keyValue) {
            try {
                $this
                    ->createElasticWizardWithFilters(['boundary' => ['type' => 'indexed_shape', 'id' => '1', $key => $keyValue]])
                    ->allowedFilters(GeoShapeFilter::make('boundary')->indexedShapes('shapes'))
                    ->build();
                $this->fail("`{$key}` was accepted.");
            } catch (InvalidGeoShapeValue $exception) {
                $this->assertStringContainsString('An indexed shape expects only an `id`', $exception->getMessage());
            }
        }
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function a_polygon_keeps_its_holes(): void
    {
        $outer = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]];
        $hole = [[4.0, 4.0], [6.0, 4.0], [6.0, 6.0], [4.0, 6.0], [4.0, 4.0]];

        $this->assertSame(['type' => 'polygon', 'coordinates' => [$outer, $hole]], $this->shapeOf(['type' => 'polygon', 'coordinates' => [$outer, $hole]]));
    }

    #[Test]
    public function an_open_ring_is_closed(): void
    {
        $this->assertSame(
            ['type' => 'polygon', 'coordinates' => [[[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 0.0]]]],
            $this->shapeOf(['type' => 'polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10]]]])
        );
    }

    #[Test]
    public function a_ring_of_fewer_than_four_points_after_closing_is_refused(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);

        $this->shapeOf(['type' => 'polygon', 'coordinates' => [[[0, 0], [10, 0]]]]);
    }

    #[Test]
    public function an_invalid_hole_is_refused(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);

        $this->shapeOf(['type' => 'polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 0]], [[4, 4], ['x', 4]]]]);
    }

    #[Test]
    public function an_infinite_envelope_coordinate_is_refused(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);

        $this->shapeOf(['type' => 'envelope', 'coordinates' => [['1e999', '50'], ['10', '40']]]);
    }

    #[Test]
    public function an_infinite_point_coordinate_is_refused(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);

        $this->shapeOf(['type' => 'point', 'coordinates' => ['1e999', '50']]);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function shapeOf(array $value): array
    {
        $wizard = $this->createElasticWizardWithFilters(['boundary' => $value])->allowedFilters(GeoShapeFilter::make('boundary'));
        $wizard->build();

        return $this->getFilterQueries($wizard->boolQuery())[0]['geo_shape']['boundary']['shape'];
    }
}
