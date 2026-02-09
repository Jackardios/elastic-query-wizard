<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Sorts\CallbackSort;

/**
 * @group elastic
 * @group sort
 * @group elastic-sort
 */
class CallbackSortTest extends TestCase
{
    use AssertsCollectionSorting;

    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(5)->create();
    }

    /** @test */
    public function it_should_sort_by_closure(): void
    {
        $sortedModels = $this
            ->createElasticWizardWithSorts('-callbackSort')
            ->allowedSorts(
                CallbackSort::make('callbackSort', function (SearchBuilder $builder, string $direction, string $property) {
                    $builder->sort('category', $direction);
                })
            )
            ->build()
            ->execute()
            ->models();

        $this->assertSortedDescending($sortedModels, 'category');
    }

    /** @test */
    public function it_should_sort_by_array_callback(): void
    {
        $sortedModels = $this
            ->createElasticWizardWithSorts('callbackSort')
            ->allowedSorts(CallbackSort::make('callbackSort', [$this, 'sortCallback']))
            ->build()
            ->execute()
            ->models();

        $this->assertSortedAscending($sortedModels, 'category');
    }

    public function sortCallback(SearchBuilder $builder, string $direction, string $property): void
    {
        $builder->sort('category', $direction);
    }
}
