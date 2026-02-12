<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\NestedFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\NestedModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class NestedFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            NestedModel::factory()->create([
                'title' => 'Product A',
                'variants' => [
                    ['sku' => 'SKU-001', 'price' => 100.00, 'active' => true],
                    ['sku' => 'SKU-002', 'price' => 150.00, 'active' => false],
                ],
                'comments' => [
                    ['author' => 'john', 'text' => 'Great product', 'rating' => 5],
                    ['author' => 'jane', 'text' => 'Good value', 'rating' => 4],
                ],
            ]),
            NestedModel::factory()->create([
                'title' => 'Product B',
                'variants' => [
                    ['sku' => 'SKU-003', 'price' => 200.00, 'active' => true],
                ],
                'comments' => [
                    ['author' => 'mike', 'text' => 'Nice item', 'rating' => 3],
                ],
            ]),
            NestedModel::factory()->create([
                'title' => 'Product C',
                'variants' => [
                    ['sku' => 'SKU-004', 'price' => 50.00, 'active' => false],
                ],
                'comments' => [
                    ['author' => 'john', 'text' => 'Could be better', 'rating' => 2],
                ],
            ]),
        ]);
    }

    /** @test */
    public function it_can_filter_by_nested_field_single_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['author' => 'john'], NestedModel::class)
            ->allowedFilters(NestedFilter::make('comments', 'author'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[0]->id, $this->models[2]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_can_filter_by_nested_field_multiple_values(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['author' => 'john,mike'], NestedModel::class)
            ->allowedFilters(NestedFilter::make('comments', 'author'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
    }

    /** @test */
    public function it_returns_no_results_for_non_matching_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['author' => 'nonexistent'], NestedModel::class)
            ->allowedFilters(NestedFilter::make('comments', 'author'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_can_filter_by_nested_keyword_field(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['sku' => 'SKU-001'], NestedModel::class)
            ->allowedFilters(NestedFilter::make('variants', 'sku'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_can_filter_by_nested_boolean_field(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['active' => 'true'], NestedModel::class)
            ->allowedFilters(
                NestedFilter::make('variants', 'active')
                    ->innerQuery(fn($value) => Query::term('variants.active', $value === 'true'))
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[0]->id, $this->models[1]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_can_filter_with_custom_range_inner_query(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['min_rating' => 4], NestedModel::class)
            ->allowedFilters(
                NestedFilter::make('comments', 'min_rating')
                    ->innerQuery(fn($value) => Query::range('comments.rating')->gte((int) $value))
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_can_filter_with_score_mode(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['author' => 'john'], NestedModel::class)
            ->allowedFilters(
                NestedFilter::make('comments', 'author')->scoreMode('avg')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['author' => ''], NestedModel::class)
            ->allowedFilters(NestedFilter::make('comments', 'author'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['comment_author' => 'mike'], NestedModel::class)
            ->allowedFilters(NestedFilter::make('comments', 'author', 'comment_author'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[1]->id, $result->first()->id);
    }

    /** @test */
    public function it_can_filter_by_nested_numeric_field_with_range(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['max_price' => 120], NestedModel::class)
            ->allowedFilters(
                NestedFilter::make('variants', 'max_price')
                    ->innerQuery(fn($value) => Query::range('variants.price')->lte((float) $value))
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[0]->id, $this->models[2]->id],
            $result->pluck('id')->all()
        );
    }
}
