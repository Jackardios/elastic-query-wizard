<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use Jackardios\QueryWizard\Values\Sort;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->expectExceptionMessage('is not a model using the `Jackardios\EsScoutDriver\Searchable` trait.');

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
    public function it_rejects_a_model_instance(): void
    {
        $this->expectException(\TypeError::class);

        ElasticQueryWizard::for(new TestModel);
    }

    #[Test]
    public function it_rejects_a_schema_of_another_model(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return GeoModel::class;
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('describes '.GeoModel::class.', but the wizard queries '.TestModel::class.'.');

        ElasticQueryWizard::for(TestModel::class)->schema($schema);
    }

    #[Test]
    public function the_constructor_rejects_a_schema_of_another_model(): void
    {
        $schema = new class extends ResourceSchema
        {
            public function model(): string
            {
                return GeoModel::class;
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('describes '.GeoModel::class.', but the wizard queries '.TestModel::class.'.');

        new ElasticQueryWizard(TestModel::class, null, null, $schema);
    }

    /**
     * @return iterable<string, array{\Closure(): AbstractFilter}>
     */
    public static function filtersWithoutBooleans(): iterable
    {
        yield 'match' => [fn () => ElasticFilter::match('name')];
        yield 'range' => [fn () => ElasticFilter::range('price')];
        yield 'dateRange' => [fn () => ElasticFilter::dateRange('created_at')];
        yield 'geoDistance' => [fn () => ElasticFilter::geoDistance('location')];
        yield 'geoBoundingBox' => [fn () => ElasticFilter::geoBoundingBox('location')];
        yield 'geoShape' => [fn () => ElasticFilter::geoShape('area')];
        yield 'ids' => [fn () => ElasticFilter::ids('id')];
        yield 'trashed' => [fn () => ElasticFilter::trashed()];
        yield 'fuzzy' => [fn () => ElasticFilter::fuzzy('name')];
        yield 'matchPhrase' => [fn () => ElasticFilter::matchPhrase('name')];
        yield 'matchPhrasePrefix' => [fn () => ElasticFilter::matchPhrasePrefix('name')];
        yield 'multiMatch' => [fn () => ElasticFilter::multiMatch('search', ['name', 'category'])];
        yield 'prefix' => [fn () => ElasticFilter::prefix('name')];
        yield 'regexp' => [fn () => ElasticFilter::regexp('name')];
        yield 'wildcard' => [fn () => ElasticFilter::wildcard('name')];
        yield 'queryString' => [fn () => ElasticFilter::queryString('name')];
        yield 'simpleQueryString' => [fn () => ElasticFilter::simpleQueryString('name')];
        yield 'moreLikeThis' => [fn () => ElasticFilter::moreLikeThis('similar', ['name'])];
        yield 'bool group' => [fn () => ElasticGroup::bool('should')];
        yield 'nested group' => [fn () => ElasticGroup::nested('comments')];
    }

    #[Test]
    #[DataProvider('filtersWithoutBooleans')]
    public function as_boolean_throws_on_filters_that_do_not_take_booleans(\Closure $make): void
    {
        $filter = $make();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches(sprintf('/^Filter (group )?`%s`.*, so asBoolean\(\)/', preg_quote($filter->getName(), '/')));

        $filter->asBoolean();
    }

    #[Test]
    public function as_boolean_is_accepted_by_filters_that_take_booleans(): void
    {
        foreach ([ElasticFilter::term('active'), ElasticFilter::exists('active'), ElasticFilter::null('deleted_at')] as $filter) {
            $this->assertSame($filter, $filter->asBoolean());
        }
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
    public function the_search_builder_and_model_callbacks_take_any_callable(): void
    {
        $minScore = new class
        {
            public function __invoke(SearchBuilder $builder): void
            {
                $builder->minScore(0.5);
            }
        };

        $wizard = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder($minScore)
            ->modifyQuery([$this, 'ignoreQuery'])
            ->modifyModels(static fn (Collection $models): Collection => $models);
        $wizard->build();

        $this->assertSame(0.5, $wizard->getSubject()->toArray()['body']['min_score']);
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  array<string, mixed>  $rawResult
     */
    public function ignoreQuery(Builder $builder, array $rawResult): void {}

    #[Test]
    public function bool_query_after_build_locks_configuration_changes(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->build();

        $wizard->boolQuery();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The wizard cannot be reconfigured after its built search was changed through the wizard');

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
        $this->expectExceptionMessage('The wizard cannot be reconfigured after its built search was changed through the wizard');

        $clone->allowedFilters(TermFilter::make('name'));
    }

    #[Test]
    public function modify_query_after_build_throws_logic_exception(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->build();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('modifyQuery() cannot be called after build()');

        $wizard->modifyQuery(static function (Builder $builder, array $rawResult): void {});
    }

    #[Test]
    public function modify_models_after_build_throws_logic_exception(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->build();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('modifyModels() cannot be called after build()');

        $wizard->modifyModels(static function (Collection $collection): Collection {
            return $collection;
        });
    }

    #[Test]
    public function a_search_joined_through_the_proxy_builds(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)->join(GeoModel::class);

        $wizard->build();

        $queryModifiers = (new \ReflectionProperty(SearchBuilder::class, 'queryModifiers'))->getValue($wizard->getSubject());
        $this->assertSame([(new TestModel)->searchableAs()], array_keys($queryModifiers));
    }

    /**
     * @return array<string, array{\Closure(ElasticQueryWizard): mixed}>
     */
    public static function buildCallbackRegistrations(): array
    {
        return [
            'modifyQuery' => [static fn (ElasticQueryWizard $wizard) => $wizard->modifyQuery(static function (Builder $builder, array $rawResult): void {})],
            'modifyModels' => [static fn (ElasticQueryWizard $wizard) => $wizard->modifyModels(static fn (Collection $collection): Collection => $collection)],
            'tapSearchBuilder' => [static fn (ElasticQueryWizard $wizard) => $wizard->tapSearchBuilder(static fn (SearchBuilder $builder) => $builder)],
            'a fluent search builder method' => [static fn (ElasticQueryWizard $wizard) => $wizard->size(5)],
        ];
    }

    #[Test]
    #[DataProvider('buildCallbackRegistrations')]
    public function a_callback_registered_while_the_wizard_builds_throws(\Closure $register): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->tap(static fn () => $register($wizard));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The wizard cannot be reconfigured while it builds.');

        $wizard->build();
    }

    #[Test]
    public function bool_query_throws_the_error_of_the_build_and_leaves_the_wizard_configurable(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['nope' => 'x'])->allowedFilters('category');

        try {
            $wizard->boolQuery();
            $this->fail('The build accepted a filter that is not allowed.');
        } catch (InvalidFilterQuery) {
        }

        $body = $wizard->allowedFilters('category', 'nope')->build()->toArray()['body'];

        $this->assertSame([['term' => ['nope' => ['value' => 'x']]]], $body['query']['bool']['filter']);
    }

    #[Test]
    public function a_build_that_failed_can_be_retried_when_the_bool_query_was_not_changed(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['nope' => 'x'])->allowedFilters('category');

        try {
            $wizard->build();
            $this->fail('The build accepted a filter that is not allowed.');
        } catch (InvalidFilterQuery) {
        }

        $body = $wizard->allowedFilters('category', 'nope')->build()->toArray()['body'];

        $this->assertSame([['term' => ['nope' => ['value' => 'x']]]], $body['query']['bool']['filter']);
    }

    #[Test]
    public function the_bool_query_read_through_the_proxy_locks_configuration_changes(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['category' => 'a'])->allowedFilters('category');
        $boolQuery = $wizard->getBoolQuery();
        $this->assertInstanceOf(BoolQuery::class, $boolQuery);
        $boolQuery->addFilter(ElasticQuery::term('tenant_id', 7));

        $this->assertSame(
            [['term' => ['category' => ['value' => 'a']]], ['term' => ['tenant_id' => ['value' => 7]]]],
            $this->getFilterQueries($wizard->getSubject()->boolQuery())
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The wizard cannot be reconfigured after its built search was changed through the wizard');

        $wizard->allowedSorts('id');
    }

    #[Test]
    public function an_unknown_method_throws_a_bad_method_call_exception(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Call to undefined method Jackardios\\ElasticQueryWizard\\ElasticQueryWizard::noSuchMethod()');

        ElasticQueryWizard::for(TestModel::class)->noSuchMethod();
    }

    #[Test]
    public function a_declarative_method_called_after_the_build_changes_the_built_search(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)->allowedSorts('name');
        $builder = $wizard->build();

        $this->assertSame($wizard, $wizard->size(7));
        $this->assertSame(7, $builder->getSize());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The wizard cannot be reconfigured after its built search was changed through the wizard');

        $wizard->allowedSorts('id');
    }

    #[Test]
    public function a_declarative_method_called_before_the_build_survives_a_rebuild(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)->trackTotalHits(true);
        $wizard->build();

        $wizard->allowedSorts('name');

        $this->assertTrue($wizard->build()->getTrackTotalHits());
    }

    #[Test]
    public function when_and_unless_are_applied_at_the_build(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['category' => 'a']);

        $this->assertSame($wizard, $wizard->when(true, static fn (SearchBuilder $builder) => $builder->size(3)));
        $this->assertSame($wizard, $wizard->unless(false, static fn (SearchBuilder $builder) => $builder->from(6)));
        $this->assertNull($wizard->getSubject()->getSize());

        $body = $wizard->allowedFilters('category')->build()->toArray()['body'];

        $this->assertSame(3, $body['size']);
        $this->assertSame(6, $body['from']);
        $this->assertSame([['term' => ['category' => ['value' => 'a']]]], $body['query']['bool']['filter']);
    }

    #[Test]
    public function when_after_the_build_changes_the_built_search(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $builder = $wizard->build();

        $this->assertSame($wizard, $wizard->when(true, static fn (SearchBuilder $builder) => $builder->size(3)));
        $this->assertSame(3, $builder->getSize());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The wizard cannot be reconfigured after its built search was changed through the wizard');

        $wizard->allowedSorts('name');
    }

    #[Test]
    public function when_without_a_callback_throws(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Pass a callback to Jackardios\\ElasticQueryWizard\\ElasticQueryWizard::when()');

        ElasticQueryWizard::for(TestModel::class)->when(true);
    }

    #[Test]
    public function a_proxy_call_after_the_build_is_not_kept_for_later_builds(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->size(3);
        $wizard->build();
        $wizard->from(6);
        $wizard->tapSearchBuilder(static fn (SearchBuilder $builder) => $builder->minScore(0.5));

        $this->assertCount(1, (new \ReflectionProperty(ElasticQueryWizard::class, 'searchBuilderModifiers'))->getValue($wizard));
        $this->assertSame(0.5, $wizard->getSubject()->toArray()['body']['min_score']);
    }

    #[Test]
    public function a_search_builder_macro_runs_on_the_built_search(): void
    {
        SearchBuilder::macro('wizardTestPageOfFour', function (): SearchBuilder {
            /** @var SearchBuilder $this */
            return $this->size(4);
        });
        SearchBuilder::macro('wizardTestSize', function (): ?int {
            /** @var SearchBuilder $this */
            return $this->getSize();
        });

        try {
            $wizard = $this->createElasticWizardWithFilters(['category' => 'a'])->allowedFilters('category');

            $this->assertSame($wizard, $wizard->wizardTestPageOfFour());
            $this->assertSame(4, $wizard->wizardTestSize());
            $this->assertSame([['term' => ['category' => ['value' => 'a']]]], $this->getFilterQueries($wizard->getSubject()->boolQuery()));

            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('The wizard cannot be reconfigured after its built search was changed through the wizard');

            $wizard->allowedSorts('name');
        } finally {
            SearchBuilder::flushMacros();
        }
    }

    #[Test]
    public function a_non_public_search_builder_method_throws_before_the_build(): void
    {
        $privateMethods = (new \ReflectionClass(SearchBuilder::class))->getMethods(\ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_PROTECTED);
        $this->assertNotEmpty($privateMethods);
        $name = $privateMethods[0]->getName();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Call to undefined method Jackardios\\ElasticQueryWizard\\ElasticQueryWizard::{$name}()");

        $this->createElasticWizardWithFilters(['not_allowed' => 'x'])->{$name}();
    }

    #[Test]
    public function a_getter_with_a_union_return_type_returns_the_builder_value_and_keeps_the_wizard_configurable(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)->trackTotalHits(100);

        $this->assertSame(100, $wizard->getTrackTotalHits());

        $wizard->allowedSorts('name');

        $this->assertSame(100, $wizard->build()->getTrackTotalHits());
    }
}
