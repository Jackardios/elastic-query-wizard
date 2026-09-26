<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class CallbackFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_applies_the_callback_to_bool_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'test'])
            ->allowedFilters(
                ElasticFilter::callback('search', function (SearchBuilder $builder, $value, $property) {
                    $builder->must(Query::match('name', $value));
                })
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['match' => ['name' => ['query' => 'test']]], $queries[0]);
    }

    #[Test]
    public function it_refuses_a_subject_that_is_not_a_search_builder(): void
    {
        $filter = ElasticFilter::callback('search', fn (SearchBuilder $builder) => null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Elastic callback filter "search" expects a '.SearchBuilder::class.'.');

        $filter->apply(new \stdClass, 'test');
    }

    #[Test]
    public function it_passes_the_property_name_to_the_callback(): void
    {
        $receivedProperty = null;

        $wizard = $this
            ->createElasticWizardWithFilters(['my_filter' => 'value'])
            ->allowedFilters(
                ElasticFilter::callback('my_filter', function ($builder, $value, $property) use (&$receivedProperty) {
                    $receivedProperty = $property;
                })
            );
        $wizard->build();

        $this->assertEquals('my_filter', $receivedProperty);
    }

    #[Test]
    public function it_can_add_filter_clauses_via_callback(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['active' => '1'])
            ->allowedFilters(
                ElasticFilter::callback('active', function (SearchBuilder $builder, $value) {
                    $builder->filter(['term' => ['is_active' => ['value' => (bool) $value]]]);
                })
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['term' => ['is_active' => ['value' => true]]], $queries[0]);
    }
}
