<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Handlers\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Tests\App\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class MatchFilterTest extends TestCase
{
    /** @var Collection */
    protected $models;

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
            ->createQueryFromFilterRequest([
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
        $this
            ->createQueryFromFilterRequest([
                'name' => ''
            ])
            ->setAllowedFilters(new MatchFilter('name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $this->models);
    }

    /** @test */
    public function it_can_filter_results_by_array_of_values(): void
    {
        factory(TestModel::class)->create(['name' => 'UniqueJohn Doe']);
        $model1 = factory(TestModel::class)->create(['name' => 'Some new TESTING Name']);
        $model2 = factory(TestModel::class)->create(['name' => 'UniqueJohn Deer']);

        $results = $this
            ->createQueryFromFilterRequest([
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

        $models = $this
            ->createQueryFromFilterRequest([])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertEquals(1, $models->count());
        $this->assertEquals($model1->id, $models->first()->id);
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $model1 = factory(TestModel::class)->create(['name' => 'UniqueJohn UniqueDoe']);
        $model2 = factory(TestModel::class)->create(['name' => 'Some Deer']);

        $filter = (new MatchFilter('name'))->default('Deer');

        $models = $this
            ->createQueryFromFilterRequest([
                'name' => 'UniqueDoe',
            ])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertEquals(1, $models->count());
        $this->assertEquals($model1->id, $models->first()->id);
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return ElasticQueryWizard::for(TestModel::class, $request);
    }
}
