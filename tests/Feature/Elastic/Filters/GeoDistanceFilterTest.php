<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Filters\GeoDistanceFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class GeoDistanceFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = GeoModel::factory()->count(5)->create();
    }

    /** @test */
    public function it_throws_an_exception_when_invalid_value_provided(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        $this->createQueryFromFilterRequest([
                'location_distance' => 'some invalid value'
            ])
            ->allowedFilters(GeoDistanceFilter::make('location', 'location_distance'))
            ->build();
    }

    /** @test */
    public function it_throws_an_exception_when_missing_array_key(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        $this->createQueryFromFilterRequest([
            'location_distance' => [
                'lon' => '30.33701339770632',
                'lat' => '59.934366587863444'
            ]
        ])
            ->allowedFilters(GeoDistanceFilter::make('location', 'location_distance'))
            ->build();
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'location_distance' => ''
            ])
            ->allowedFilters(GeoDistanceFilter::make('location', 'location_distance'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results(): void
    {
        $expectedModels[] = GeoModel::factory()->create(['location' => new Point(30.328443362817065, 59.939403630916246)]);
        $expectedModels[] = GeoModel::factory()->create(['location' => new Point(30.346954797823454, 59.9319304942638)]);
        $expectedModels[] = GeoModel::factory()->create(['location' => new Point(30.353005861360433, 59.93296431053539)]);
        GeoModel::factory()->create(['location' => new Point(30.35564044552315, 59.93290463173133)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'location_distance' => [
                    'lon' => '30.33701339770632',
                    'lat' => '59.934366587863444',
                    'distance' => '1km'
                ]
            ])
            ->allowedFilters(GeoDistanceFilter::make('location', 'location_distance'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn($model) => $model->id, $expectedModels)
        );
    }

    /** @test */
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        $expectedModels[] = GeoModel::factory()->create(['location' => new Point(30.328443362817065, 59.939403630916246)]);
        $expectedModels[] = GeoModel::factory()->create(['location' => new Point(30.346954797823454, 59.9319304942638)]);
        $expectedModels[] = GeoModel::factory()->create(['location' => new Point(30.353005861360433, 59.93296431053539)]);
        GeoModel::factory()->create(['location' => new Point(30.35564044552315, 59.93290463173133)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([])
            ->allowedFilters(
                (GeoDistanceFilter::make('location', 'location_distance'))
                    ->default([
                        'lon' => '30.33701339770632',
                        'lat' => '59.934366587863444',
                        'distance' => '1km'
                    ])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn($model) => $model->id, $expectedModels)
        );
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $expectedModels[] = GeoModel::factory()->create(['location' => new Point(30.328443362817065, 59.939403630916246)]);
        $expectedModels[] = GeoModel::factory()->create(['location' => new Point(30.346954797823454, 59.9319304942638)]);
        $expectedModels[] = GeoModel::factory()->create(['location' => new Point(30.353005861360433, 59.93296431053539)]);
        GeoModel::factory()->create(['location' => new Point(30.35564044552315, 59.93290463173133)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'location_distance' => [
                    'lon' => '30.33701339770632',
                    'lat' => '59.934366587863444',
                    'distance' => '1km'
                ]
            ])
            ->allowedFilters(
                (GeoDistanceFilter::make('location', 'location_distance'))
                    ->default([
                        'lon' => '34.33701339770632',
                        'lat' => '55.934366587863444',
                        'distance' => '2km'
                    ])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn($model) => $model->id, $expectedModels)
        );
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        return $this->createElasticWizardWithFilters($filters, GeoModel::class);
    }
}
