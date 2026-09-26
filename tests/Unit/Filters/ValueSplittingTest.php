<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Request values are split by laravel-query-wizard only, with its separator,
 * and filters whose value is one pattern or text are not split at all.
 */
#[Group('unit')]
#[Group('filter')]
class ValueSplittingTest extends UnitTestCase
{
    #[Test]
    public function a_term_value_is_split_once_by_the_configured_separator(): void
    {
        config()->set('query-wizard.separators.filters', ';');

        $this->assertSame(
            [['terms' => ['name' => ['Smith, John', 'Doe, Jane']]]],
            $this->filterQueries(ElasticFilter::term('name'), 'Smith, John;Doe, Jane')
        );
        $this->assertSame(
            [['term' => ['name' => ['value' => 'Smith, John']]]],
            $this->filterQueries(ElasticFilter::term('name'), 'Smith, John')
        );
    }

    #[Test]
    public function a_term_value_is_kept_whole_without_value_splitting(): void
    {
        $this->assertSame(
            [['term' => ['name' => ['value' => 'Smith, John']]]],
            $this->filterQueries(ElasticFilter::term('name')->withoutValueSplitting(), 'Smith, John')
        );
    }

    #[Test]
    public function a_nested_term_value_is_kept_whole_without_value_splitting(): void
    {
        $this->assertSame(
            [['nested' => ['path' => 'comments', 'query' => ['term' => ['comments.name' => ['value' => 'Smith, John']]]]]],
            $this->filterQueries(ElasticFilter::nested('comments', 'name')->withoutValueSplitting(), 'Smith, John')
        );
    }

    #[Test]
    public function ids_are_split_once_by_the_configured_separator(): void
    {
        config()->set('query-wizard.separators.filters', ';');

        $this->assertSame(
            [['ids' => ['values' => ['1,2', '3']]]],
            $this->filterQueries(ElasticFilter::ids('id'), '1,2;3')
        );
        $this->assertSame(
            [['ids' => ['values' => ['1,2']]]],
            $this->filterQueries(ElasticFilter::ids('id'), '1,2')
        );
    }

    /**
     * @return iterable<string, array{FilterInterface, string, array<string, mixed>}>
     */
    public static function unsplitFilters(): iterable
    {
        yield 'prefix' => [ElasticFilter::prefix('title'), 'a,b', ['prefix' => ['title' => ['value' => 'a,b']]]];
        yield 'wildcard' => [ElasticFilter::wildcard('title'), 'a*,b', ['wildcard' => ['title' => ['value' => 'a*,b']]]];
        yield 'regexp' => [ElasticFilter::regexp('title'), 'a{1,3}', ['regexp' => ['title' => ['value' => 'a{1,3}']]]];
        yield 'fuzzy' => [ElasticFilter::fuzzy('title'), 'a,b', ['fuzzy' => ['title' => ['value' => 'a,b']]]];
        yield 'match' => [ElasticFilter::match('title'), 'red, blue', ['match' => ['title' => ['query' => 'red, blue']]]];
        yield 'match phrase' => [ElasticFilter::matchPhrase('title'), 'Hello, world', ['match_phrase' => ['title' => ['query' => 'Hello, world']]]];
        yield 'match phrase prefix' => [ElasticFilter::matchPhrasePrefix('title'), 'Hello, wor', ['match_phrase_prefix' => ['title' => ['query' => 'Hello, wor']]]];
        yield 'multi match' => [ElasticFilter::multiMatch(['title', 'body'], 'title'), 'red, blue', ['multi_match' => ['fields' => ['title', 'body'], 'query' => 'red, blue']]];
        yield 'query string' => [ElasticFilter::queryString('title'), 'a, b', ['query_string' => ['query' => 'a, b', 'fields' => ['title'], 'allow_leading_wildcard' => false]]];
        yield 'simple query string' => [ElasticFilter::simpleQueryString('title'), 'a, b', ['simple_query_string' => ['query' => 'a, b', 'fields' => ['title']]]];
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('unsplitFilters')]
    public function a_pattern_or_text_value_is_not_split(FilterInterface $filter, string $value, array $expected): void
    {
        $queries = $this->filterQueries($filter, $value);
        $queries = $queries === [] ? $this->mustQueries($filter, $value) : $queries;

        $this->assertSame([$expected], $queries);
    }

    /**
     * @return array<int, mixed>
     */
    private function filterQueries(FilterInterface $filter, string $value): array
    {
        $wizard = $this->createElasticWizardWithFilters([$filter->getName() => $value])->allowedFilters($filter);
        $wizard->build();

        return $this->getFilterQueries($wizard->boolQuery());
    }

    /**
     * @return array<int, mixed>
     */
    private function mustQueries(FilterInterface $filter, string $value): array
    {
        $wizard = $this->createElasticWizardWithFilters([$filter->getName() => $value])->allowedFilters($filter);
        $wizard->build();

        return $this->getMustQueries($wizard->boolQuery());
    }
}
