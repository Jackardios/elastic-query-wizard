<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\NestedFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Support\Query;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class NestedFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_builds_nested_term_query_for_single_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['author' => 'john'])
            ->allowedFilters(NestedFilter::make('author', 'comments'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'nested' => [
                'path' => 'comments',
                'query' => [
                    'term' => ['comments.author' => ['value' => 'john']],
                ],
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_builds_nested_terms_query_for_multiple_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['author' => ['john', 'jane']])
            ->allowedFilters(NestedFilter::make('author', 'comments'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'nested' => [
                'path' => 'comments',
                'query' => [
                    'terms' => ['comments.author' => ['john', 'jane']],
                ],
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_applies_score_mode(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['author' => 'john'])
            ->allowedFilters(
                NestedFilter::make('author', 'comments')->scoreMode('avg')
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'nested' => [
                'path' => 'comments',
                'query' => [
                    'term' => ['comments.author' => ['value' => 'john']],
                ],
                'score_mode' => 'avg',
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_applies_ignore_unmapped(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['author' => 'john'])
            ->allowedFilters(
                NestedFilter::make('author', 'comments')->ignoreUnmapped()
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'nested' => [
                'path' => 'comments',
                'query' => [
                    'term' => ['comments.author' => ['value' => 'john']],
                ],
                'ignore_unmapped' => true,
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_uses_custom_inner_query_closure(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['min_rating' => 4])
            ->allowedFilters(
                NestedFilter::make('min_rating', 'reviews')
                    ->innerQuery(fn ($value) => Query::range('reviews.rating')->gte($value))
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'nested' => [
                'path' => 'reviews',
                'query' => [
                    'range' => ['reviews.rating' => ['gte' => 4]],
                ],
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_uses_custom_inner_query_interface(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['active' => true])
            ->allowedFilters(
                NestedFilter::make('active', 'comments')
                    ->innerQuery(Query::term('comments.active', true))
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'nested' => [
                'path' => 'comments',
                'query' => [
                    'term' => ['comments.active' => ['value' => true]],
                ],
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_does_not_add_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['author' => ''])
            ->allowedFilters(NestedFilter::make('author', 'comments'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_does_not_add_query_for_null_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['author' => null])
            ->allowedFilters(NestedFilter::make('author', 'comments'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_does_not_add_query_for_empty_array(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['author' => []])
            ->allowedFilters(NestedFilter::make('author', 'comments'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_resolves_alias_correctly(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['comment_author' => 'john'])
            ->allowedFilters(NestedFilter::make('author', 'comments', 'comment_author'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'nested' => [
                'path' => 'comments',
                'query' => [
                    'term' => ['comments.author' => ['value' => 'john']],
                ],
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_combines_all_options(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['author' => 'john'])
            ->allowedFilters(
                NestedFilter::make('author', 'comments')
                    ->scoreMode('max')
                    ->ignoreUnmapped(true)
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'nested' => [
                'path' => 'comments',
                'query' => [
                    'term' => ['comments.author' => ['value' => 'john']],
                ],
                'score_mode' => 'max',
                'ignore_unmapped' => true,
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_filters_blank_items_from_array(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['author' => ['john', '', null]])
            ->allowedFilters(NestedFilter::make('author', 'comments'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'nested' => [
                'path' => 'comments',
                'query' => [
                    'term' => ['comments.author' => ['value' => 'john']],
                ],
            ],
        ], $queries[0]);
    }
}
