<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticInclude;
use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\ElasticQueryWizard\ElasticSort;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\RelatedModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Values\Sort;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Examples from the documentation, run as written.
 */
#[Group('unit')]
class DocumentationExamplesTest extends UnitTestCase
{
    #[Test]
    public function a_bool_query_takes_clauses_through_must_should_and_filter(): void
    {
        $query = Query::bool()
            ->should(Query::term('code', 'a'))
            ->should(Query::match('code_text', 'a'))
            ->minimumShouldMatch(1);

        $this->assertCount(2, $query->toArray()['bool']['should']);
    }

    #[Test]
    public function the_built_search_builder_takes_further_clauses(): void
    {
        $search = $this->createElasticWizardFromQuery()
            ->allowedFilters(ElasticFilter::term('status'))
            ->build();

        $search->must(ElasticQuery::matchPhrase('content', 'exact phrase'));

        $this->assertSame(
            [['match_phrase' => ['content' => ['query' => 'exact phrase']]]],
            $search->toArray()['body']['query']['bool']['must']
        );
    }

    #[Test]
    public function schema_filters_and_an_extra_filter_are_allowed_in_one_list(): void
    {
        $schema = new DocumentedPostSchema;
        $wizard = $this->createElasticWizardWithFilters(['featured' => 'true'])->schema($schema);
        $wizard->allowedFilters([
            ...$schema->filters($wizard),
            ElasticFilter::term('featured'),
        ]);
        $wizard->build();

        $this->assertContains(['term' => ['featured' => ['value' => 'true']]], $this->getFilterQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_public_endpoint_keeps_its_hard_condition_when_the_filter_is_disallowed(): void
    {
        $wizard = $this->createElasticWizardFromQuery()
            ->schema(new DocumentedPostSchema)
            ->tapSearchBuilder(fn ($builder) => $builder->filter(ElasticQuery::term('status', 'published')))
            ->disallowedFilters('status');
        $wizard->build();

        $this->assertSame([['term' => ['status' => ['value' => 'published']]]], $this->getFilterQueries($wizard->boolQuery()));
    }

    #[Test]
    public function the_upgrade_guide_custom_filter_builds_its_query(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['title' => 'hello'])
            ->allowedFilters(DocumentedCustomFilter::make('title'));
        $wizard->build();

        $this->assertSame([['match' => ['title' => ['query' => 'hello']]]], $this->getMustQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_model_query_wizard_takes_a_schema_after_for(): void
    {
        $model = new TestModel(['name' => 'Alice']);

        $processed = ModelQueryWizard::for($model)->schema(DocumentedPostSchema::class)->process();

        $this->assertSame($model, $processed);
    }

    #[Test]
    public function a_default_sort_is_a_sort_value(): void
    {
        $search = $this->createElasticWizardFromQuery()
            ->allowedSorts(ElasticSort::field('created_at'))
            ->defaultSorts(new Sort('created_at', SortDirection::Descending))
            ->build();

        $this->assertSame([['created_at' => 'desc']], $search->toArray()['body']['sort']);
    }

    #[Test]
    public function the_count_include_of_an_allowed_relation_is_requested_by_its_name(): void
    {
        $search = $this->createElasticWizardWithIncludes('author,comments,commentsCount', DocumentedPost::class)
            ->allowedIncludes('author', 'comments', ElasticInclude::count('comments'), ElasticInclude::relationship('tags'))
            ->build();

        $query = DocumentedPost::query();
        $queryModifiers = (new \ReflectionProperty(SearchBuilder::class, 'queryModifiers'))->getValue($search);
        foreach ($queryModifiers[(new DocumentedPost)->searchableAs()] as $modifyQuery) {
            $modifyQuery($query, []);
        }

        $this->assertEqualsCanonicalizing(['author', 'comments'], array_keys($query->getEagerLoads()));
        $this->assertStringContainsString('as "comments_count"', $query->toSql());
    }

    #[Test]
    public function a_term_filter_as_number_sends_numbers(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['id' => '5,7'])
            ->allowedFilters(ElasticFilter::term('id')->asNumber());
        $wizard->build();

        $this->assertSame([['terms' => ['id' => [5, 7]]]], $this->getFilterQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_term_filter_as_number_refuses_text(): void
    {
        $this->expectException(InvalidFilterValue::class);

        $this->createElasticWizardWithFilters(['id' => 'abc'])
            ->allowedFilters(ElasticFilter::term('id')->asNumber())
            ->build();
    }

    #[Test]
    public function a_range_filter_as_number_refuses_a_date(): void
    {
        $this->expectException(InvalidRangeValue::class);

        $this->createElasticWizardWithFilters(['price' => ['gte' => '2024-01-01']])
            ->allowedFilters(ElasticFilter::range('price')->asNumber())
            ->build();
    }

    #[Test]
    public function an_ids_filter_as_number_refuses_text(): void
    {
        $this->expectException(InvalidFilterValue::class);

        $this->createElasticWizardWithFilters(['_id' => '1,abc'])
            ->allowedFilters(ElasticFilter::ids('_id')->asNumber())
            ->build();
    }

    #[Test]
    public function a_more_like_this_filter_refuses_document_references_by_default(): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('this filter does not take document references');

        $this->createElasticWizardWithFilters(['similar' => ['_id' => '123']])
            ->allowedFilters(ElasticFilter::moreLikeThis('similar', ['title', 'body']))
            ->build();
    }

    #[Test]
    public function a_more_like_this_filter_takes_document_references_once_allowed(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['similar' => ['elasticsearch', ['_id' => '123']]])
            ->allowedFilters(ElasticFilter::moreLikeThis('similar', ['title', 'body'])->allowDocumentReferences());
        $wizard->build();

        $query = $this->getMustQueries($wizard->boolQuery())[0];

        $this->assertSame(['elasticsearch', ['_id' => '123']], $query['more_like_this']['like']);
        $this->assertSame(['title', 'body'], $query['more_like_this']['fields']);
    }

    #[Test]
    public function a_simple_query_string_without_the_fuzzy_flag_sends_the_flags(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['q' => 'word~2'])
            ->allowedFilters(ElasticFilter::simpleQueryString('body', 'q')->withParameters([
                'flags' => 'AND|OR|NOT|PHRASE|PRECEDENCE|PREFIX|ESCAPE|WHITESPACE',
            ]));
        $wizard->build();

        $query = $this->getMustQueries($wizard->boolQuery())[0];

        $this->assertSame('word~2', $query['simple_query_string']['query']);
        $this->assertSame('AND|OR|NOT|PHRASE|PRECEDENCE|PREFIX|ESCAPE|WHITESPACE', $query['simple_query_string']['flags']);
    }

    #[Test]
    public function a_regexp_filter_takes_the_documented_hardening_parameters(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['slug' => 'post-.*'])
            ->allowedFilters(
                ElasticFilter::regexp('slug')
                    ->maxLength(100)
                    ->withParameters(['flags' => 'NONE', 'max_determinized_states' => 1000])
            );
        $wizard->build();

        $this->assertSame(
            [['regexp' => ['slug' => ['value' => 'post-.*', 'flags' => 'NONE', 'max_determinized_states' => 1000]]]],
            $this->getFilterQueries($wizard->boolQuery())
        );
    }
}

class DocumentedPost extends TestModel
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class, 'related_model_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RelatedModel::class, 'test_model_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(RelatedModel::class, 'test_model_id');
    }
}

class DocumentedPostSchema extends ResourceSchema
{
    public function model(): string
    {
        return TestModel::class;
    }

    public function filters(QueryWizardInterface $wizard): array
    {
        return ['status'];
    }

    public function defaultFilters(QueryWizardInterface $wizard): array
    {
        return ['status' => 'published'];
    }
}

final class DocumentedCustomFilter extends AbstractElasticFilter
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::Must;
    }

    public function buildQuery(mixed $value): QueryInterface|array|null
    {
        return is_string($value) ? Query::match($this->property, $value) : null;
    }
}
