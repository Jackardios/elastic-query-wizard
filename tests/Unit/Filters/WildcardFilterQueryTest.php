<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\WildcardFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class WildcardFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_wildcard_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['sku' => 'ABC*'])
            ->allowedFilters(WildcardFilter::make('sku'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['wildcard' => ['sku' => ['value' => 'ABC*']]], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['sku' => ''])
            ->allowedFilters(WildcardFilter::make('sku'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['code' => 'ABC*'])
            ->allowedFilters(WildcardFilter::make('sku', 'code'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['wildcard' => ['sku' => ['value' => 'ABC*']]], $queries[0]);
    }

    /** @test */
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['sku' => 'ABC*'])
            ->allowedFilters(
                WildcardFilter::make('sku')->withParameters(['boost' => 1.5, 'case_insensitive' => true])
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'wildcard' => [
                'sku' => [
                    'value' => 'ABC*',
                    'boost' => 1.5,
                    'case_insensitive' => true,
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_handles_array_input_by_taking_first_element(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['sku' => ['ABC*', 'DEF*']])
            ->allowedFilters(WildcardFilter::make('sku'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['wildcard' => ['sku' => ['value' => 'ABC*']]], $queries[0]);
    }

    /** @test */
    public function it_handles_empty_array_input(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['sku' => []])
            ->allowedFilters(WildcardFilter::make('sku'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }
}
