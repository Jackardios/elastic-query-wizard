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

    /** @test */
    public function it_inverts_logic_for_truthy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_value' => 'true'])
            ->allowedFilters(NullFilter::make('thumbnail', 'has_value')->withInvertedLogic());
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        // Inverted: truthy means NOT NULL (field exists)
        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'thumbnail']], $filterQueries[0]);
        $this->assertEmpty($mustNotQueries);
    }

    /** @test */
    public function it_inverts_logic_for_falsy_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_value' => 'false'])
            ->allowedFilters(NullFilter::make('thumbnail', 'has_value')->withInvertedLogic());
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        // Inverted: falsy means NULL (field doesn't exist)
        $this->assertEmpty($filterQueries);
        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'thumbnail']], $mustNotQueries[0]);
    }

    /** @test */
    public function it_can_reset_inverted_logic(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['is_null' => 'true'])
            ->allowedFilters(
                NullFilter::make('deleted_at', 'is_null')
                    ->withInvertedLogic()
                    ->withoutInvertedLogic()
            );
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        // Back to normal: truthy means NULL (field doesn't exist)
        $this->assertEmpty($filterQueries);
        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'deleted_at']], $mustNotQueries[0]);
    }

    /** @test */
    public function it_inverts_logic_for_integer_1(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_value' => '1'])
            ->allowedFilters(NullFilter::make('thumbnail', 'has_value')->withInvertedLogic());
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        // Inverted: 1 means NOT NULL (field exists)
        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['exists' => ['field' => 'thumbnail']], $filterQueries[0]);
    }

    /** @test */
    public function it_inverts_logic_for_integer_0(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['has_value' => '0'])
            ->allowedFilters(NullFilter::make('thumbnail', 'has_value')->withInvertedLogic());
        $wizard->build();

        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());

        // Inverted: 0 means NULL (field doesn't exist)
        $this->assertCount(1, $mustNotQueries);
        $this->assertEquals(['exists' => ['field' => 'thumbnail']], $mustNotQueries[0]);
    }
}
