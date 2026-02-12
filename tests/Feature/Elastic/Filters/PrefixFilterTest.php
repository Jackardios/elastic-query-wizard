<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\PrefixFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class PrefixFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'John Doe', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'Jane Smith', 'category' => 'electrical']),
            TestModel::factory()->create(['name' => 'Bob Johnson', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Alice Williams', 'category' => 'clothing']),
        ]);
    }

    /** @test */
    public function it_can_filter_by_prefix(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'elec'])
            ->allowedFilters(PrefixFilter::make('category'))
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
    public function it_can_filter_by_full_term_as_prefix(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'electronics'])
            ->allowedFilters(PrefixFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_returns_no_results_for_non_matching_prefix(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'xyz'])
            ->allowedFilters(PrefixFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_is_case_sensitive_on_keyword_fields(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'Elec'])
            ->allowedFilters(PrefixFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        // Keyword fields are case sensitive, so 'Elec' won't match 'elec*'
        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => ''])
            ->allowedFilters(PrefixFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['cat' => 'book'])
            ->allowedFilters(PrefixFilter::make('category', 'cat'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
    }
}
