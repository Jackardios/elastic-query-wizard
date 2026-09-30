<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\MultiMatchFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('elastic')]
#[Group('filter')]
#[Group('elastic-filter')]
class MultiMatchFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(5)->create();
    }

    #[Test]
    public function it_can_filter_across_multiple_fields(): void
    {
        $expectedModel = TestModel::factory()->create([
            'name' => 'Some new TESTING Name',
            'category' => 'random-category',
        ]);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'search' => 'new testing',
            ])
            ->allowedFilters(MultiMatchFilter::make('search', ['name', 'category']))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->id, $modelsResult->first()->id);
    }

    #[Test]
    public function it_allows_empty_filter_value(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'search' => '',
            ])
            ->allowedFilters(MultiMatchFilter::make('search', ['name', 'category']))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    #[Test]
    public function it_can_filter_with_parameters(): void
    {
        $expectedModel = TestModel::factory()->create([
            'name' => 'UniqueSearchPhrase',
            'category' => 'some-category',
        ]);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'search' => 'UniqueSearchPhrase',
            ])
            ->allowedFilters(
                MultiMatchFilter::make('search', ['name', 'category'])
                    ->withParameters(['type' => 'best_fields'])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->id, $modelsResult->first()->id);
    }

    #[Test]
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        $model1 = TestModel::factory()->create(['name' => 'UniqueJohn Doe']);
        $model2 = TestModel::factory()->create(['name' => 'Some Deer']);

        $filter = MultiMatchFilter::make('search', ['name', 'category'])->default('UniqueJohn');

        $modelsResult = $this
            ->createElasticWizardWithFilters([])
            ->allowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($model1->id, $modelsResult->first()->id);
    }
}
