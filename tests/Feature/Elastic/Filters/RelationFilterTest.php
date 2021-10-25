<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Http\Request;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\RelatedModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\ExactFilter;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class RelationFilterTest extends TestCase
{
    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();

        $this->models->each(function (TestModel $model, $index) {
            $model
                ->relatedModels()->create(['name' => $model->name])
                ->nestedRelatedModels()->create(['name' => 'test'.$index]);
        });
    }

    /** @test */
    public function it_can_filter_related_model_property(): void
    {
        $expectedModel = $this->models->random();
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'relatedModels.name' => $expectedModel->name,
            ])
            ->setAllowedFilters(new ExactFilter('relatedModels.name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->id, $modelsResult->first()->id);
    }

    /** @test */
    public function it_can_filter_results_based_on_the_exact_existence_of_a_property_in_an_array(): void
    {
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'relatedModels.nestedRelatedModels.name' => 'test0,test1',
            ])
            ->setAllowedFilters(new ExactFilter('relatedModels.nestedRelatedModels.name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing(
            [$this->models[0]->id, $this->models[1]->id],
            $modelsResult->pluck('id')->all()
        );
    }

    /** @test */
    public function it_can_filter_models_and_return_an_empty_collection(): void
    {
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'relatedModels.name' => 'None existing first name',
            ])
            ->setAllowedFilters(new ExactFilter('relatedModels.name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
    }

    /** @test */
    public function it_can_filter_related_nested_model_property(): void
    {
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'relatedModels.nestedRelatedModels.name' => 'test1',
            ])
            ->setAllowedFilters(new ExactFilter('relatedModels.nestedRelatedModels.name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertTrue(
            $modelsResult
                ->first()
                ->relatedModels
                ->contains(function(RelatedModel $relatedModel) {
                    return $relatedModel
                        ->nestedRelatedModels
                        ->contains('name', 'test1');
                })
        );
    }

    /** @test */
    public function it_can_filter_related_model_and_related_nested_model_property(): void
    {
        $expectedModel = $this->models->first();
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'relatedModels.name' => $expectedModel->name,
                'relatedModels.nestedRelatedModels.name' => 'test0',
            ])
            ->setAllowedFilters(
                new ExactFilter('relatedModels.name'),
                new ExactFilter('relatedModels.nestedRelatedModels.name')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->name, $modelsResult->first()->name);
    }

    /** @test */
    public function it_can_filter_results_based_on_the_existence_of_a_property_in_an_array(): void
    {
        $testModels = TestModel::whereIn('id', [1, 2])->get();

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'relatedModels.id' => $testModels->map(function ($model) {
                    return $model->relatedModels->pluck('id');
                })->flatten()->all(),
            ])
            ->setAllowedFilters(new ExactFilter('relatedModels.id'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing([1, 2], $modelsResult->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_and_reject_results_by_exact_property(): void
    {
        factory(TestModel::class)->create(['name' => 'John Testing Doe']);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'relatedModels.nestedRelatedModels.name' => ' test ',
            ])
            ->setAllowedFilters(new ExactFilter('relatedModels.nestedRelatedModels.name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return ElasticQueryWizard::for(TestModel::class, $request);
    }
}
