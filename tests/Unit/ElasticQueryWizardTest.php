<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Values\Sort;

/**
 * @group unit
 * @group wizard
 */
class ElasticQueryWizardTest extends UnitTestCase
{
    /** @test */
    public function it_throws_for_non_class_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$subject must be a model that uses `Jackardios\EsScoutDriver\Searchable` trait');

        ElasticQueryWizard::for('not a class name');
    }

    /** @test */
    public function it_throws_for_non_searchable_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ElasticQueryWizard::for(\Illuminate\Database\Eloquent\Model::class);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
    public function it_creates_wizard_from_model_instance(): void
    {
        $model = new TestModel();
        $wizard = ElasticQueryWizard::for($model);

        $this->assertInstanceOf(ElasticQueryWizard::class, $wizard);
    }

    /** @test */
    public function it_returns_subject_as_search_builder(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);

        $this->assertInstanceOf(\Jackardios\EsScoutDriver\Search\SearchBuilder::class, $wizard->getSubject());
    }

    /** @test */
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

    /** @test */
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

    /** @test */
    public function it_applies_tap_search_builder_callback(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)
            ->tapSearchBuilder(fn ($builder) => $builder->minScore(0.75));

        $wizard->build();

        $params = $wizard->getSubject()->toArray();
        $this->assertSame(0.75, $params['body']['min_score']);
    }

    /** @test */
    public function bool_query_after_build_locks_configuration_changes(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class);
        $wizard->build();

        $wizard->boolQuery();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot modify query wizard configuration after calling query builder methods.');

        $wizard->allowedFilters(TermFilter::make('name'));
    }
}
