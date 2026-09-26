<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticInclude;
use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\ElasticQueryWizard\ElasticSort;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\ModelQueryWizard;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Values\Sort;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Examples from the documentation that failed or misbehaved as written.
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
        $this->createElasticWizardWithIncludes('author,comments,commentsCount')
            ->allowedIncludes('author', 'comments', ElasticInclude::count('comments'), ElasticInclude::relationship('tags'))
            ->build();

        $this->addToAssertionCount(1);
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

    public function getType(): string
    {
        return 'custom';
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::MUST;
    }

    public function buildQuery(mixed $value): QueryInterface|array|null
    {
        return is_string($value) ? Query::match($this->property, $value) : null;
    }
}
