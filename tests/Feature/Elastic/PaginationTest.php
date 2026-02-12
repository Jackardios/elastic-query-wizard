<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group pagination
 * @group elastic-pagination
 */
class PaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestModel::factory()->count(25)->create();
    }

    /** @test */
    public function it_can_paginate_results(): void
    {
        $paginator = ElasticQueryWizard::for(TestModel::class)
            ->build()
            ->paginate(10);

        $this->assertEquals(10, $paginator->perPage());
        $this->assertEquals(25, $paginator->total());
        $this->assertEquals(3, $paginator->lastPage());
    }

    /** @test */
    public function it_can_get_specific_page(): void
    {
        $paginator = ElasticQueryWizard::for(TestModel::class)
            ->build()
            ->paginate(10, 'page', 2);

        $this->assertEquals(2, $paginator->currentPage());
        $this->assertEquals(10, $paginator->count());
    }

    /** @test */
    public function it_can_get_last_page_with_remaining_items(): void
    {
        $paginator = ElasticQueryWizard::for(TestModel::class)
            ->build()
            ->paginate(10, 'page', 3);

        $this->assertEquals(3, $paginator->currentPage());
        $this->assertEquals(5, $paginator->count()); // 25 - 10 - 10 = 5
    }

    /** @test */
    public function it_can_paginate_with_filters(): void
    {
        TestModel::factory()->count(15)->create(['category' => 'paginate-test-category']);

        $paginator = $this
            ->createElasticWizardWithFilters(['category' => 'paginate-test-category'])
            ->allowedFilters(TermFilter::make('category'))
            ->build()
            ->paginate(5);

        $this->assertEquals(15, $paginator->total());
        $this->assertEquals(5, $paginator->perPage());
        $this->assertEquals(3, $paginator->lastPage());
    }

    /** @test */
    public function it_can_paginate_with_sorts(): void
    {
        $paginator = $this
            ->createElasticWizardWithSorts('name')
            ->allowedSorts(FieldSort::make('name.keyword', 'name'))
            ->build()
            ->paginate(10);

        $models = $paginator->withModels()->models();

        $this->assertEquals(10, $paginator->count());
        // Models should be sorted
        $this->assertCount(10, $models);
    }

    /** @test */
    public function it_can_access_models_from_paginator(): void
    {
        $paginator = ElasticQueryWizard::for(TestModel::class)
            ->build()
            ->paginate(10);

        $models = $paginator->withModels()->models();

        $this->assertCount(10, $models);
        $this->assertInstanceOf(TestModel::class, $models->first());
    }

    /** @test */
    public function it_can_use_custom_page_name(): void
    {
        $paginator = ElasticQueryWizard::for(TestModel::class)
            ->build()
            ->paginate(10, 'p');

        $this->assertEquals('p', $paginator->getPageName());
    }

    /** @test */
    public function it_returns_empty_page_for_beyond_last_page(): void
    {
        $paginator = ElasticQueryWizard::for(TestModel::class)
            ->build()
            ->paginate(10, 'page', 100);

        $this->assertEquals(0, $paginator->count());
    }

    /** @test */
    public function it_can_count_without_pagination(): void
    {
        $count = ElasticQueryWizard::for(TestModel::class)
            ->build()
            ->count();

        $this->assertEquals(25, $count);
    }

    /** @test */
    public function it_can_count_with_filters(): void
    {
        TestModel::factory()->count(10)->create(['category' => 'count-test-category']);

        $count = $this
            ->createElasticWizardWithFilters(['category' => 'count-test-category'])
            ->allowedFilters(TermFilter::make('category'))
            ->build()
            ->count();

        $this->assertEquals(10, $count);
    }
}
