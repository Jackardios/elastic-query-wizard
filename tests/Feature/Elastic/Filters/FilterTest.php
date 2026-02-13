<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class FilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(5)->create();
    }

    /** @test */
    public function it_can_filter_models_by_term_property_by_default(): void
    {
        $expectedModel = TestModel::factory()->create(['category' => 'some-testing-category']);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => $expectedModel->category,
            ])
            ->allowedFilters('category')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->category, $modelsResult->first()->category);
    }

    /** @test */
    public function it_can_filter_models_by_an_array_as_filter_value(): void
    {
        $expectedModel = TestModel::factory()->create(['category' => 'some-testing-category']);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => [$expectedModel->category],
            ])
            ->allowedFilters('category')
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
            ->allowedFilters('category')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
    }

    /** @test */
    public function it_can_filter_a_custom_base_query_with_select(): void
    {
        $model = TestModel::factory()->create(['category' => 'unique-test-category-xyz']);
        $expectedModel = TestModel::query()->select(['id', 'category'])->find($model->id);

        $modelResult = $this->createElasticWizardWithFilters(['category' => $expectedModel->category])
            ->modifyQuery(function (Builder $query) {
                return $query->select('id', 'category');
            })
            ->allowedFilters('category', 'id')
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertModelsAttributesEqual($expectedModel, $modelResult);
    }

    /** @test */
    public function it_can_filter_results_based_on_the_existence_of_a_property_in_an_array(): void
    {
        $expectedModels = collect([$this->models[0], $this->models[1]]);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'id' => "{$expectedModels[0]->id},{$expectedModels[1]->id}",
            ])
            ->allowedFilters('id')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing($expectedModels->pluck('id')->all(), $modelsResult->pluck('id')->all());
    }

    /**
     * Note: Eloquent filters (ExactFilter, ScopeFilter) are not compatible with ElasticQueryWizard.
     * They expect Eloquent\Builder but ElasticQueryWizard uses SearchBuilder.
     * Use Elastic-specific filters (TermFilter, MatchFilter, etc.) instead.
     */

    /** @test */
    public function it_can_filter_results_by_a_custom_filter_class(): void
    {
        $testModel = $this->models->first();

        $filterClass = new class ('custom_name') extends AbstractElasticFilter {
            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public static function make(string $property, ?string $alias = null): static
            {
                return new static($property, $alias);
            }

            public function getType(): string
            {
                return 'custom';
            }

            protected function getDefaultClause(): \Jackardios\ElasticQueryWizard\Enums\BoolClause
            {
                return \Jackardios\ElasticQueryWizard\Enums\BoolClause::MUST;
            }

            public function buildQuery(mixed $value): ?\Jackardios\EsScoutDriver\Query\QueryInterface
            {
                return Query::match('name', $value);
            }
        };

        $modelResult = $this
            ->createElasticWizardWithFilters([
                'custom_name' => $testModel->name,
            ])
            ->allowedFilters($filterClass)
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertEquals($testModel->id, $modelResult->id);
    }

    /** @test */
    public function it_can_allow_multiple_filters(): void
    {
        $expectedModels = TestModel::factory()->count(2)->create(['name' => 'abcdef']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'abcdef',
            ])
            ->allowedFilters(MatchFilter::make('name'), 'id')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing($expectedModels->pluck('id')->all(), $modelsResult->pluck('id')->all());
    }

    /** @test */
    public function it_can_allow_multiple_filters_as_an_array(): void
    {
        $expectedModels = TestModel::factory()->count(2)->create(['name' => 'abcdef']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'abcdef',
            ])
            ->allowedFilters([MatchFilter::make('name'), 'id'])
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing($expectedModels->pluck('id')->all(), $modelsResult->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_by_multiple_filters(): void
    {
        $expectedModels = TestModel::factory()->count(2)->create(['name' => 'abcdef']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'abcdef',
                'id' => "1,{$expectedModels[0]->id}",
            ])
            ->allowedFilters(MatchFilter::make('name'), 'id')
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
            ->allowedFilters('id')
            ->build();
    }

    /** @test */
    public function it_can_create_a_custom_filter_with_an_instantiated_filter(): void
    {
        $customFilter = new class ('*') extends AbstractElasticFilter {
            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public static function make(string $property, ?string $alias = null): static
            {
                return new static($property, $alias);
            }

            public function getType(): string
            {
                return 'custom';
            }

            public function buildQuery(mixed $value): ?\Jackardios\EsScoutDriver\Query\QueryInterface
            {
                return null;
            }
        };

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                '*' => '*',
            ])
            ->allowedFilters('name', $customFilter)
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
        $filter = TermFilter::make('property_name');

        $this->assertEquals($filter->getName(), $filter->getProperty());
    }

    /** @test */
    public function it_resolves_queries_using_property_column_name(): void
    {
        $filter = TermFilter::make('category', 'tag');

        $expectedModel = TestModel::factory()->create(['category' => 'abcdef']);

        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'tag' => 'abcdef',
            ])
            ->allowedFilters($filter)
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
        $filter = TermFilter::make('is_visible');

        $modelsResult = $this
            ->createElasticWizardWithFilters(['is_visible' => 'false'])
            ->allowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
        $this->assertGreaterThan(0, TestModel::all()->count());
    }

    /** @test */
    public function it_can_add_parameters_to_filters(): void
    {
        TestModel::factory()->create(['name' => 'St.Petersburg']);
        TestModel::factory()->create(['name' => 'Noscow']);
        $expectedModel = TestModel::factory()->create(['name' => 'Moscow']);

        $filter = (MatchFilter::make('name'))->withParameters([
            'fuzziness' => 1,
        ]);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'name' => 'Mascow',
            ])
            ->allowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals([$expectedModel->id], $modelsResult->pluck('id')->all());
    }
}
