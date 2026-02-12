<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\MatchPhrasePrefixFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class MatchPhrasePrefixFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'John Smith Developer', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'John Smith Designer', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Jane Doe Developer', 'category' => 'clothing']),
            TestModel::factory()->create(['name' => 'Bob Johnson Manager', 'category' => 'electronics']),
        ]);
    }

    /** @test */
    public function it_can_filter_by_phrase_prefix(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'John Smith Dev'])
            ->allowedFilters(MatchPhrasePrefixFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_can_match_multiple_documents_with_same_prefix(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'John Smith'])
            ->allowedFilters(MatchPhrasePrefixFilter::make('name'))
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
    public function it_can_filter_by_single_word_prefix(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Develop'])
            ->allowedFilters(MatchPhrasePrefixFilter::make('name'))
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
    public function it_returns_no_results_for_non_matching_prefix(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'nonexistent'])
            ->allowedFilters(MatchPhrasePrefixFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => ''])
            ->allowedFilters(MatchPhrasePrefixFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
    }
}
