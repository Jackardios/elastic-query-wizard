<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\MoreLikeThisFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class MoreLikeThisFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_mlt_query_with_text(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'elasticsearch distributed search'])
            ->allowedFilters(MoreLikeThisFilter::make(['title', 'body'], 'similar'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title', 'body'],
                'like' => 'elasticsearch distributed search',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_builds_mlt_query_with_document_reference(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'similar' => [
                    '_index' => 'articles',
                    '_id' => '123',
                ],
            ])
            ->allowedFilters(MoreLikeThisFilter::make(['title', 'body'], 'similar'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title', 'body'],
                'like' => [
                    ['_index' => 'articles', '_id' => '123'],
                ],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_builds_mlt_query_with_array_of_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'similar' => ['elasticsearch', 'search engine', 'distributed'],
            ])
            ->allowedFilters(MoreLikeThisFilter::make(['title'], 'similar'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => ['elasticsearch', 'search engine', 'distributed'],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_min_term_freq(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')->minTermFreq(2)
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'min_term_freq' => 2,
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_max_query_terms(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')->maxQueryTerms(25)
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'max_query_terms' => 25,
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_doc_freq_limits(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')
                    ->minDocFreq(5)
                    ->maxDocFreq(1000)
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'min_doc_freq' => 5,
                'max_doc_freq' => 1000,
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_word_length_limits(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')
                    ->minWordLength(3)
                    ->maxWordLength(20)
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'min_word_length' => 3,
                'max_word_length' => 20,
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_analyzer(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')->analyzer('english')
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'analyzer' => 'english',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_minimum_should_match_as_int(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')->minimumShouldMatch(2)
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'minimum_should_match' => 2,
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_minimum_should_match_as_string(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')->minimumShouldMatch('30%')
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'minimum_should_match' => '30%',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_boost(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')->boost(1.5)
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'boost' => 1.5,
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_include(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')->include(true)
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'include' => true,
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_boost_terms(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title'], 'similar')->boostTerms(2.0)
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'test',
                'boost_terms' => 2.0,
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => ''])
            ->allowedFilters(MoreLikeThisFilter::make(['title'], 'similar'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_does_not_add_query_for_null_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => null])
            ->allowedFilters(MoreLikeThisFilter::make(['title'], 'similar'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_does_not_add_query_for_whitespace_only(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => '   '])
            ->allowedFilters(MoreLikeThisFilter::make(['title'], 'similar'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_does_not_add_query_for_empty_array(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => []])
            ->allowedFilters(MoreLikeThisFilter::make(['title'], 'similar'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_filters_blank_items_from_array(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'similar' => ['elasticsearch', '', null, 'search'],
            ])
            ->allowedFilters(MoreLikeThisFilter::make(['title'], 'similar'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => ['elasticsearch', 'search'],
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_uses_alias_correctly(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['related' => 'test'])
            ->allowedFilters(MoreLikeThisFilter::make(['title', 'body'], 'similar', 'related'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title', 'body'],
                'like' => 'test',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_returns_correct_type(): void
    {
        $filter = MoreLikeThisFilter::make(['title'], 'similar');

        $this->assertEquals('more_like_this', $filter->getType());
    }

    /** @test */
    public function it_combines_all_options(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test query'])
            ->allowedFilters(
                MoreLikeThisFilter::make(['title', 'body'], 'similar')
                    ->minTermFreq(2)
                    ->maxQueryTerms(25)
                    ->minDocFreq(5)
                    ->maxDocFreq(1000)
                    ->minWordLength(3)
                    ->maxWordLength(20)
                    ->analyzer('english')
                    ->minimumShouldMatch('30%')
                    ->boost(1.5)
                    ->include(false)
                    ->boostTerms(2.0)
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title', 'body'],
                'like' => 'test query',
                'min_term_freq' => 2,
                'max_query_terms' => 25,
                'min_doc_freq' => 5,
                'max_doc_freq' => 1000,
                'min_word_length' => 3,
                'max_word_length' => 20,
                'analyzer' => 'english',
                'minimum_should_match' => '30%',
                'boost' => 1.5,
                'include' => false,
                'boost_terms' => 2.0,
            ],
        ], $queries[0]);
    }
}
