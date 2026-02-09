<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\ExistsFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class ExistsFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(5)->create();
    }

    /** @test */
    public function it_can_filter_with_truthy_value(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => '1',
            ])
            ->allowedFilters(ExistsFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    /** @test */
    public function it_can_filter_with_falsy_value(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => '0',
            ])
            ->allowedFilters(ExistsFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => '',
            ])
            ->allowedFilters(ExistsFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }
}
