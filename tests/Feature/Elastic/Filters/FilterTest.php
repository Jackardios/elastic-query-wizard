<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Eloquent\Filters\ExactFilter;
use Jackardios\QueryWizard\Eloquent\Filters\ScopeFilter;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class FilterTest extends TestCase
{
    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_can_filter_models_by_term_property_by_default(): void
    {
        $expectedModel = factory(TestModel::class)->create(['category' => 'some-testing-category']);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => $expectedModel->category,
            ])
            ->setAllowedFilters('category')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->category, $modelsResult->first()->category);
    }

    /** @test */
    public function it_can_filter_models_by_an_array_as_filter_value(): void
    {
        $expectedModel = factory(TestModel::class)->create(['category' => 'some-testing-category']);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => [$expectedModel->category],
            ])
            ->setAllowedFilters('category')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->category, $modelsResult->first()->category);
    }

    /** @test */
    public function it_can_filter_models_and_return_an_empty_collection(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => 'non existing category',
            ])
            ->setAllowedFilters('category')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
    }

    /** @test */
    public function it_can_filter_a_custom_base_query_with_select(): void
    {
        $expectedModel = TestModel::query()
            ->select(['id', 'category'])
            ->find($this->models->random()->id);

        $modelResult = $this->createElasticWizardWithFilters(['category' => $expectedModel->category])
            ->addEloquentQueryCallback(function(Builder $query) {
                return $query->select('id', 'category');
            })
            ->setAllowedFilters('category', 'id')
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertModelsAttributesEqual($expectedModel, $modelResult);
    }

    /** @test */
    public function it_can_filter_results_based_on_the_existence_of_a_property_in_an_array(): void
    {
        $expectedModels = $this->models->random(2);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'id' => "{$expectedModels[0]->id},{$expectedModels[1]->id}",
            ])
            ->setAllowedFilters('id')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing($expectedModels->pluck('id')->all(), $modelsResult->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_results_by_eloquent_filter(): void
    {
        $expectedModel = factory(TestModel::class)->create(['category' => 'Some Testing Category']);

        $modelsResult = $this
            ->createElasticWizardWithFilters(['category' => 'Some Testing Category'])
            ->setAllowedFilters(new ExactFilter('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->id, $modelsResult->first()->id);
    }

    /** @test */
    public function it_can_filter_results_by_eloquent_scope(): void
    {
        $expectedModel = factory(TestModel::class)->create(['category' => 'Some Testing Category']);

        $modelsResult = $this
            ->createElasticWizardWithFilters(['categorized' => 'Some Testing Category'])
            ->setAllowedFilters(new ScopeFilter('categorized'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->id, $modelsResult->first()->id);
    }

    /** @test */
    public function it_can_filter_results_by_nested_relation_eloquent_scope(): void
    {
        $expectedModel = factory(TestModel::class)->create(['name' => 'John Testing Doe 234234']);
        $expectedModel->relatedModels()->create(['name' => 'John\'s Post']);

        $modelsResult = $this
            ->createElasticWizardWithFilters(['relatedModels.named' => 'John\'s Post'])
            ->setAllowedFilters(new ScopeFilter('relatedModels.named'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->id, $modelsResult->first()->id);
    }

    /** @test */
    public function it_can_filter_results_by_a_custom_filter_class(): void
    {
        $testModel = $this->models->first();

        $filterClass = new class('custom_name') extends ElasticFilter {
            public function handle($queryWizard, $queryBuilder, $value): void
            {
                $queryWizard->getRootBoolQuery()->must(Query::match()->field('name')->query($value));
            }
        };

        $modelResult = $this
            ->createElasticWizardWithFilters([
                'custom_name' => $testModel->name,
            ])
            ->setAllowedFilters($filterClass)
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertEquals($testModel->id, $modelResult->id);
    }

    /** @test */
    public function it_can_allow_multiple_filters(): void
    {
        $expectedModels = factory(TestModel::class, 2)->create(['name' => 'abcdef']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'abcdef',
            ])
            ->setAllowedFilters(new MatchFilter('name'), 'id')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing($expectedModels->pluck('id')->all(), $modelsResult->pluck('id')->all());
    }

    /** @test */
    public function it_can_allow_multiple_filters_as_an_array(): void
    {
        $expectedModels = factory(TestModel::class, 2)->create(['name' => 'abcdef']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'abcdef',
            ])
            ->setAllowedFilters([new MatchFilter('name'), 'id'])
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing($expectedModels->pluck('id')->all(), $modelsResult->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_by_multiple_filters(): void
    {
        $expectedModels = factory(TestModel::class, 2)->create(['name' => 'abcdef']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'abcdef',
                'id' => "1,{$expectedModels[0]->id}",
            ])
            ->setAllowedFilters(new MatchFilter('name'), 'id')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals([$expectedModels[0]->id], $modelsResult->pluck('id')->all());
    }

    /** @test */
    public function it_guards_against_invalid_filters(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters(['name' => 'John'])
            ->setAllowedFilters('id')
            ->build();
    }

    /** @test */
    public function it_can_create_a_custom_filter_with_an_instantiated_filter(): void
    {
        $customFilter = new class('*') extends ElasticFilter {
            public function handle($queryWizard, $queryBuilder, $value): void
            {
                //
            }
        };

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                '*' => '*',
            ])
            ->setAllowedFilters('name', $customFilter)
            ->build()
            ->execute()
            ->models();

        $this->assertNotEmpty($modelsResult);
    }

    /** @test */
    public function an_invalid_filter_query_exception_contains_the_unknown_and_allowed_filters(): void
    {
        $exception = new InvalidFilterQuery(collect(['unknown filter']), collect(['allowed filter']));

        $this->assertEquals(['unknown filter'], $exception->unknownFilters->all());
        $this->assertEquals(['allowed filter'], $exception->allowedFilters->all());
    }

    /** @test */
    public function it_sets_property_column_name_to_property_name_by_default(): void
    {
        $filter = new TermFilter('property_name');

        $this->assertEquals($filter->getName(), $filter->getPropertyName());
    }

    /** @test */
    public function it_resolves_queries_using_property_column_name(): void
    {
        $filter = new TermFilter('category', 'tag');

        $expectedModel = factory(TestModel::class)->create(['category' => 'abcdef']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'tag' => 'abcdef',
            ])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->category, $modelsResult->first()->category);
    }

    /** @test */
    public function it_can_filter_using_boolean_flags(): void
    {
        TestModel::query()->update(['is_visible' => true]);
        $filter = new TermFilter('is_visible');

        $modelsResult = $this
            ->createElasticWizardWithFilters(['is_visible' => 'false'])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
        $this->assertGreaterThan(0, TestModel::all()->count());
    }

    /** @test */
    public function it_can_add_parameters_to_filters(): void
    {
        factory(TestModel::class)->create(['name' => 'St.Petersburg']);
        factory(TestModel::class)->create(['name' => 'Noscow']);
        $expectedModel = factory(TestModel::class)->create(['name' => 'Moscow']);

        $filter = (new MatchFilter('name'))->withParameters([
            'fuzziness' => 1,
        ]);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'Mascow'
            ])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals([$expectedModel->id], $modelsResult->pluck('id')->all());
    }
}
