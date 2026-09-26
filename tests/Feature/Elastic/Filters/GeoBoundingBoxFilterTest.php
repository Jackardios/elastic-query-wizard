<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Filters\GeoBoundingBoxFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('elastic')]
#[Group('filter')]
#[Group('elastic-filter')]
class GeoBoundingBoxFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = GeoModel::factory()->count(5)->create();
    }

    #[Test]
    public function it_throws_an_exception_when_invalid_value_provided(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        $this
            ->createQueryFromFilterRequest([
                'bbox' => '29.8431393959961,59.70658123789505,30.76667760400391',
            ])
            ->allowedFilters(GeoBoundingBoxFilter::make('location', 'bbox'))
            ->build();
    }

    #[Test]
    public function it_allows_empty_filter_value(): void
    {
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'bbox' => '',
            ])
            ->allowedFilters(GeoBoundingBoxFilter::make('location', 'bbox'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    #[Test]
    public function it_can_filter_results(): void
    {
        $expectedModels[] = GeoModel::factory()->create(['lat' => 59.933237, 'lon' => 30.3694531]);
        $expectedModels[] = GeoModel::factory()->create(['lat' => 59.973454, 'lon' => 30.5493402]);
        $expectedModels[] = GeoModel::factory()->create(['lat' => 59.706582, 'lon' => 30.1243467]);
        $expectedModels[] = GeoModel::factory()->create(['lat' => 60.103454, 'lon' => 29.8745233]);
        GeoModel::factory()->create(['lat' => 61.973454, 'lon' => 30.5493402]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'bbox' => '29.8431393959961,59.70658123789505,30.76667760400391,60.12821910231846',
            ])
            ->allowedFilters(GeoBoundingBoxFilter::make('location', 'bbox'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn ($model) => $model->id, $expectedModels)
        );
    }

    #[Test]
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        $expectedModels[] = GeoModel::factory()->create(['lat' => 59.933237, 'lon' => 30.3694531]);
        $expectedModels[] = GeoModel::factory()->create(['lat' => 59.973454, 'lon' => 30.5493402]);
        $expectedModels[] = GeoModel::factory()->create(['lat' => 59.706582, 'lon' => 30.1243467]);
        $expectedModels[] = GeoModel::factory()->create(['lat' => 60.103454, 'lon' => 29.8745233]);
        GeoModel::factory()->create(['lat' => 61.973454, 'lon' => 30.5493402]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([])
            ->allowedFilters(
                (GeoBoundingBoxFilter::make('location', 'bbox'))
                    ->default([29.8431393959961, 59.70658123789505, 30.76667760400391, 60.12821910231846])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn ($model) => $model->id, $expectedModels)
        );
    }

    #[Test]
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $expectedModels[] = GeoModel::factory()->create(['lat' => 59.933237, 'lon' => 30.3694531]);
        $expectedModels[] = GeoModel::factory()->create(['lat' => 59.973454, 'lon' => 30.5493402]);
        $expectedModels[] = GeoModel::factory()->create(['lat' => 59.706582, 'lon' => 30.1243467]);
        $expectedModels[] = GeoModel::factory()->create(['lat' => 60.103454, 'lon' => 29.8745233]);
        GeoModel::factory()->create(['lat' => 61.973454, 'lon' => 30.5493402]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'bbox' => '29.8431393959961,59.70658123789505,30.76667760400391,60.12821910231846',
            ])
            ->allowedFilters(
                (GeoBoundingBoxFilter::make('location', 'bbox'))
                    ->default([36.461995, 55.105673, 38.309071, 56.056992])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn ($model) => $model->id, $expectedModels)
        );
    }

    #[Test]
    public function it_can_filter_results_with_antimeridian_crossing_bbox(): void
    {
        $insideLeft = GeoModel::factory()->create(['lat' => 0.0, 'lon' => 179.5]);
        $insideRight = GeoModel::factory()->create(['lat' => 0.0, 'lon' => -179.5]);
        GeoModel::factory()->create(['lat' => 0.0, 'lon' => -160.0]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'bbox' => '170,-10,-170,10',
            ])
            ->allowedFilters(GeoBoundingBoxFilter::make('location', 'bbox'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing(
            [$insideLeft->id, $insideRight->id],
            $modelsResult->pluck('id')->toArray()
        );
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        return $this->createElasticWizardWithFilters($filters, GeoModel::class);
    }
}
