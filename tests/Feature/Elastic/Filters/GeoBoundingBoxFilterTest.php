<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Handlers\Filters\GeoBoundingBoxFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class GeoBoundingBoxFilterTest extends TestCase
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
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        $this
            ->createQueryFromFilterRequest([
                'bbox' => '29.8431393959961,59.70658123789505,30.76667760400391'
            ])
            ->setAllowedFilters(new GeoBoundingBoxFilter('location', 'bbox'))
            ->build();
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'bbox' => ''
            ])
            ->setAllowedFilters(new GeoBoundingBoxFilter('location', 'bbox'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results(): void
    {
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(30.3694531, 59.933237)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(30.5493402, 59.973454)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(30.1243467, 59.706582)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(29.8745233, 60.103454)]);
        factory(GeoModel::class)->create(['location' => new Point(30.5493402, 61.973454)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'bbox' => '29.8431393959961,59.70658123789505,30.76667760400391,60.12821910231846',
            ])
            ->setAllowedFilters(new GeoBoundingBoxFilter('location', 'bbox'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn($model) => $model->id, $expectedModels)
        );
    }

    /** @test */
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(30.3694531, 59.933237)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(30.5493402, 59.973454)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(30.1243467, 59.706582)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(29.8745233, 60.103454)]);
        factory(GeoModel::class)->create(['location' => new Point(30.5493402, 61.973454)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([])
            ->setAllowedFilters(
                (new GeoBoundingBoxFilter('location', 'bbox'))
                    ->default([29.8431393959961,59.70658123789505,30.76667760400391,60.12821910231846])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn($model) => $model->id, $expectedModels)
        );
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(30.3694531, 59.933237)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(30.5493402, 59.973454)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(30.1243467, 59.706582)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(29.8745233, 60.103454)]);
        factory(GeoModel::class)->create(['location' => new Point(30.5493402, 61.973454)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'bbox' => '29.8431393959961,59.70658123789505,30.76667760400391,60.12821910231846'
            ])
            ->setAllowedFilters(
                (new GeoBoundingBoxFilter('location', 'bbox'))
                    ->default([36.461995,55.105673,38.309071,56.056992])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $modelsResult);
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
