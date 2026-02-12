<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\DateRangeFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class DateRangeFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2024-06-15'));

        $this->models = collect([
            TestModel::factory()->create([
                'name' => 'January Model',
                'created_at' => Carbon::parse('2024-01-15'),
            ]),
            TestModel::factory()->create([
                'name' => 'March Model',
                'created_at' => Carbon::parse('2024-03-15'),
            ]),
            TestModel::factory()->create([
                'name' => 'June Model',
                'created_at' => Carbon::parse('2024-06-15'),
            ]),
            TestModel::factory()->create([
                'name' => 'September Model',
                'created_at' => Carbon::parse('2024-09-15'),
            ]),
            TestModel::factory()->create([
                'name' => 'December Model',
                'created_at' => Carbon::parse('2024-12-15'),
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @test */
    public function it_can_filter_by_date_range(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'created_at' => [
                    'from' => '2024-02-01',
                    'to' => '2024-07-01',
                ],
            ])
            ->allowedFilters(DateRangeFilter::make('created_at'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[1]->id, $this->models[2]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_can_filter_with_only_from_date(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'created_at' => [
                    'from' => '2024-06-01',
                ],
            ])
            ->allowedFilters(DateRangeFilter::make('created_at'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[2]->id, $this->models[3]->id, $this->models[4]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_can_filter_with_only_to_date(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'created_at' => [
                    'to' => '2024-04-01',
                ],
            ])
            ->allowedFilters(DateRangeFilter::make('created_at'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[0]->id, $this->models[1]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_can_use_custom_from_and_to_keys(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'created_at' => [
                    'start' => '2024-02-01',
                    'end' => '2024-07-01',
                ],
            ])
            ->allowedFilters(
                DateRangeFilter::make('created_at')
                    ->fromKey('start')
                    ->toKey('end')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[1]->id, $this->models[2]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_returns_no_results_for_non_overlapping_range(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'created_at' => [
                    'from' => '2025-01-01',
                    'to' => '2025-12-31',
                ],
            ])
            ->allowedFilters(DateRangeFilter::make('created_at'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_allows_empty_range(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'created_at' => [
                    'from' => '',
                    'to' => '',
                ],
            ])
            ->allowedFilters(DateRangeFilter::make('created_at'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $result);
    }

    /** @test */
    public function it_can_filter_with_datetime_format(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'created_at' => [
                    'from' => '2024-03-15T00:00:00',
                    'to' => '2024-06-15T23:59:59',
                ],
            ])
            ->allowedFilters(DateRangeFilter::make('created_at'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'date' => [
                    'from' => '2024-02-01',
                    'to' => '2024-04-01',
                ],
            ])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[1]->id, $result->first()->id);
    }

    /** @test */
    public function it_includes_boundary_dates(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'created_at' => [
                    'from' => '2024-01-15',
                    'to' => '2024-01-15',
                ],
            ])
            ->allowedFilters(DateRangeFilter::make('created_at'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_handles_non_array_value_gracefully(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'created_at' => 'not-an-array',
            ])
            ->allowedFilters(DateRangeFilter::make('created_at'))
            ->build()
            ->execute()
            ->models();

        // Should return all results when value is not an array
        $this->assertCount(5, $result);
    }
}
