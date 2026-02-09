<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\IdsFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class IdsFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_an_ids_query_for_single_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['id' => '123'])
            ->allowedFilters(IdsFilter::make('id'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['ids' => ['values' => ['123']]], $queries[0]);
    }

    /** @test */
    public function it_builds_an_ids_query_for_array_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['id' => ['123', '456', '789']])
            ->allowedFilters(IdsFilter::make('id'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['ids' => ['values' => ['123', '456', '789']]], $queries[0]);
    }

    /** @test */
    public function it_builds_an_ids_query_for_comma_separated_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['id' => '123,456,789'])
            ->allowedFilters(IdsFilter::make('id'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['ids' => ['values' => ['123', '456', '789']]], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['id' => ''])
            ->allowedFilters(IdsFilter::make('id'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['document_id' => '123'])
            ->allowedFilters(IdsFilter::make('id', 'document_id'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['ids' => ['values' => ['123']]], $queries[0]);
    }

    /** @test */
    public function it_filters_out_blank_items_from_array_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['id' => ['123', '', null, '456']])
            ->allowedFilters(IdsFilter::make('id'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['ids' => ['values' => ['123', '456']]], $queries[0]);
    }
}
