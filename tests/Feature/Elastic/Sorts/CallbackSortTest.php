<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Sorts\CallbackSort;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group sort
 * @group elastic-sort
 */
class CallbackSortTest extends TestCase
{
    use AssertsCollectionSorting;

    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_should_sort_by_closure(): void
    {
        $sortedModels = $this
            ->createElasticWizardWithSorts('-callbackSort')
            ->setAllowedSorts(
                new CallbackSort('callbackSort', function (ElasticQueryWizard $queryWizard, SearchRequestBuilder $queryBuilder, string $direction) {
                    $queryBuilder->sort('category', $direction);
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
            ->setAllowedSorts(new CallbackSort('callbackSort', [$this, 'sortCallback']))
            ->build()
            ->execute()
            ->models();

        $this->assertSortedAscending($sortedModels, 'category');
    }

    public function sortCallback(ElasticQueryWizard $queryWizard, SearchRequestBuilder $queryBuilder, string $direction): void
    {
        $queryBuilder->sort('category', $direction);
    }
}
