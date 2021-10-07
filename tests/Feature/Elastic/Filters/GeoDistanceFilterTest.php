<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Handlers\Filters\GeoDistanceFilter;
use Jackardios\ElasticQueryWizard\Tests\App\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class GeoDistanceFilterTest extends TestCase
{
    /** @var Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(GeoModel::class, 5)->create();
    }

    /** @test */
    public function it_throws_an_exception_when_invalid_value_provided(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        $this->createQueryFromFilterRequest([
                'location_distance' => 'some invalid value'
            ])
            ->setAllowedFilters(new GeoDistanceFilter('location', 'location_distance'))
            ->build();
    }

    /** @test */
    public function it_throws_an_exception_when_missing_array_key(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        $this->createQueryFromFilterRequest([
            'location_distance' => [
                'lat' => '59.934366587863444',
                'lon' => '30.33701339770632'
            ]
        ])
            ->setAllowedFilters(new GeoDistanceFilter('location', 'location_distance'))
            ->build();
    }

    /** @test */
    public function it_can_filter_results(): void
    {
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.939403630916246,30.328443362817065)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.9319304942638,30.346954797823454)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.93296431053539,30.353005861360433)]);
        factory(GeoModel::class)->create(['location' => new Point(59.93290463173133,30.35564044552315)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'location_distance' => [
                    'lat' => '59.934366587863444',
                    'lon' => '30.33701339770632',
                    'distance' => '1km'
                ]
            ])
            ->setAllowedFilters(new GeoDistanceFilter('location', 'location_distance'))
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
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.939403630916246,30.328443362817065)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.9319304942638,30.346954797823454)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.93296431053539,30.353005861360433)]);
        factory(GeoModel::class)->create(['location' => new Point(59.93290463173133,30.35564044552315)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([])
            ->setAllowedFilters(
                (new GeoDistanceFilter('location', 'location_distance'))
                    ->default([
                        'lat' => '59.934366587863444',
                        'lon' => '30.33701339770632',
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
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.939403630916246,30.328443362817065)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.9319304942638,30.346954797823454)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.93296431053539,30.353005861360433)]);
        factory(GeoModel::class)->create(['location' => new Point(59.93290463173133,30.35564044552315)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'location_distance' => [
                    'lat' => '59.934366587863444',
                    'lon' => '30.33701339770632',
                    'distance' => '1km'
                ]
            ])
            ->setAllowedFilters(
                (new GeoDistanceFilter('location', 'location_distance'))
                    ->default([
                        'lat' => '55.934366587863444',
                        'lon' => '34.33701339770632',
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
        $request = new Request([
            'filter' => $filters,
        ]);

        return ElasticQueryWizard::for(GeoModel::class, $request);
    }
}
