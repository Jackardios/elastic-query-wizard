<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\MoreLikeThisFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class MoreLikeThisFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_builds_mlt_query_with_text(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'elasticsearch distributed search'])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title', 'body']));
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

    #[Test]
    public function it_builds_mlt_query_with_document_reference(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'similar' => [
                    '_id' => '123',
                ],
            ])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title', 'body'])->allowDocumentReferences());
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'more_like_this' => [
                'fields' => ['title', 'body'],
                'like' => [
                    ['_id' => '123'],
                ],
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_builds_mlt_query_with_array_of_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'similar' => ['elasticsearch', 'search engine', 'distributed'],
            ])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title']));
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

    #[Test]
    public function it_applies_min_term_freq(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])->minTermFreq(2)
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

    #[Test]
    public function it_applies_max_query_terms(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])->maxQueryTerms(25)
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

    #[Test]
    public function it_applies_doc_freq_limits(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])
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

    #[Test]
    public function it_applies_word_length_limits(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])
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

    #[Test]
    public function it_applies_analyzer(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])->analyzer('english')
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

    #[Test]
    public function it_applies_minimum_should_match_as_int(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])->minimumShouldMatch(2)
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

    #[Test]
    public function it_applies_minimum_should_match_as_string(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])->minimumShouldMatch('30%')
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

    #[Test]
    public function it_applies_boost(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])->boost(1.5)
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

    #[Test]
    public function it_applies_include(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])->include(true)
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

    #[Test]
    public function it_applies_boost_terms(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title'])->boostTerms(2.0)
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

    #[Test]
    public function it_does_not_add_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => ''])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title']));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_does_not_add_query_for_null_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => null])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title']));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_does_not_add_query_for_whitespace_only(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => '   '])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title']));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_does_not_add_query_for_empty_array(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => []])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title']));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_does_not_add_query_when_the_prepared_items_are_all_blank(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'apple'])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title'])->prepareValueWith(fn (): array => ['', ' ']));
        $wizard->build();

        $this->assertEmpty($this->getMustQueries($wizard->boolQuery()));
    }

    #[Test]
    public function it_filters_blank_items_from_array(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'similar' => ['elasticsearch', '', null, 'search'],
            ])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title']));
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

    #[Test]
    public function it_uses_alias_correctly(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['related' => 'test'])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title', 'body'], 'related'));
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

    #[Test]
    public function it_combines_all_options(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['similar' => 'test query'])
            ->allowedFilters(
                MoreLikeThisFilter::make('similar', ['title', 'body'])
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

    #[Test]
    public function a_text_with_the_separator_is_one_text_and_several_texts_come_as_a_list(): void
    {
        $like = fn (mixed $value): mixed => $this->getMustQueries(
            tap($this->createElasticWizardWithFilters(['similar' => $value])
                ->allowedFilters(MoreLikeThisFilter::make('similar', ['title'])->allowDocumentReferences()))->build()->boolQuery()
        )[0]['more_like_this']['like'];

        $this->assertSame('red, blue', $like('red, blue'));
        $this->assertSame(['red', 'blue', ['_id' => '7']], $like(['red', ' ', 'blue', ['_id' => '7']]));
        $this->assertSame(['red', 'blue'], $like([1 => 'red', 3 => 'blue']));
    }

    #[Test]
    public function the_length_limit_applies_to_each_text(): void
    {
        $this->expectException(InvalidFilterValue::class);

        $this->createElasticWizardWithFilters(['similar' => ['red', 'blue']])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title'])->maxLength(3))
            ->build();
    }

    #[Test]
    public function a_document_reference_takes_only_an_id(): void
    {
        foreach ([['_index' => 'users', '_id' => '1'], [['_id' => '1', '_routing' => 'x']], ['_id' => ['1']], ['_id' => '1', 'x' => '']] as $value) {
            try {
                $this->createElasticWizardWithFilters(['similar' => $value])
                    ->allowedFilters(MoreLikeThisFilter::make('similar', ['title'])->allowDocumentReferences())
                    ->build();
                $this->fail('The reference was accepted: '.json_encode($value));
            } catch (InvalidFilterValue $exception) {
                $this->assertStringContainsString('A document reference takes only an `_id`', $exception->getMessage());
            }
        }
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function documentReferences(): array
    {
        return [
            'one reference' => [['_id' => '1']],
            'a reference in a list' => [['red', ['_id' => '1']]],
        ];
    }

    #[Test]
    #[DataProvider('documentReferences')]
    public function a_document_reference_is_refused_unless_the_filter_allows_them(mixed $value): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected a text or a list of texts: this filter does not take document references.');

        $this->createElasticWizardWithFilters(['similar' => $value])
            ->allowedFilters(MoreLikeThisFilter::make('similar', ['title']))
            ->build();
    }
}
