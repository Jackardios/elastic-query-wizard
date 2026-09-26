<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\AppendModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\MorphModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * How the models a search resolves are loaded and shaped: includes, sparse
 * fieldsets and appends applied to the Eloquent query and the loaded models.
 */
#[Group('elastic')]
#[Group('fields')]
class ModelShapeTest extends TestCase
{
    #[Test]
    public function a_relation_fieldset_runs_after_the_eager_load_constraint_of_modify_query(): void
    {
        $model = TestModel::factory()->create();
        $model->relatedModels()->create(['name' => 'public']);
        $model->relatedModels()->create(['name' => 'SECRET']);

        $loaded = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => ['relatedModels' => 'name'],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('name', 'relatedModels.name')
            ->modifyQuery(fn (Builder $query) => $query->with([
                'relatedModels' => fn ($query) => $query->where('name', '!=', 'SECRET'),
            ]))
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertSame([['name' => 'public']], $loaded->relatedModels->toArray());
    }

    #[Test]
    public function count_and_exists_includes_survive_a_root_fieldset(): void
    {
        $model = TestModel::factory()->create();
        $model->relatedModels()->create(['name' => 'one']);
        $model->relatedModels()->create(['name' => 'two']);

        $loaded = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModelsCount,relatedModelsExists',
                'fields' => ['testModel' => 'id,name'],
            ])
            ->allowedIncludes('relatedModelsCount', 'relatedModelsExists')
            ->allowedFields('id', 'name')
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertSame(
            ['id' => $model->id, 'name' => $model->name, 'related_models_count' => 2, 'related_models_exists' => true],
            $loaded->toArray()
        );
    }

    #[Test]
    public function a_select_added_in_modify_query_is_kept_under_a_root_fieldset_and_hidden(): void
    {
        $model = TestModel::factory()->create(['name' => 'Alice']);

        $loaded = $this
            ->createElasticWizardWithFields(['testModel' => 'name'])
            ->allowedFields('name')
            ->modifyQuery(fn (Builder $query) => $query->addSelect(DB::raw('upper(name) as shouted')))
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertSame($model->id, $loaded->getKey());
        $this->assertSame('ALICE', $loaded->getAttribute('shouted'));
        $this->assertSame(['name' => 'Alice'], $loaded->toArray());
    }

    #[Test]
    public function root_appends_read_every_column_under_a_root_fieldset(): void
    {
        AppendModel::factory()->create(['firstname' => 'John', 'lastname' => 'Doe']);

        $loaded = $this
            ->createElasticWizardFromQuery([
                'fields' => ['appendModel' => 'id'],
                'append' => 'fullname',
            ], AppendModel::class)
            ->allowedFields('id')
            ->allowedAppends('fullname')
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertSame(['id', 'fullname'], array_keys($loaded->toArray()));
        $this->assertSame('John Doe', $loaded->toArray()['fullname']);
    }

    #[Test]
    public function a_relation_fieldset_keeps_the_default_eager_loads_of_the_related_model(): void
    {
        $model = TestModel::factory()->create();
        $related = $model->relatedModelsWithDefaults()->create(['name' => 'related']);
        $related->nestedRelatedModels()->create(['name' => 'nested']);

        $loaded = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModelsWithDefaults',
                'fields' => ['relatedModelsWithDefaults' => 'name'],
            ])
            ->allowedIncludes('relatedModelsWithDefaults')
            ->allowedFields('name', 'relatedModelsWithDefaults.name')
            ->build()
            ->execute()
            ->models()
            ->first();

        $relatedModel = $loaded->relatedModelsWithDefaults->first();
        $this->assertSame(['name', 'nested_related_models'], array_keys($relatedModel->toArray()));
        $this->assertSame(1, $relatedModel->getAttribute('nested_related_models_count'));
        $this->assertSame(['nested'], $relatedModel->nestedRelatedModels->pluck('name')->all());
    }

    #[Test]
    public function a_root_fieldset_selects_the_keys_of_eager_loads_registered_in_modify_query(): void
    {
        $parent = TestModel::factory()->create();
        $parent->morphModels()->create(['name' => 'child']);

        $loaded = $this
            ->createElasticWizardWithFields(['morphModel' => 'name'], MorphModel::class)
            ->allowedFields('name')
            ->modifyQuery(fn (Builder $query) => $query->with('parent'))
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertTrue($loaded->relationLoaded('parent'));
        $this->assertSame($parent->id, $loaded->parent?->getKey());
        $this->assertSame('child', $loaded->toArray()['name']);
        $this->assertArrayNotHasKey('parent_id', $loaded->toArray());
    }

    #[Test]
    public function the_primary_and_scout_keys_are_selected_and_hidden_unless_requested(): void
    {
        $model = TestModel::factory()->create();

        $hidden = $this->createElasticWizardWithFields(['testModel' => 'name'])
            ->allowedFields('id', 'name')
            ->build()
            ->execute()
            ->models()
            ->first();
        $requested = $this->createElasticWizardWithFields(['testModel' => 'id,name'])
            ->allowedFields('id', 'name')
            ->build()
            ->execute()
            ->models()
            ->first();

        $this->assertSame($model->id, $hidden->getKey());
        $this->assertSame(['name'], array_keys($hidden->toArray()));
        $this->assertSame(['id', 'name'], array_keys($requested->toArray()));
    }

    #[Test]
    public function a_wildcard_fieldset_does_not_accept_a_case_variant_of_a_hidden_attribute(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this->createElasticWizardWithFields(['hiddenCategoryModel' => 'id,CATEGORY'], HiddenCategoryModel::class)
            ->allowedFields('*')
            ->build();
    }

    #[Test]
    public function a_wildcard_append_that_is_not_an_accessor_is_rejected(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this->createElasticWizardWithAppends('not_an_accessor')
            ->allowedAppends('*')
            ->build();
    }

    #[Test]
    public function a_build_that_failed_validation_fails_again_on_retry(): void
    {
        $wizard = $this->createElasticWizardWithFields(['testModel' => 'secret'])->allowedFields('id');

        foreach ([1, 2] as $attempt) {
            try {
                $wizard->build();
                $this->fail("Build {$attempt} passed validation.");
            } catch (InvalidFieldQuery) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function a_built_search_keeps_its_shape_when_the_wizard_is_reconfigured(): void
    {
        TestModel::factory()->create();
        $wizard = $this->createElasticWizardWithFields(['testModel' => 'name'])->allowedFields('name');

        $search = $wizard->build();
        $wizard->allowedFields('id', 'name', 'category');

        $this->assertSame(['name'], array_keys($search->execute()->models()->first()->toArray()));
    }

    #[Test]
    public function a_clone_keeps_the_shape_of_the_build_it_was_cloned_from(): void
    {
        TestModel::factory()->create();
        $wizard = $this->createElasticWizardWithFields(['testModel' => 'name'])->allowedFields('name', 'category');
        $wizard->build();

        $clone = clone $wizard;
        $wizard->allowedFields('id', 'name', 'category');
        $wizard->build();

        $this->assertSame(['name'], array_keys($clone->execute()->models()->first()->toArray()));
    }

    #[Test]
    public function post_processing_applies_the_root_fieldset_to_an_array_and_a_base_collection(): void
    {
        TestModel::factory()->count(2)->create();
        $wizard = $this->createElasticWizardWithFields(['testModel' => 'name'])->allowedFields('name');
        $keysOf = fn (iterable $models): array => array_map(
            fn (Model $model): array => array_keys($model->toArray()),
            is_array($models) ? $models : $models->all()
        );

        $this->assertSame([['name'], ['name']], $keysOf($wizard->applyPostProcessingTo(TestModel::query()->get()->all())));
        $this->assertSame([['name'], ['name']], $keysOf($wizard->applyPostProcessingTo(new Collection(TestModel::query()->get()->all()))));
    }
}

class HiddenCategoryModel extends TestModel
{
    protected $table = 'test_models';

    protected $hidden = ['category'];
}
