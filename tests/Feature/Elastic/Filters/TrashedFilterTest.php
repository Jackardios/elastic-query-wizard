<?php

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

    public function setUp(): void
    {
        parent::setUp();

        Config::set('scout.soft_delete', true);

        $this->models = factory(SoftDeleteModel::class, 2)->create()
            ->merge(factory(SoftDeleteModel::class, 1)->create(['deleted_at' => now()]));
    }

    /** @test */
    public function it_should_filter_not_trashed_by_default()
    {
        $models = $this
            ->createQueryFromFilterRequest([
                'trashed' => '',
            ])
            ->setAllowedFilters(new TrashedFilter())
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
            ->setAllowedFilters(new TrashedFilter())
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
            ->setAllowedFilters(new TrashedFilter())
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $models);
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        return $this->createElasticWizardWithFilters($filters, SoftDeleteModel::class);
    }
}
