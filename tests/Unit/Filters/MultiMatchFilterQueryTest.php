<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Filters\MultiMatchFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class MultiMatchFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_builds_a_multi_match_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'hello'])
            ->allowedFilters(MultiMatchFilter::make('search', ['name', 'category']));
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

    #[Test]
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => ''])
            ->allowedFilters(MultiMatchFilter::make('search', ['name', 'category']));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'hello'])
            ->allowedFilters(
                MultiMatchFilter::make('search', ['name', 'category'])
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

    #[Test]
    public function it_builds_via_factory(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'hello'])
            ->allowedFilters(ElasticFilter::multiMatch('search', ['name', 'category']));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('multi_match', $queries[0]);
    }
}
