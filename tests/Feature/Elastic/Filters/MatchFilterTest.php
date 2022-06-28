<?php

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

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_can_filter_results(): void
    {
        $expectedModel = factory(TestModel::class)->create(['name' => 'Some new TESTING Name']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'new testing',
            ])
            ->setAllowedFilters(new MatchFilter('name'))
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
                'name' => ''
            ])
            ->setAllowedFilters(new MatchFilter('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    /** @test */
    public function it_can_filter_results_by_array_of_values(): void
    {
        factory(TestModel::class)->create(['name' => 'UniqueJohn Doe']);
        $model1 = factory(TestModel::class)->create(['name' => 'Some new TESTING Name']);
        $model2 = factory(TestModel::class)->create(['name' => 'UniqueJohn Deer']);

        $results = $this
            ->createElasticWizardWithFilters([
                'name' => "Testing,Deer",
            ])
            ->setAllowedFilters(new MatchFilter('name'))
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
        $model1 = factory(TestModel::class)->create(['name' => 'UniqueJohn Doe']);
        $model2 = factory(TestModel::class)->create(['name' => 'Some Deer']);

        $filter = (new MatchFilter('name'))->default('UniqueJohn');

        $modelsResult = $this
            ->createElasticWizardWithFilters([])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($model1->id, $modelsResult->first()->id);
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $model1 = factory(TestModel::class)->create(['name' => 'UniqueJohn UniqueDoe']);
        $model2 = factory(TestModel::class)->create(['name' => 'Some Deer']);

        $filter = (new MatchFilter('name'))->default('Deer');

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'UniqueDoe',
            ])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($model1->id, $modelsResult->first()->id);
    }
}
