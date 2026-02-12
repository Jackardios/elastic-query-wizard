<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\RegexpFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class RegexpFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'John Doe', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'Jane Smith', 'category' => 'electrical']),
            TestModel::factory()->create(['name' => 'Bob Johnson', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Alice Williams', 'category' => 'tools-123']),
        ]);
    }

    /** @test */
    public function it_can_filter_by_regexp_pattern(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'elec.*'])
            ->allowedFilters(RegexpFilter::make('category'))
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
    public function it_can_filter_with_character_class(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'tools-[0-9]+'])
            ->allowedFilters(RegexpFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[3]->id, $result->first()->id);
    }

    /** @test */
    public function it_can_filter_with_alternation(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'books|clothing'])
            ->allowedFilters(RegexpFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_returns_no_results_for_non_matching_pattern(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'xyz.*'])
            ->allowedFilters(RegexpFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => ''])
            ->allowedFilters(RegexpFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
    }
}
