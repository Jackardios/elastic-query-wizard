<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Illuminate\Http\Request;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Handlers\ElasticQueryHandler;
use Jackardios\ElasticQueryWizard\Handlers\Sorts\CallbackSort;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\ElasticQueryWizard\Tests\App\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;

/**
 * @group elastic
 * @group sort
 * @group elastic-sort
 */
class CallbackSortTest extends TestCase
{
    use AssertsCollectionSorting;

    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_should_sort_by_closure(): void
    {
        $sortedModels = $this
            ->createWizardFromSortRequest('-callbackSort')
            ->setAllowedSorts(
                new CallbackSort('callbackSort', function (ElasticQueryHandler $queryHandler, SearchRequestBuilder $queryBuilder, string $direction) {
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
            ->createWizardFromSortRequest('callbackSort')
            ->setAllowedSorts(new CallbackSort('callbackSort', [$this, 'sortCallback']))
            ->build()
            ->execute()
            ->models();

        $this->assertSortedAscending($sortedModels, 'category');
    }

    public function sortCallback(ElasticQueryHandler $queryHandler, SearchRequestBuilder $queryBuilder, string $direction): void
    {
        $queryBuilder->sort('category', $direction);
    }

    protected function createWizardFromSortRequest(string $sort): ElasticQueryWizard
    {
        $request = new Request([
            'sort' => $sort,
        ]);

        return ElasticQueryWizard::for(TestModel::class, $request);
    }
}
