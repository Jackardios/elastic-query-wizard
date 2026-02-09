<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\MatchPhrasePrefixFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class MatchPhrasePrefixFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_match_phrase_prefix_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['bio' => 'quick brown fo'])
            ->allowedFilters(MatchPhrasePrefixFilter::make('bio'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['match_phrase_prefix' => ['bio' => ['query' => 'quick brown fo']]], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['bio' => ''])
            ->allowedFilters(MatchPhrasePrefixFilter::make('bio'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['description' => 'quick brown fo'])
            ->allowedFilters(MatchPhrasePrefixFilter::make('bio', 'description'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['match_phrase_prefix' => ['bio' => ['query' => 'quick brown fo']]], $queries[0]);
    }

    /** @test */
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['bio' => 'quick brown fo'])
            ->allowedFilters(
                MatchPhrasePrefixFilter::make('bio')->withParameters(['max_expansions' => 10, 'slop' => 2])
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'match_phrase_prefix' => [
                'bio' => [
                    'query' => 'quick brown fo',
                    'max_expansions' => 10,
                    'slop' => 2,
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_handles_array_input_by_joining_with_comma(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['bio' => ['quick', 'brown', 'fo']])
            ->allowedFilters(MatchPhrasePrefixFilter::make('bio'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['match_phrase_prefix' => ['bio' => ['query' => 'quick,brown,fo']]], $queries[0]);
    }
}
