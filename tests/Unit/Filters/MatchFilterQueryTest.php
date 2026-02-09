<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class MatchFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_match_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'John'])
            ->allowedFilters(MatchFilter::make('name'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['match' => ['name' => ['query' => 'John']]], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => ''])
            ->allowedFilters(MatchFilter::make('name'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'Mascow'])
            ->allowedFilters(
                MatchFilter::make('name')->withParameters(['fuzziness' => '1'])
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'match' => ['name' => ['query' => 'Mascow', 'fuzziness' => '1']],
        ], $queries[0]);
    }

    /** @test */
    public function it_joins_array_values_with_comma(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => ['foo', 'bar']])
            ->allowedFilters(MatchFilter::make('name'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['match' => ['name' => ['query' => 'foo,bar']]], $queries[0]);
    }
}
