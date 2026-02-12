<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\QueryStringFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class QueryStringFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'John Smith Developer', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'Jane Doe Designer', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Bob Johnson Developer', 'category' => 'clothing']),
            TestModel::factory()->create(['name' => 'Alice Williams Manager', 'category' => 'electronics']),
        ]);
    }

    /** @test */
    public function it_can_filter_with_simple_query(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => 'Developer'])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[0]->id, $this->models[2]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_can_filter_with_and_operator(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => 'John AND Developer'])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_can_filter_with_or_operator(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => 'Designer OR Manager'])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[1]->id, $this->models[3]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_returns_no_results_for_non_matching_query(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => 'nonexistent'])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => ''])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
    }
}
