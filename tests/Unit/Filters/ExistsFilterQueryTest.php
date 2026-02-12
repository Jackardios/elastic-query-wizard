<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\ExistsFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class ExistsFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_adds_a_filter_clause_for_truthy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => 'true'])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'has_image']], $filterQueries[0]);
        $this->assertEmpty($mustNotQueries);
    }

    /** @test */
    public function it_adds_a_must_not_clause_for_falsy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => 'false'])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertEmpty($filterQueries);
        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'has_image']], $mustNotQueries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => ''])
            ->allowedFilters(ExistsFilter::make('has_image'));
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
            ->createElasticWizardWithFilters(['has_image' => 'definitely'])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertEmpty($filterQueries);
        $this->assertEmpty($mustNotQueries);
    }

    /** @test */
    public function it_adds_a_filter_clause_for_integer_1(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => '1'])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'has_image']], $filterQueries[0]);
    }

    /** @test */
    public function it_adds_a_must_not_clause_for_integer_0(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_image' => '0'])
            ->allowedFilters(ExistsFilter::make('has_image'));
        $wizard->build();

        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'has_image']], $mustNotQueries[0]);
    }
}
