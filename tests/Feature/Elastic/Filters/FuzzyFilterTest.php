<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\FuzzyFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class FuzzyFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'John Doe', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'Jane Smith', 'category' => 'elektonics']),
            TestModel::factory()->create(['name' => 'Bob Johnson', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Alice Williams', 'category' => 'clothing']),
        ]);
    }

    /** @test */
    public function it_can_filter_with_fuzzy_matching(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'electroncs'])
            ->allowedFilters(FuzzyFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        // Should match 'electronics' with 1 edit distance
        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_can_match_exact_term(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'electronics'])
            ->allowedFilters(FuzzyFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_returns_no_results_for_completely_different_term(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'xyz'])
            ->allowedFilters(FuzzyFilter::make('category'))
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
            ->allowedFilters(FuzzyFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['cat' => 'electronics'])
            ->allowedFilters(FuzzyFilter::make('category', 'cat'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_handles_minor_typos(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'boks'])
            ->allowedFilters(FuzzyFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        // Should match 'books' with 1 edit distance
        $this->assertCount(1, $result);
        $this->assertEquals($this->models[2]->id, $result->first()->id);
    }
}
