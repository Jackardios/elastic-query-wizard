<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Includes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\ElasticQueryWizard\Includes\AbstractElasticInclude;
use Jackardios\QueryWizard\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\MorphModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Exceptions\InvalidIncludeQuery;
use ReflectionClass;

/**
 * @group elastic
 * @group include
 * @group elastic-include
 */
class IncludeTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(5)->create();

        $this->models->each(function (TestModel $model) {
            $model
                ->relatedModels()->create(['name' => 'Test'])
                ->nestedRelatedModels()->create(['name' => 'Test']);

            $model->morphModels()->create(['name' => 'Test']);

            $model->relatedThroughPivotModels()->create([
                'id' => $model->id + 1,
                'name' => 'Test',
            ]);
        });
    }

    /** @test */
    public function it_does_not_require_includes(): void
    {
        $models = $this->createElasticWizardFromQuery()
            ->allowedIncludes('relatedModels')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(TestModel::count(), $models);
    }

    /** @test */
    public function it_can_handle_empty_includes(): void
    {
        $models = $this->createElasticWizardFromQuery()
            ->allowedIncludes([
                null,
                [],
                '',
            ])
            ->build()
            ->execute()
            ->models();

        $this->assertCount(TestModel::count(), $models);
    }

    /** @test */
    public function it_can_include_model_relations(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels')
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_include_model_relations_by_alias(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('include-alias')
            ->allowedIncludes(RelationshipInclude::make('relatedModels', 'include-alias'))
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_include_an_includes_count(): void
    {
        $model = $this
            ->createElasticWizardWithIncludes('relatedModelsCount')
            ->allowedIncludes('relatedModelsCount')
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertNotNull($model->related_models_count);
    }

    /** @test */
    public function allowing_an_include_also_allows_the_include_count(): void
    {
        // In v3, count includes must be explicitly allowed
        // Allowing 'relatedModels' does NOT automatically allow 'relatedModelsCount'
        $model = $this
            ->createElasticWizardWithIncludes('relatedModelsCount')
            ->allowedIncludes('relatedModels', 'relatedModelsCount')
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertNotNull($model->related_models_count);
    }

    /** @test */
    public function it_can_include_nested_model_relations(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->build()
            ->execute()
            ->models();

        $models->each(function (Model $model) {
            $this->assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
        });
    }

    /** @test */
    public function it_can_include_nested_model_relations_by_alias(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('nested-alias')
            ->allowedIncludes(
                RelationshipInclude::make('relatedModels.nestedRelatedModels', 'nested-alias')
            )
            ->build()
            ->execute()
            ->models();

        $models->each(function (TestModel $model) {
            $this->assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
        });
    }

    /** @test */
    public function it_can_include_model_relations_from_nested_model_relations(): void
    {
        // In v3, requesting 'relatedModels' requires it to be explicitly allowed
        // Allowing 'relatedModels.nestedRelatedModels' doesn't implicitly allow 'relatedModels'
        $models = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels.nestedRelatedModels', 'relatedModels')
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_include_both_parent_and_nested(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('relatedModels,relatedModels.nestedRelatedModels')
            ->allowedIncludes('relatedModels', 'relatedModels.nestedRelatedModels')
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'relatedModels');
        $models->each(function (Model $model) {
            $this->assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
        });
    }

    /** @test */
    public function it_allows_the_include_count_for_the_first_level_of_nested_includes(): void
    {
        // In v3, count includes must be explicitly allowed
        $model = $this
            ->createElasticWizardWithIncludes('relatedModelsCount')
            ->allowedIncludes('relatedModels.nestedRelatedModels', 'relatedModelsCount')
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertNotNull($model->related_models_count);
    }

    /** @test */
    public function it_does_not_allow_nested_related_models_count_when_only_nested_include_is_allowed(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createElasticWizardWithIncludes('nestedRelatedModelsCount')
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->build()
            ->execute()
            ->models()
            ->first();
    }

    /** @test */
    public function it_does_not_allow_nested_count_via_dotted_path_when_only_nested_include_is_allowed(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createElasticWizardWithIncludes('related-models.nestedRelatedModelsCount')
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->build()
            ->execute()
            ->models()
            ->first();
    }

    /** @test */
    public function it_can_include_morph_model_relations(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('morphModels')
            ->allowedIncludes('morphModels')
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'morphModels');
    }

    /** @test */
    public function it_can_include_reverse_morph_model_relations(): void
    {
        $models = $this->createElasticWizardWithIncludes('parent', MorphModel::class)
            ->allowedIncludes('parent')
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'parent');
    }

    /** @test */
    public function it_can_include_camel_case_includes(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels')
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_include_models_on_an_empty_collection(): void
    {
        TestModel::query()->delete();

        $models = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $models);
    }

    /** @test */
    public function it_guards_against_invalid_includes(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createElasticWizardWithIncludes('random-model')
            ->allowedIncludes('relatedModels')
            ->build();
    }

    /** @test */
    public function it_throws_exception_for_nested_not_allowed_include(): void
    {
        $this->expectException(InvalidIncludeQuery::class);

        $this
            ->createElasticWizardWithIncludes('relatedModels.nestedRelatedModels')
            ->allowedIncludes('relatedModels')
            ->build();
    }

    /** @test */
    public function it_can_allow_multiple_includes(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels', 'otherRelatedModels')
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_allow_multiple_includes_as_an_array(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes(['relatedModels', 'otherRelatedModels'])
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_remove_duplicate_includes_from_nested_includes(): void
    {
        $wizard = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes('relatedModels.nestedRelatedModels', 'relatedModels');

        // Access the wizard's allowedIncludes property - it contains raw values before building
        $property = (new ReflectionClass($wizard))->getProperty('allowedIncludes');
        $rawValues = $property->getValue($wizard);

        // allowedIncludes contains the raw string/object values before normalization
        $includeNames = array_map(
            fn($include) => is_string($include) ? $include : $include->getName(),
            $rawValues
        );

        $this->assertContains('relatedModels', $includeNames);
        $this->assertContains('relatedModels.nestedRelatedModels', $includeNames);
    }

    /** @test */
    public function it_can_include_multiple_model_relations(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('relatedModels,otherRelatedModels')
            ->allowedIncludes(['relatedModels', 'otherRelatedModels'])
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'relatedModels');
        $this->assertRelationLoaded($models, 'otherRelatedModels');
    }

    /** @test */
    public function it_can_query_included_many_to_many_relationships(): void
    {
        DB::enableQueryLog();

        $this
            ->createElasticWizardWithIncludes('relatedThroughPivotModels')
            ->allowedIncludes('relatedThroughPivotModels')
            ->build()
            ->execute()
            ->models();

        // Based on the following query: TestModel::with('relatedThroughPivotModels')->get();
        // Without where-clause as that differs per Laravel version
        //dump(DB::getQueryLog());
        $this->assertQueryLogContains('select `related_through_pivot_models`.*, `pivot_models`.`test_model_id` as `pivot_test_model_id`, `pivot_models`.`related_through_pivot_model_id` as `pivot_related_through_pivot_model_id` from `related_through_pivot_models` inner join `pivot_models` on `related_through_pivot_models`.`id` = `pivot_models`.`related_through_pivot_model_id` where `pivot_models`.`test_model_id` in (1, 2, 3, 4, 5)');
    }

    /** @test */
    public function it_returns_correct_id_when_including_many_to_many_relationship(): void
    {
        $models = $this
            ->createElasticWizardWithIncludes('relatedThroughPivotModels')
            ->allowedIncludes('relatedThroughPivotModels')
            ->build()
            ->execute()
            ->models();

        $relatedModel = $models->first()->relatedThroughPivotModels->first();

        $this->assertEquals($relatedModel->id, $relatedModel->pivot->related_through_pivot_model_id);
    }

    /** @test */
    public function an_invalid_include_query_exception_contains_the_unknown_and_allowed_includes(): void
    {
        $exception = new InvalidIncludeQuery(collect(['unknown include']), collect(['allowed include']));

        $this->assertEquals(['unknown include'], $exception->unknownIncludes->all());
        $this->assertEquals(['allowed include'], $exception->allowedIncludes->all());
    }

    /** @test */
    public function it_can_alias_multiple_allowed_includes(): void
    {
        $models = $this->createElasticWizardWithIncludes('relatedModelsCount,relationShipAlias')
            ->allowedIncludes([
                CountInclude::make('relatedModels')->alias('relatedModelsCount'),
                RelationshipInclude::make('otherRelatedModels', 'relationShipAlias'),
            ])
            ->build()
            ->execute()
            ->models();

        $this->assertRelationLoaded($models, 'otherRelatedModels');
        $models->each(function ($model) {
            $this->assertNotNull($model->related_models_count);
        });
    }

    /** @test */
    public function it_can_include_custom_include_class(): void
    {
        $includeClass = new class('relatedModels') extends AbstractElasticInclude {
            public function __construct(string $relation, ?string $alias = null)
            {
                parent::__construct($relation, $alias);
            }

            public static function make(string $relation, ?string $alias = null): static
            {
                return new static($relation, $alias);
            }

            public function getType(): string
            {
                return 'custom';
            }

            public function handleEloquent(Builder $eloquentBuilder): void
            {
                $eloquentBuilder->withCount($this->getRelation());
            }
        };

        $modelResult = $this
            ->createElasticWizardWithIncludes('relatedModels')
            ->allowedIncludes($includeClass)
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertNotNull($modelResult->related_models_count);
    }

    /** @test */
    public function it_can_include_custom_include_class_by_alias(): void
    {
        $includeClass = (new class('relatedModels') extends AbstractElasticInclude {
            public function __construct(string $relation, ?string $alias = null)
            {
                parent::__construct($relation, $alias);
            }

            public static function make(string $relation, ?string $alias = null): static
            {
                return new static($relation, $alias);
            }

            public function getType(): string
            {
                return 'custom';
            }

            public function handleEloquent(Builder $eloquentBuilder): void
            {
                $eloquentBuilder->withCount($this->getRelation());
            }
        })->alias('relatedModelsCount');

        $modelResult = $this
            ->createElasticWizardWithIncludes('relatedModelsCount')
            ->allowedIncludes($includeClass)
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertNotNull($modelResult->related_models_count);
    }

    /** @test */
    public function it_can_include_a_custom_base_query_with_select(): void
    {
        $modelResult = $this->createElasticWizardWithIncludes('relatedModelsCount')
            ->modifyQuery(function(Builder $query) {
                return $query->select('id', 'name');
            })
            ->allowedIncludes(CountInclude::make('relatedModels')->alias('relatedModelsCount'))
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertNotNull($modelResult->related_models_count);
        $this->assertEqualsCanonicalizing(['id', 'name', 'related_models_count'], array_keys($modelResult->getAttributes()));
    }

    protected function assertRelationLoaded(Collection $collection, string $relation): void
    {
        $hasModelWithoutRelationLoaded = $collection
            ->contains(function (Model $model) use ($relation) {
                return ! $model->relationLoaded($relation);
            });

        $this->assertFalse($hasModelWithoutRelationLoaded, "The `{$relation}` relation was expected but not loaded.");
    }
}
