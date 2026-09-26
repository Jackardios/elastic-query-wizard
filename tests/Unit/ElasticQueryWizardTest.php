<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Values\Sort;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('wizard')]
class ElasticQueryWizardTest extends UnitTestCase
{
    #[Test]
    public function it_throws_for_non_class_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$subject must be a model that uses `Jackardios\EsScoutDriver\Searchable` trait');

        ElasticQueryWizard::for('not a class name');
    }

    #[Test]
    public function it_throws_for_non_searchable_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ElasticQueryWizard::for(Model::class);
    }

    #[Test]
    public function it_builds_query_with_filters_and_sorts(): void
    {
        $wizard = $this->createElasticWizardFromQuery([
            'filter' => ['category' => 'test-value'],
            'sort' => '-name',
        ])
            ->allowedFilters(TermFilter::make('category'))
            ->allowedSorts(FieldSort::make('name'));

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());
        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertNotEmpty($filterQueries);
        $this->assertEquals(['term' => ['category' => ['value' => 'test-value']]], $filterQueries[0]);
        $this->assertEquals([['name' => 'desc']], $sorts);
    }

    #[Test]
    public function it_builds_query_with_multiple_elastic_filters(): void
    {
        $wizard = $this->createElasticWizardFromQuery([
            'filter' => [
                'category' => 'elastic-value',
                'name' => 'another-value',
            ],
        ])
            ->allowedFilters(
                TermFilter::make('category'),
                TermFilter::make('name')
            );

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(2, $filterQueries);
    }

    #[Test]
    public function it_applies_default_sorts(): void
    {
        $wizard = $this->createElasticWizardFromQuery([])
            ->allowedSorts('name', 'id')
            ->defaultSorts(new Sort('name'), new Sort('id', SortDirection::Descending));

        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            ['name' => 'asc'],
            ['id' => 'desc'],
        ], $sorts);
    }

    #[Test]
    public function it_overrides_default_sorts_with_requested_sorts(): void
    {
        $wizard = $this->createElasticWizardFromQuery([
            'sort' => '-category',
        ])
            ->allowedSorts('name', 'category')
            ->defaultSorts('name');

        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([['category' => 'desc']], $sorts);
    }

    #[Test]
    public function it_creates_wizard_from_model_instance(): void
    {
        $model = new TestModel;
        $wizard = ElasticQueryWizard::for($model);

        $this->assertInstanceOf(ElasticQueryWizard::class, $wizard);
    }

    #[Test]
    public function it_returns_subject_as_search_builder(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);

        $this->assertInstanceOf(SearchBuilder::class, $wizard->getSubject());
    }

    #[Test]
    public function it_applies_declarative_search_builder_mutations_before_build(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)
            ->query(ElasticQuery::match('name', 'John'))
            ->must(ElasticQuery::term('category', 'users'))
            ->from(10)
            ->size(5)
            ->trackTotalHits(true)
            ->allowedSorts('name')
            ->defaultSorts('name');

        $wizard->build();

        $mustQueries = $this->getMustQueries($wizard->boolQuery());
        $this->assertCount(1, $mustQueries);
        $this->assertSame(
            ['term' => ['category' => ['value' => 'users']]],
            $mustQueries[0]
        );

        $params = $wizard->getSubject()->toArray();

        $this->assertSame(10, $params['body']['from']);
        $this->assertSame(5, $params['body']['size']);
        $this->assertTrue($params['body']['track_total_hits']);

        $mustQueriesFromBody = $params['body']['query']['bool']['must'];
        $this->assertContains(['match' => ['name' => ['query' => 'John']]], $mustQueriesFromBody);
        $this->assertContains(['term' => ['category' => ['value' => 'users']]], $mustQueriesFromBody);
    }

    #[Test]
    public function it_reapplies_search_builder_mutations_after_build_invalidation(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)
            ->from(20)
            ->size(15);

        $wizard->build();

        // invalidate build with configuration change
        $wizard->allowedSorts('name');
        $wizard->build();

        $params = $wizard->getSubject()->toArray();

        $this->assertSame(20, $params['body']['from']);
        $this->assertSame(15, $params['body']['size']);
    }

    #[Test]
    public function it_applies_tap_search_builder_callback(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(fn ($builder) => $builder->minScore(0.75));

        $wizard->build();

        $params = $wizard->getSubject()->toArray();
        $this->assertSame(0.75, $params['body']['min_score']);
    }

    #[Test]
    public function bool_query_after_build_locks_configuration_changes(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->build();

        $wizard->boolQuery();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot modify query wizard configuration after calling query builder methods.');

        $wizard->allowedFilters(TermFilter::make('name'));
    }

    #[Test]
    public function a_clone_keeps_the_lock_of_a_bool_query_changed_after_build(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->build();
        $wizard->boolQuery()->filter(ElasticQuery::term('tenant_id', 7));

        $clone = clone $wizard;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot modify query wizard configuration after calling query builder methods.');

        $clone->allowedFilters(TermFilter::make('name'));
    }

    #[Test]
    public function modify_query_after_build_throws_logic_exception(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->build();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot call modifyQuery() after build().');

        $wizard->modifyQuery(static function (Builder $builder, array $rawResult): void {});
    }

    #[Test]
    public function modify_models_after_build_throws_logic_exception(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->build();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot call modifyModels() after build().');

        $wizard->modifyModels(static function (Collection $collection): Collection {
            return $collection;
        });
    }
}
