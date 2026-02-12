<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class MatchFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(5)->create();
    }

    /** @test */
    public function it_can_filter_results(): void
    {
        $expectedModel = TestModel::factory()->create(['name' => 'Some new TESTING Name']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'new testing',
            ])
            ->allowedFilters(MatchFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->id, $modelsResult->first()->id);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => '',
            ])
            ->allowedFilters(MatchFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_array_of_values(): void
    {
        TestModel::factory()->create(['name' => 'UniqueJohn Doe']);
        $model1 = TestModel::factory()->create(['name' => 'Some new TESTING Name']);
        $model2 = TestModel::factory()->create(['name' => 'UniqueJohn Deer']);

        $results = $this
            ->createElasticWizardWithFilters([
                'name' => "Testing,Deer",
            ])
            ->allowedFilters(MatchFilter::make('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(
            [$model1->id, $model2->id],
            $results->pluck('id')->all()
        );
    }

    /** @test */
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        $model1 = TestModel::factory()->create(['name' => 'UniqueJohn Doe']);
        $model2 = TestModel::factory()->create(['name' => 'Some Deer']);

        $filter = (MatchFilter::make('name'))->default('UniqueJohn');

        $modelsResult = $this
            ->createElasticWizardWithFilters([])
            ->allowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($model1->id, $modelsResult->first()->id);
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $model1 = TestModel::factory()->create(['name' => 'UniqueJohn UniqueDoe']);
        $model2 = TestModel::factory()->create(['name' => 'Some Deer']);

        $filter = (MatchFilter::make('name'))->default('Deer');

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'UniqueDoe',
            ])
            ->allowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($model1->id, $modelsResult->first()->id);
    }
}
