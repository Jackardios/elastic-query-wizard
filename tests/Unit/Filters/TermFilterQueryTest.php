<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class TermFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_term_query_for_a_single_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'John'])
            ->allowedFilters('name');
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['term' => ['name' => ['value' => 'John']]], $queries[0]);
    }

    /** @test */
    public function it_builds_a_terms_query_for_an_array_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => ['John', 'Jane']])
            ->allowedFilters('name');
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['terms' => ['name' => ['John', 'Jane']]], $queries[0]);
    }

    /** @test */
    public function it_builds_a_terms_query_for_comma_separated_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'John,Jane'])
            ->allowedFilters('name');
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['terms' => ['name' => ['John', 'Jane']]], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => ''])
            ->allowedFilters('name');
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['tag' => 'php'])
            ->allowedFilters(TermFilter::make('category', 'tag'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['term' => ['category' => ['value' => 'php']]], $queries[0]);
    }

    /** @test */
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'John'])
            ->allowedFilters(
                TermFilter::make('name')->withParameters(['boost' => 1.5])
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['term' => ['name' => ['value' => 'John', 'boost' => 1.5]]], $queries[0]);
    }

    /** @test */
    public function it_filters_out_blank_items_from_array_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => ['John', '', null]])
            ->allowedFilters('name');
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        // After filtering blank items, only 'John' remains, so it uses 'term' query
        $this->assertEquals(['term' => ['name' => ['value' => 'John']]], $queries[0]);
    }

    /** @test */
    public function it_filters_out_blank_items_and_uses_terms_for_multiple_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => ['John', '', null, 'Jane']])
            ->allowedFilters('name');
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['terms' => ['name' => ['John', 'Jane']]], $queries[0]);
    }
}
