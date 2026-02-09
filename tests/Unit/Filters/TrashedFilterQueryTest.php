<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Illuminate\Support\Facades\Config;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\SoftDeleteModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Enums\SoftDeleteMode;

/**
 * @group unit
 * @group filter
 */
class TrashedFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function with_trashed_sets_soft_delete_mode_to_with_trashed(): void
    {
        Config::set('scout.soft_delete', true);

        $wizard = $this
            ->createElasticWizardWithFilters(['trashed' => 'with'], SoftDeleteModel::class)
            ->allowedFilters(ElasticFilter::trashed());
        $wizard->build();

        $this->assertEquals(
            SoftDeleteMode::WithTrashed,
            $wizard->boolQuery()->getSoftDeleteMode()
        );
    }

    /** @test */
    public function only_trashed_sets_soft_delete_mode_to_only_trashed(): void
    {
        Config::set('scout.soft_delete', true);

        $wizard = $this
            ->createElasticWizardWithFilters(['trashed' => 'only'], SoftDeleteModel::class)
            ->allowedFilters(ElasticFilter::trashed());
        $wizard->build();

        $this->assertEquals(
            SoftDeleteMode::OnlyTrashed,
            $wizard->boolQuery()->getSoftDeleteMode()
        );
    }

    /** @test */
    public function default_keeps_soft_delete_mode_as_exclude_trashed(): void
    {
        Config::set('scout.soft_delete', true);

        $wizard = $this
            ->createElasticWizardFromQuery([], SoftDeleteModel::class)
            ->allowedFilters(ElasticFilter::trashed());
        $wizard->build();

        $this->assertEquals(
            SoftDeleteMode::ExcludeTrashed,
            $wizard->boolQuery()->getSoftDeleteMode()
        );
    }

    /** @test */
    public function no_soft_delete_config_keeps_default_mode(): void
    {
        Config::set('scout.soft_delete', false);

        $wizard = $this
            ->createElasticWizardFromQuery([], SoftDeleteModel::class)
            ->allowedFilters(ElasticFilter::trashed());
        $wizard->build();

        // Without soft_delete config, the mode remains ExcludeTrashed
        // but the Engine won't apply any __soft_deleted filter
        $this->assertEquals(
            SoftDeleteMode::ExcludeTrashed,
            $wizard->boolQuery()->getSoftDeleteMode()
        );
    }
}
