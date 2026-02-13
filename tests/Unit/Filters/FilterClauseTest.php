<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\Filters\FuzzyFilter;
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Filters\MatchPhrasePrefixFilter;
use Jackardios\ElasticQueryWizard\Filters\MatchPhraseFilter;
use Jackardios\ElasticQueryWizard\Filters\MoreLikeThisFilter;
use Jackardios\ElasticQueryWizard\Filters\MultiMatchFilter;
use Jackardios\ElasticQueryWizard\Filters\QueryStringFilter;
use Jackardios\ElasticQueryWizard\Filters\SimpleQueryStringFilter;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 * @group clause
 */
class FilterClauseTest extends UnitTestCase
{
    /** @test */
    public function term_filter_defaults_to_filter_clause(): void
    {
        $filter = TermFilter::make('name');

        $this->assertEquals(BoolClause::FILTER, $filter->getEffectiveClause());
    }

    /** @test */
    public function term_filter_can_be_set_to_must(): void
    {
        $filter = TermFilter::make('name')->inMust();

        $this->assertEquals(BoolClause::MUST, $filter->getEffectiveClause());
    }

    /** @test */
    public function term_filter_can_be_set_to_should(): void
    {
        $filter = TermFilter::make('name')->inShould();

        $this->assertEquals(BoolClause::SHOULD, $filter->getEffectiveClause());
    }

    /** @test */
    public function term_filter_can_be_set_to_must_not(): void
    {
        $filter = TermFilter::make('name')->inMustNot();

        $this->assertEquals(BoolClause::MUST_NOT, $filter->getEffectiveClause());
    }

    /** @test */
    public function match_filter_defaults_to_must_clause(): void
    {
        $filter = MatchFilter::make('title');

        $this->assertEquals(BoolClause::MUST, $filter->getEffectiveClause());
    }

    /** @test */
    public function match_filter_can_be_set_to_filter(): void
    {
        $filter = MatchFilter::make('title')->inFilter();

        $this->assertEquals(BoolClause::FILTER, $filter->getEffectiveClause());
    }

    /** @test */
    public function multi_match_filter_defaults_to_must_clause(): void
    {
        $filter = MultiMatchFilter::make(['title', 'content'], 'search');

        $this->assertEquals(BoolClause::MUST, $filter->getEffectiveClause());
    }

    /** @test */
    public function fuzzy_filter_defaults_to_must_clause(): void
    {
        $filter = FuzzyFilter::make('name');

        $this->assertEquals(BoolClause::MUST, $filter->getEffectiveClause());
    }

    /** @test */
    public function match_phrase_filter_defaults_to_must_clause(): void
    {
        $filter = MatchPhraseFilter::make('content');

        $this->assertEquals(BoolClause::MUST, $filter->getEffectiveClause());
    }

    /** @test */
    public function match_phrase_prefix_filter_defaults_to_must_clause(): void
    {
        $filter = MatchPhrasePrefixFilter::make('content');

        $this->assertEquals(BoolClause::MUST, $filter->getEffectiveClause());
    }

    /** @test */
    public function query_string_filter_defaults_to_must_clause(): void
    {
        $filter = QueryStringFilter::make('search');

        $this->assertEquals(BoolClause::MUST, $filter->getEffectiveClause());
    }

    /** @test */
    public function simple_query_string_filter_defaults_to_must_clause(): void
    {
        $filter = SimpleQueryStringFilter::make('search');

        $this->assertEquals(BoolClause::MUST, $filter->getEffectiveClause());
    }

    /** @test */
    public function more_like_this_filter_defaults_to_must_clause(): void
    {
        $filter = MoreLikeThisFilter::make(['title', 'content'], 'similar');

        $this->assertEquals(BoolClause::MUST, $filter->getEffectiveClause());
    }

    /** @test */
    public function it_applies_term_filter_to_should_clause(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['status' => 'active'])
            ->allowedFilters(TermFilter::make('status')->inShould());
        $wizard->build();

        $shouldQueries = $this->getShouldQueries($wizard->boolQuery());
        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $shouldQueries);
        $this->assertEmpty($filterQueries);
        $this->assertEquals(['term' => ['status' => ['value' => 'active']]], $shouldQueries[0]);
    }

    /** @test */
    public function it_applies_term_filter_to_must_not_clause(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['status' => 'deleted'])
            ->allowedFilters(TermFilter::make('status')->inMustNot());
        $wizard->build();

        $mustNotQueries = $this->getMustNotQueries($wizard->boolQuery());
        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $mustNotQueries);
        $this->assertEmpty($filterQueries);
        $this->assertEquals(['term' => ['status' => ['value' => 'deleted']]], $mustNotQueries[0]);
    }

    /** @test */
    public function it_applies_match_filter_to_filter_clause_when_overridden(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['title' => 'test'])
            ->allowedFilters(MatchFilter::make('title')->inFilter());
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $mustQueries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEmpty($mustQueries);
        $this->assertEquals(['match' => ['title' => ['query' => 'test']]], $filterQueries[0]);
    }
}
