<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\NullFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class NullFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_adds_a_must_not_clause_for_truthy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => 'true'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertEmpty($filterQueries);
        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'deleted_at']], $mustNotQueries[0]);
    }

    /** @test */
    public function it_adds_a_filter_clause_for_falsy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => 'false'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'deleted_at']], $filterQueries[0]);
        $this->assertEmpty($mustNotQueries);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => ''])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertEmpty($filterQueries);
        $this->assertEmpty($mustNotQueries);
    }

    /** @test */
    public function it_ignores_invalid_boolean_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => 'invalid'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertEmpty($filterQueries);
        $this->assertEmpty($mustNotQueries);
    }

    /** @test */
    public function it_adds_a_must_not_clause_for_integer_1(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => '1'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'deleted_at']], $mustNotQueries[0]);
    }

    /** @test */
    public function it_adds_a_filter_clause_for_integer_0(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => '0'])
            ->allowedFilters(NullFilter::make('deleted_at', 'is_null'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'deleted_at']], $filterQueries[0]);
    }

    /** @test */
    public function it_returns_correct_type(): void
    {
        $filter = NullFilter::make('deleted_at', 'is_null');

        $this->assertEquals('null', $filter->getType());
    }
}
