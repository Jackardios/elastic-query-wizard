<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Jackardios\ElasticQueryWizard\ElasticAggregation;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group aggregation
 * @group elastic-aggregation
 */
class AggregationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestModel::factory()->count(5)->create(['category' => 'electronics']);
        TestModel::factory()->count(3)->create(['category' => 'books']);
        TestModel::factory()->count(2)->create(['category' => 'clothing']);
    }

    /** @test */
    public function it_can_add_terms_aggregation(): void
    {
        $result = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('categories', ElasticAggregation::terms('category'));
            })
            ->build()
            ->execute();

        $this->assertCount(10, $result->models());

        $aggregations = $result->aggregations();
        $this->assertNotEmpty($aggregations);
        $this->assertArrayHasKey('categories', $aggregations);
    }

    /** @test */
    public function it_can_get_aggregation_buckets(): void
    {
        $result = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('categories', ElasticAggregation::terms('category'));
            })
            ->build()
            ->execute();

        $aggregations = $result->aggregations();
        $buckets = $aggregations['categories']['buckets'] ?? [];

        $this->assertNotEmpty($buckets);

        // Should have 3 buckets (electronics, books, clothing)
        $this->assertCount(3, $buckets);
    }

    /** @test */
    public function it_can_add_cardinality_aggregation(): void
    {
        $result = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('unique_categories', ElasticAggregation::cardinality('category'));
            })
            ->build()
            ->execute();

        $aggregations = $result->aggregations();
        $this->assertArrayHasKey('unique_categories', $aggregations);
        $this->assertEquals(3, $aggregations['unique_categories']['value']);
    }

    /** @test */
    public function it_can_combine_aggregation_with_filters(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'electronics'])
            ->allowedFilters(TermFilter::make('category'))
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('categories', ElasticAggregation::terms('category'));
            })
            ->build()
            ->execute();

        $this->assertCount(5, $result->models());

        $aggregations = $result->aggregations();
        $buckets = $aggregations['categories']['buckets'] ?? [];

        // Only electronics should be in aggregation after filtering
        $this->assertCount(1, $buckets);
        $this->assertEquals('electronics', $buckets[0]['key']);
    }

    /** @test */
    public function it_can_add_multiple_aggregations(): void
    {
        $result = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('categories', ElasticAggregation::terms('category'));
                $builder->aggregate('unique_count', ElasticAggregation::cardinality('category'));
            })
            ->build()
            ->execute();

        $aggregations = $result->aggregations();

        $this->assertArrayHasKey('categories', $aggregations);
        $this->assertArrayHasKey('unique_count', $aggregations);
    }

    /** @test */
    public function it_can_use_date_histogram_aggregation(): void
    {
        $result = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('by_date', ElasticAggregation::dateHistogram('created_at', 'day'));
            })
            ->build()
            ->execute();

        $aggregations = $result->aggregations();
        $this->assertArrayHasKey('by_date', $aggregations);
    }

    /** @test */
    public function it_can_limit_terms_aggregation_size(): void
    {
        TestModel::factory()->count(5)->create(['category' => 'category-a']);
        TestModel::factory()->count(4)->create(['category' => 'category-b']);
        TestModel::factory()->count(3)->create(['category' => 'category-c']);
        TestModel::factory()->count(2)->create(['category' => 'category-d']);
        TestModel::factory()->count(1)->create(['category' => 'category-e']);

        $result = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('top_categories', ElasticAggregation::terms('category')->size(3));
            })
            ->build()
            ->execute();

        $aggregations = $result->aggregations();
        $buckets = $aggregations['top_categories']['buckets'] ?? [];

        $this->assertLessThanOrEqual(3, count($buckets));
    }

    /** @test */
    public function it_returns_empty_aggregations_when_none_configured(): void
    {
        $result = ElasticQueryWizard::for(TestModel::class)
            ->build()
            ->execute();

        $aggregations = $result->aggregations();
        $this->assertEmpty($aggregations);
    }

    /** @test */
    public function it_can_access_raw_result_with_aggregations(): void
    {
        $raw = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('categories', ElasticAggregation::terms('category'));
            })
            ->build()
            ->raw();

        $this->assertIsArray($raw);
        $this->assertArrayHasKey('aggregations', $raw);
        $this->assertArrayHasKey('categories', $raw['aggregations']);
    }

    /** @test */
    public function it_can_paginate_with_aggregations(): void
    {
        $paginator = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('categories', ElasticAggregation::terms('category'));
            })
            ->build()
            ->paginate(5);

        $this->assertEquals(10, $paginator->total());
        $this->assertEquals(5, $paginator->perPage());
    }

    /** @test */
    public function it_can_use_stats_aggregation(): void
    {
        $result = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(function ($builder) {
                $builder->aggregate('id_stats', ElasticAggregation::stats('id'));
            })
            ->build()
            ->execute();

        $aggregations = $result->aggregations();
        $this->assertArrayHasKey('id_stats', $aggregations);
        $this->assertArrayHasKey('count', $aggregations['id_stats']);
        $this->assertArrayHasKey('min', $aggregations['id_stats']);
        $this->assertArrayHasKey('max', $aggregations['id_stats']);
        $this->assertArrayHasKey('avg', $aggregations['id_stats']);
        $this->assertArrayHasKey('sum', $aggregations['id_stats']);
    }
}
