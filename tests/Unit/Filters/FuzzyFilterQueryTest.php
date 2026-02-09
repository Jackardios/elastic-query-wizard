<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\FuzzyFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class FuzzyFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_fuzzy_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'jonh'])
            ->allowedFilters(FuzzyFilter::make('name'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['fuzzy' => ['name' => ['value' => 'jonh']]], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => ''])
            ->allowedFilters(FuzzyFilter::make('name'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'jonh'])
            ->allowedFilters(FuzzyFilter::make('name', 'search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['fuzzy' => ['name' => ['value' => 'jonh']]], $queries[0]);
    }

    /** @test */
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'jonh'])
            ->allowedFilters(
                FuzzyFilter::make('name')->withParameters(['fuzziness' => 'AUTO', 'max_expansions' => 50])
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'fuzzy' => [
                'name' => [
                    'value' => 'jonh',
                    'fuzziness' => 'AUTO',
                    'max_expansions' => 50,
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_handles_array_input_by_taking_first_element(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => ['jonh', 'jahn']])
            ->allowedFilters(FuzzyFilter::make('name'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['fuzzy' => ['name' => ['value' => 'jonh']]], $queries[0]);
    }

    /** @test */
    public function it_handles_empty_array_input(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => []])
            ->allowedFilters(FuzzyFilter::make('name'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }
}
