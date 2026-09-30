<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * boolQuery() builds the wizard and returns the built search's bool query; a rebuild would drop a change made there.
 */
#[Group('unit')]
class BoolQueryRebuildTest extends UnitTestCase
{
    #[Test]
    public function reconfiguring_a_built_wizard_whose_bool_query_changed_throws(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['status' => 'x'])->allowedFilters(ElasticFilter::term('status'));
        $wizard->boolQuery()->addMust(Query::term('extra', 'y'));
        $wizard->build();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('the rebuild would drop the change');

        $wizard->allowedSorts('name');
    }

    #[Test]
    public function bool_query_builds_the_wizard_first(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['status' => 'x'])->allowedFilters(ElasticFilter::term('status'));
        $boolQuery = $wizard->boolQuery();

        $this->assertSame($wizard->getSubject()->boolQuery(), $boolQuery);
        $this->assertSame([['term' => ['status' => ['value' => 'x']]]], $this->getFilterQueries($boolQuery));
    }

    #[Test]
    public function a_bool_query_change_follows_the_search_builder_calls_made_before_it(): void
    {
        $wizard = $this->createElasticWizardWithFilters([]);
        $wizard->clearBoolQuery();
        $wizard->boolQuery()->addMust(Query::term('extra', 'y'));

        $this->assertSame([['term' => ['extra' => ['value' => 'y']]]], $this->getMustQueries($wizard->build()->boolQuery()));
    }

    #[Test]
    public function configuring_after_a_bool_query_change_before_the_build_throws(): void
    {
        $wizard = $this->createElasticWizardWithFilters([]);
        $wizard->boolQuery()->addMust(Query::term('extra', 'y'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('the rebuild would drop the change');

        $wizard->allowedFilters(ElasticFilter::term('status'));
    }

    #[Test]
    public function a_tapped_change_survives_a_rebuild(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['status' => 'x'])->allowedFilters(ElasticFilter::term('status'));
        $wizard->tapSearchBuilder(fn (SearchBuilder $builder) => $builder->boolQuery()->addMust(Query::term('extra', 'y')));
        $wizard->build();
        $wizard->allowedSorts('name');

        $this->assertSame([['term' => ['extra' => ['value' => 'y']]]], $this->getMustQueries($wizard->build()->boolQuery()));
    }
}
