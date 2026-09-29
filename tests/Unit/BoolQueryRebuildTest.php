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
 * A change made through boolQuery() lives on the built subject; a rebuild would drop it.
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
    public function configuring_before_the_first_build_keeps_the_change(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['status' => 'x']);
        $wizard->boolQuery()->addMust(Query::term('extra', 'y'));
        $wizard->allowedFilters(ElasticFilter::term('status'));

        $this->assertSame([['term' => ['extra' => ['value' => 'y']]]], $this->getMustQueries($wizard->build()->boolQuery()));
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
