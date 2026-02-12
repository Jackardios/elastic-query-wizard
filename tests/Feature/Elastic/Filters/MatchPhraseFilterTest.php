<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\MatchPhraseFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class MatchPhraseFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'John Smith Developer', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'John Developer Smith', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Jane Doe Designer', 'category' => 'clothing']),
            TestModel::factory()->create(['name' => 'Bob Johnson Manager', 'category' => 'electronics']),
        ]);
    }

    /** @test */
    public function it_can_filter_by_exact_phrase(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'John Smith'])
            ->allowedFilters(MatchPhraseFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_does_not_match_words_in_different_order(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Smith John'])
            ->allowedFilters(MatchPhraseFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        // Phrase must be in exact order
        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_can_match_single_word(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Designer'])
            ->allowedFilters(MatchPhraseFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[2]->id, $result->first()->id);
    }

    /** @test */
    public function it_returns_no_results_for_non_matching_phrase(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'nonexistent phrase'])
            ->allowedFilters(MatchPhraseFilter::make('name'))
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
            ->allowedFilters(MatchPhraseFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
    }
}
