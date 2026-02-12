<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Filters\TrashedFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\SoftDeleteModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class TrashedFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('scout.soft_delete', true);

        $this->models = SoftDeleteModel::factory()->count(2)->create()
            ->merge(SoftDeleteModel::factory()->count(1)->create(['deleted_at' => now()]));
    }

    /** @test */
    public function it_should_filter_not_trashed_by_default()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'trashed' => '',
            ])
            ->allowedFilters(TrashedFilter::make())
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $models);
    }

    /** @test */
    public function it_can_filter_only_trashed()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'trashed' => 'only',
            ])
            ->allowedFilters(TrashedFilter::make())
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $models);
    }

    /** @test */
    public function it_can_filter_with_trashed()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'trashed' => 'with',
            ])
            ->allowedFilters(TrashedFilter::make())
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_can_filter_with_trashed_using_true_alias()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'trashed' => 'true',
            ])
            ->allowedFilters(TrashedFilter::make())
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_can_explicitly_filter_without_trashed()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'trashed' => 'without',
            ])
            ->allowedFilters(TrashedFilter::make())
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $models);
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        return $this->createElasticWizardWithFilters($filters, SoftDeleteModel::class);
    }
}
