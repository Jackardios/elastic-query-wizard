<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\SimpleQueryStringFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class SimpleQueryStringFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_builds_a_simple_query_string_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'quick +brown -fox'])
            ->allowedFilters(SimpleQueryStringFilter::make('search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['simple_query_string' => ['query' => 'quick +brown -fox', 'fields' => ['search']]], $queries[0]);
    }

    #[Test]
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => ''])
            ->allowedFilters(SimpleQueryStringFilter::make('search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['q' => 'quick +brown'])
            ->allowedFilters(SimpleQueryStringFilter::make('search', 'q'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['simple_query_string' => ['query' => 'quick +brown', 'fields' => ['search']]], $queries[0]);
    }

    #[Test]
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'quick +brown'])
            ->allowedFilters(
                SimpleQueryStringFilter::make('search')->withParameters([
                    'fields' => ['title^2', 'content'],
                    'default_operator' => 'AND',
                ])
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'simple_query_string' => [
                'query' => 'quick +brown',
                'fields' => ['title^2', 'content'],
                'default_operator' => 'AND',
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_refuses_a_list(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters(['search' => ['quick', '+brown']])
            ->allowedFilters(SimpleQueryStringFilter::make('search'))
            ->build();
    }
}
