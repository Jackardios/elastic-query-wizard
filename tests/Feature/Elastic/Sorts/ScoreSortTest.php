<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Sorts\ScoreSort;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group sort
 * @group elastic-sort
 */
class ScoreSortTest extends TestCase
{
    use AssertsCollectionSorting;

    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'John Developer Expert', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'Jane Developer', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Bob Manager', 'category' => 'clothing']),
        ]);
    }

    /** @test */
    public function it_can_sort_by_score_descending(): void
    {
        $result = $this
            ->createElasticWizardFromQuery([
                'filter' => ['name' => 'Developer'],
                'sort' => '-score',
            ])
            ->allowedFilters(MatchFilter::make('name'))
            ->allowedSorts(ScoreSort::make('score'))
            ->build()
            ->execute()
            ->models();

        // Higher score (more matches) should come first
        $this->assertCount(2, $result);
        $this->assertContains($this->models[0]->id, $result->pluck('id')->all());
        $this->assertContains($this->models[1]->id, $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_by_score_ascending(): void
    {
        $result = $this
            ->createElasticWizardFromQuery([
                'filter' => ['name' => 'Developer'],
                'sort' => 'score',
            ])
            ->allowedFilters(MatchFilter::make('name'))
            ->allowedSorts(ScoreSort::make('score'))
            ->build()
            ->execute()
            ->models();

        // Lower score should come first
        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_returns_results_with_default_scoring(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('-score')
            ->allowedSorts(ScoreSort::make('score'))
            ->build()
            ->execute()
            ->models();

        // All documents should be returned
        $this->assertCount(3, $result);
    }

    /** @test */
    public function it_works_with_alias(): void
    {
        $result = $this
            ->createElasticWizardFromQuery([
                'filter' => ['name' => 'Developer'],
                'sort' => '-relevance',
            ])
            ->allowedFilters(MatchFilter::make('name'))
            ->allowedSorts(ScoreSort::make('relevance'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
    }
}
