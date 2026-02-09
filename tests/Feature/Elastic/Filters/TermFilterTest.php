<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class TermFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(5)->create();
    }

    /** @test */
    public function it_can_filter_and_match_results(): void
    {
        $expectedModel = TestModel::factory()->create(['category' => 'some-testing-category']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => $expectedModel->category,
            ])
            ->allowedFilters(TermFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->id, $modelsResult->first()->id);
    }

    /** @test */
    public function it_can_filter_and_reject_results(): void
    {
        TestModel::factory()->create(['category' => 'Some Testing Category']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => ' Testing ',
            ])
            ->allowedFilters(TermFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => ''
            ])
            ->allowedFilters(TermFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_array_of_values(): void
    {
        $expectedModels = collect([
            TestModel::factory()->create(['category' => 'some-testing-category']),
            TestModel::factory()->create(['category' => 'another-testing-category'])
        ]);

        $results = $this
            ->createElasticWizardWithFilters([
                'category' => "{$expectedModels[0]->category},{$expectedModels[1]->category}",
            ])
            ->allowedFilters(TermFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedModels->pluck('id')->all(), $results->pluck('id')->all());
    }

    /** @test */
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        $model1 = TestModel::factory()->create(['category' => 'UniqueJohn Doe']);
        $model2 = TestModel::factory()->create(['category' => 'UniqueJohn Deer']);

        $filter = (TermFilter::make('category'))->default('UniqueJohn Doe');

        $models = $this
            ->createElasticWizardWithFilters([])
            ->allowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $models);
        $this->assertEquals($models[0]->id, $model1->id);
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $model1 = TestModel::factory()->create(['category' => 'UniqueJohn UniqueDoe']);
        $model2 = TestModel::factory()->create(['category' => 'UniqueJohn Deer']);

        $filter = (TermFilter::make('category'))->default('UniqueJohn Deer');

        $models = $this
            ->createElasticWizardWithFilters([
                'category' => 'UniqueJohn UniqueDoe',
            ])
            ->allowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $models);
        $this->assertEquals($models[0]->id, $model1->id);
    }
}
