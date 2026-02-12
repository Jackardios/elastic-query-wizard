<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\NullFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class NullFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'With Category', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'With Category Too', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'No Category', 'category' => null]),
        ]);
    }

    /** @test */
    public function it_can_filter_for_null_values_with_true(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'true'])
            ->allowedFilters(NullFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        // category is empty string -> considered as not existing in ES term context
        // But field still exists, so exists query returns true
        // This tests the IS NULL behavior (field doesn't exist)
        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_can_filter_for_not_null_values_with_false(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'false'])
            ->allowedFilters(NullFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        // Fields exist, so should return models with existing category field
        $this->assertGreaterThanOrEqual(2, $result->count());
    }

    /** @test */
    public function it_can_invert_logic_for_true(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'true'])
            ->allowedFilters(NullFilter::make('category')->withInvertedLogic())
            ->build()
            ->execute()
            ->models();

        // Inverted: true means NOT NULL (field exists)
        $this->assertGreaterThanOrEqual(2, $result->count());
    }

    /** @test */
    public function it_can_invert_logic_for_false(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => 'false'])
            ->allowedFilters(NullFilter::make('category')->withInvertedLogic())
            ->build()
            ->execute()
            ->models();

        // Inverted: false means NULL (field doesn't exist)
        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => ''])
            ->allowedFilters(NullFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
    }

    /** @test */
    public function it_handles_numeric_boolean_values(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['category' => '1'])
            ->allowedFilters(NullFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        // 1 = true = IS NULL
        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['has_no_category' => 'true'])
            ->allowedFilters(NullFilter::make('category', 'has_no_category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
    }
}
