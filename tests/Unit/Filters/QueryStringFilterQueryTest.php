<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\QueryStringFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class QueryStringFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_builds_a_query_string_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'quick AND brown'])
            ->allowedFilters(QueryStringFilter::make('search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['query_string' => ['query' => 'quick AND brown', 'fields' => ['search'], 'allow_leading_wildcard' => false]], $queries[0]);
    }

    #[Test]
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => ''])
            ->allowedFilters(QueryStringFilter::make('search'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['q' => 'quick AND brown'])
            ->allowedFilters(QueryStringFilter::make('search', 'q'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['query_string' => ['query' => 'quick AND brown', 'fields' => ['search'], 'allow_leading_wildcard' => false]], $queries[0]);
    }

    #[Test]
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['search' => 'quick AND brown'])
            ->allowedFilters(
                QueryStringFilter::make('search')->withParameters([
                    'default_field' => 'content',
                    'default_operator' => 'AND',
                ])
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'query_string' => [
                'query' => 'quick AND brown',
                'allow_leading_wildcard' => false,
                'default_field' => 'content',
                'default_operator' => 'AND',
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_refuses_a_list(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters(['search' => ['quick', 'brown']])
            ->allowedFilters(QueryStringFilter::make('search'))
            ->build();
    }

    #[Test]
    public function it_searches_only_its_property_unless_fields_are_set(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['q' => 'quick'])
            ->allowedFilters(QueryStringFilter::make('title', 'q')->withParameters(['fields' => ['title', 'body']]));
        $wizard->build();

        $this->assertSame(['title', 'body'], $this->getMustQueries($wizard->boolQuery())[0]['query_string']['fields']);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function leadingWildcards(): array
    {
        return [
            'leading star' => ['*abc', true],
            'second term' => ['a *b', true],
            'field value' => ['title:*b', true],
            'group' => ['(*a)', true],
            'required term' => ['+*a', true],
            'excluded term' => ['-*a', true],
            'leading question mark' => ['?bc', true],
            'after an operator' => ['a AND *b', true],
            'double star' => ['**', true],
            'phrase' => ['"a *b"', false],
            'range' => ['n:[* TO 5]', false],
            'escaped star' => ['\\*a', false],
            'inner wildcard' => ['a?c', false],
            'trailing star' => ['ab*', false],
            'lone star' => ['*', false],
            'field exists' => ['title:*', false],
            'after fuzziness' => ['a~*b', true],
            'after fuzziness with a distance' => ['a~0.5*b', true],
            'after a boost' => ['a^2*b', true],
            'after a phrase' => ['"a"*b', true],
            'after a group' => ['(a)*b', true],
            'after a range' => ['[a TO b]*c', true],
            'after a regular expression' => ['/ab/*c', true],
            'after an exclamation mark' => ['a!*b', true],
            'second field' => ['a:b:*c', true],
            'after an escaped backslash' => ['\\\\ *b', true],
            'lone question mark' => ['?', true],
            'after an ideographic space' => ["a\u{3000}*b", true],
            'lone star before an ideographic space' => ["*\u{3000}a", false],
            'star inside a regular expression' => ['/a *b/', false],
            'slash inside a term' => ['a/*b/', false],
            'plus inside a term' => ['a+*b', false],
            'operator characters inside a term' => ['a&&*b', false],
            'escaped space' => ['a\\ *b', false],
            'escaped backslash inside a term' => ['\\\\*a', false],
            'lone star with fuzziness' => ['*~', false],
            'lone star with a boost' => ['*^2', false],
            'open range' => ['{a TO *}', false],
            'star in a field phrase' => ['a:"*b"', false],
            'star in an unclosed phrase' => ['"a *b', false],
            'escaped quote in an unclosed phrase' => ['"a \\" *b', false],
            'star in an unclosed range' => ['[a TO *b', false],
            'star in an unclosed regular expression' => ['/a *b', false],
            'unclosed group' => ['(*a', true],
            'trailing escape' => ['a \\', false],
        ];
    }

    #[Test]
    #[DataProvider('leadingWildcards')]
    public function a_term_starting_with_a_wildcard_is_refused_as_elasticsearch_would(string $query, bool $refused): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['q' => $query])
            ->allowedFilters(QueryStringFilter::make('title', 'q'));

        if ($refused) {
            $this->expectException(InvalidFilterValue::class);
            $this->expectExceptionMessage('A term may not start with');
        }

        $wizard->build();

        $this->assertSame($query, $this->getMustQueries($wizard->boolQuery())[0]['query_string']['query']);
    }

    #[Test]
    public function allow_leading_wildcard_lets_a_leading_wildcard_through(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['q' => '*abc'])
            ->allowedFilters(QueryStringFilter::make('title', 'q')->withParameters(['allow_leading_wildcard' => true]));
        $wizard->build();

        $this->assertTrue($this->getMustQueries($wizard->boolQuery())[0]['query_string']['allow_leading_wildcard']);
    }
}
