<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Sorts\NestedSort;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\NestedModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * @group elastic
 * @group sort
 * @group elastic-sort
 */
class NestedSortTest extends TestCase
{
    use AssertsCollectionSorting;

    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            NestedModel::factory()->create([
                'title' => 'Product A',
                'variants' => [
                    ['sku' => 'A-1', 'price' => 100.00, 'active' => true],
                    ['sku' => 'A-2', 'price' => 150.00, 'active' => false],
                ],
                'comments' => [
                    ['author' => 'john', 'text' => 'Great', 'rating' => 5],
                ],
            ]),
            NestedModel::factory()->create([
                'title' => 'Product B',
                'variants' => [
                    ['sku' => 'B-1', 'price' => 50.00, 'active' => true],
                ],
                'comments' => [
                    ['author' => 'jane', 'text' => 'Good', 'rating' => 3],
                ],
            ]),
            NestedModel::factory()->create([
                'title' => 'Product C',
                'variants' => [
                    ['sku' => 'C-1', 'price' => 200.00, 'active' => true],
                    ['sku' => 'C-2', 'price' => 75.00, 'active' => true],
                ],
                'comments' => [
                    ['author' => 'mike', 'text' => 'OK', 'rating' => 4],
                ],
            ]),
        ]);
    }

    /** @test */
    public function it_can_sort_by_nested_field_ascending(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('price', NestedModel::class)
            ->allowedSorts(NestedSort::make('variants', 'price', 'price')->mode('min'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // B has min 50, C has min 75, A has min 100
        $this->assertEquals([$this->models[1]->id, $this->models[2]->id, $this->models[0]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_by_nested_field_descending(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('-price', NestedModel::class)
            ->allowedSorts(NestedSort::make('variants', 'price', 'price')->mode('max'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // C has max 200, A has max 150, B has max 50
        $this->assertEquals([$this->models[2]->id, $this->models[0]->id, $this->models[1]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_with_min_mode(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('lowest_price', NestedModel::class)
            ->allowedSorts(NestedSort::make('variants', 'price', 'lowest_price')->mode('min'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Min prices: B=50, C=75, A=100
        $this->assertEquals([$this->models[1]->id, $this->models[2]->id, $this->models[0]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_with_max_mode(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('highest_price', NestedModel::class)
            ->allowedSorts(NestedSort::make('variants', 'price', 'highest_price')->mode('max'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Max prices: B=50, A=150, C=200
        $this->assertEquals([$this->models[1]->id, $this->models[0]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_with_avg_mode(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('avg_price', NestedModel::class)
            ->allowedSorts(NestedSort::make('variants', 'price', 'avg_price')->mode('avg'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Avg prices: B=50, A=125, C=137.5
        $this->assertEquals([$this->models[1]->id, $this->models[0]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_by_nested_integer_field(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('rating', NestedModel::class)
            ->allowedSorts(NestedSort::make('comments', 'rating', 'rating'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Ratings: B=3, C=4, A=5
        $this->assertEquals([$this->models[1]->id, $this->models[2]->id, $this->models[0]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_descending_with_alias(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('-best_rating', NestedModel::class)
            ->allowedSorts(NestedSort::make('comments', 'rating', 'rating', 'best_rating'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Ratings desc: A=5, C=4, B=3
        $this->assertEquals([$this->models[0]->id, $this->models[2]->id, $this->models[1]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_with_nested_filter(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('active_price', NestedModel::class)
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'active_price')
                    ->mode('min')
                    ->nestedFilter(Query::term('variants.active', true))
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Active min prices: B=50, C=75 (both active), A=100 (only A-1 active)
        $this->assertEquals([$this->models[1]->id, $this->models[2]->id, $this->models[0]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_with_missing_last(): void
    {
        NestedModel::factory()->create([
            'title' => 'Product D',
            'variants' => [],
            'comments' => [],
        ]);

        $result = $this
            ->createElasticWizardWithSorts('price', NestedModel::class)
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'price')
                    ->mode('min')
                    ->missingLast()
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
        // D with no variants should be last
        $this->assertEquals('Product D', $result->last()->title);
    }

    /** @test */
    public function it_can_sort_with_missing_first(): void
    {
        NestedModel::factory()->create([
            'title' => 'Product D',
            'variants' => [],
            'comments' => [],
        ]);

        $result = $this
            ->createElasticWizardWithSorts('price', NestedModel::class)
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'price')
                    ->mode('min')
                    ->missingFirst()
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
        // D with no variants should be first
        $this->assertEquals('Product D', $result->first()->title);
    }

    /** @test */
    public function it_can_sort_with_max_children(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('price', NestedModel::class)
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'price')
                    ->mode('min')
                    ->maxChildren(1)
            )
            ->build()
            ->execute()
            ->models();

        // Should still return results (maxChildren limits nested docs considered)
        $this->assertCount(3, $result);
    }

    /** @test */
    public function it_can_combine_multiple_sort_options(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('-active_price', NestedModel::class)
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'active_price')
                    ->mode('max')
                    ->missingLast()
                    ->nestedFilter(Query::term('variants.active', true))
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Active max prices desc: C=200 (both active), A=100 (only A-1 active), B=50
        $this->assertEquals([$this->models[2]->id, $this->models[0]->id, $this->models[1]->id], $result->pluck('id')->all());
    }
}
