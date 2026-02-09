<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Filters\MultiMatchFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class MultiMatchFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_multi_match_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'hello'])
            ->allowedFilters(MultiMatchFilter::make(['name', 'category'], 'search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'multi_match' => [
                'fields' => ['name', 'category'],
                'query' => 'hello',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => ''])
            ->allowedFilters(MultiMatchFilter::make(['name', 'category'], 'search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'hello'])
            ->allowedFilters(
                MultiMatchFilter::make(['name', 'category'], 'search')
                    ->withParameters(['fuzziness' => 'AUTO'])
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'multi_match' => [
                'fields' => ['name', 'category'],
                'query' => 'hello',
                'fuzziness' => 'AUTO',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_builds_via_factory(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'hello'])
            ->allowedFilters(ElasticFilter::multiMatch(['name', 'category'], 'search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('multi_match', $queries[0]);
    }
}
