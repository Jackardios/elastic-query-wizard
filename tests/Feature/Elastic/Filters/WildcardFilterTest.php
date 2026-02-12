<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\WildcardFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class WildcardFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'John Doe', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'Jane Doe', 'category' => 'electronics-premium']),
            TestModel::factory()->create(['name' => 'Bob Smith', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Alice Johnson', 'category' => 'clothing']),
        ]);
    }

    /** @test */
    public function it_can_filter_with_wildcard_asterisk(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'electr*'])
            ->allowedFilters(WildcardFilter::make('category'))
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
    public function it_can_filter_with_wildcard_question_mark(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'book?'])
            ->allowedFilters(WildcardFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[2]->id, $result->first()->id);
    }

    /** @test */
    public function it_can_filter_with_suffix_wildcard(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => '*premium'])
            ->allowedFilters(WildcardFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[1]->id, $result->first()->id);
    }

    /** @test */
    public function it_returns_no_results_for_non_matching_pattern(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'xyz*'])
            ->allowedFilters(WildcardFilter::make('category'))
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
            ->allowedFilters(WildcardFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['cat' => 'book*'])
            ->allowedFilters(WildcardFilter::make('category', 'cat'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
    }
}
