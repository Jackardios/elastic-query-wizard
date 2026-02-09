<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\SimpleQueryStringFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class SimpleQueryStringFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_simple_query_string_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'quick +brown -fox'])
            ->allowedFilters(SimpleQueryStringFilter::make('search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['simple_query_string' => ['query' => 'quick +brown -fox']], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => ''])
            ->allowedFilters(SimpleQueryStringFilter::make('search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['q' => 'quick +brown'])
            ->allowedFilters(SimpleQueryStringFilter::make('search', 'q'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['simple_query_string' => ['query' => 'quick +brown']], $queries[0]);
    }

    /** @test */
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

    /** @test */
    public function it_handles_array_input_by_joining_with_comma(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => ['quick', '+brown']])
            ->allowedFilters(SimpleQueryStringFilter::make('search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['simple_query_string' => ['query' => 'quick,+brown']], $queries[0]);
    }
}
