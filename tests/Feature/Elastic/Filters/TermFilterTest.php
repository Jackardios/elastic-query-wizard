<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Handlers\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class TermFilterTest extends TestCase
{
    /** @var Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_can_filter_and_match_results(): void
    {
        $expectedModel = factory(TestModel::class)->create(['category' => 'some-testing-category']);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'category' => $expectedModel->category,
            ])
            ->setAllowedFilters(new TermFilter('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->id, $modelsResult->first()->id);
    }

    /** @test */
    public function it_can_filter_and_reject_results(): void
    {
        factory(TestModel::class)->create(['category' => 'Some Testing Category']);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'category' => ' Testing ',
            ])
            ->setAllowedFilters(new TermFilter('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $this
            ->createQueryFromFilterRequest([
                'category' => ''
            ])
            ->setAllowedFilters(new TermFilter('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $this->models);
    }

    /** @test */
    public function it_can_filter_results_by_array_of_values(): void
    {
        $expectedModels = collect([
            factory(TestModel::class)->create(['category' => 'some-testing-category']),
            factory(TestModel::class)->create(['category' => 'another-testing-category'])
        ]);

        $results = $this
            ->createQueryFromFilterRequest([
                'category' => "{$expectedModels[0]->category},{$expectedModels[1]->category}",
            ])
            ->setAllowedFilters(new TermFilter('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedModels->pluck('id')->all(), $results->pluck('id')->all());
    }

    /** @test */
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        $model1 = factory(TestModel::class)->create(['category' => 'UniqueJohn Doe']);
        $model2 = factory(TestModel::class)->create(['category' => 'UniqueJohn Deer']);

        $filter = (new TermFilter('category'))->default('UniqueJohn Doe');

        $models = $this
            ->createQueryFromFilterRequest([])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertEquals(1, $models->count());
        $this->assertEquals($models[0]->id, $model1->id);
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $model1 = factory(TestModel::class)->create(['category' => 'UniqueJohn UniqueDoe']);
        $model2 = factory(TestModel::class)->create(['category' => 'UniqueJohn Deer']);

        $filter = (new TermFilter('category'))->default('UniqueJohn Deer');

        $models = $this
            ->createQueryFromFilterRequest([
                'category' => 'UniqueJohn UniqueDoe',
            ])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertEquals(1, $models->count());
        $this->assertEquals($models[0]->id, $model1->id);
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return ElasticQueryWizard::for(TestModel::class, $request);
    }
}
