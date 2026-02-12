<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\AppendModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\SoftDeleteModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;

/**
 * @group elastic
 * @group wizard
 * @group elastic-wizard
 */
class ElasticQueryWizardTest extends TestCase
{
    use AssertsCollectionSorting;

    /** @test */
    public function it_can_not_be_given_a_string_that_is_not_a_class_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->expectExceptionMessage('$subject must be a model that uses `Jackardios\EsScoutDriver\Searchable` trait');

        ElasticQueryWizard::for('not a class name');
    }

    /** @test */
    public function it_can_query_soft_deletes(): void
    {
        Config::set('scout.soft_delete', true);

        $queryWizard = ElasticQueryWizard::for(SoftDeleteModel::class);

        $models = SoftDeleteModel::factory()->count(5)->create();

        $this->assertCount(5, $queryWizard->execute()->models());

        $models[0]->delete();

        // Reset the wizard to get fresh query
        $queryWizard = ElasticQueryWizard::for(SoftDeleteModel::class);
        $this->assertCount(4, $queryWizard->execute()->models());

        // With trashed
        $queryWizard = ElasticQueryWizard::for(SoftDeleteModel::class);
        $queryWizard->boolQuery()->withTrashed();
        $this->assertCount(5, $queryWizard->execute()->models());
    }

    // === Filter + Sort tests ===

    /** @test */
    public function it_can_apply_filter_and_sort_together(): void
    {
        TestModel::factory()->create(['name' => 'Alice', 'category' => 'test-category']);
        TestModel::factory()->create(['name' => 'Bob', 'category' => 'test-category']);
        TestModel::factory()->create(['name' => 'Charlie', 'category' => 'other-category']);

        $models = $this->createElasticWizardFromQuery([
            'filter' => ['category' => 'test-category'],
            'sort' => 'name',
        ])
            ->allowedFilters('category')
            ->allowedSorts(FieldSort::make('name.keyword', 'name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $models);
        $this->assertSortedAscending($models, 'name');
        $this->assertTrue($models->every(fn ($m) => $m->category === 'test-category'));
    }

    /** @test */
    public function it_can_apply_multiple_filters_and_sorts(): void
    {
        TestModel::factory()->create(['name' => 'Alice', 'category' => 'cat-a']);
        TestModel::factory()->create(['name' => 'Bob', 'category' => 'cat-a']);
        TestModel::factory()->create(['name' => 'Charlie', 'category' => 'cat-b']);

        $models = $this->createElasticWizardFromQuery([
            'filter' => ['category' => 'cat-a'],
            'sort' => '-name',
        ])
            ->allowedFilters(TermFilter::make('category'))
            ->allowedSorts(FieldSort::make('name.keyword', 'name'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $models);
        $this->assertSortedDescending($models, 'name');
    }

    // === Filter + Sort + Include tests ===

    /** @test */
    public function it_can_apply_filter_sort_and_include_together(): void
    {
        $model1 = TestModel::factory()->create(['name' => 'Alice', 'category' => 'combined-test']);
        $model2 = TestModel::factory()->create(['name' => 'Bob', 'category' => 'combined-test']);
        TestModel::factory()->create(['name' => 'Charlie', 'category' => 'other']);

        $model1->relatedModels()->create(['name' => 'Related 1']);
        $model2->relatedModels()->create(['name' => 'Related 2']);

        $models = $this->createElasticWizardFromQuery([
            'filter' => ['category' => 'combined-test'],
            'sort' => 'name',
            'include' => 'relatedModels',
        ])
            ->allowedFilters('category')
            ->allowedSorts(FieldSort::make('name.keyword', 'name'))
            ->allowedIncludes('relatedModels')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $models);
        $this->assertSortedAscending($models, 'name');
        $this->assertRelationLoaded($models, 'relatedModels');
    }

    // === modifyQuery callback tests ===

    /** @test */
    public function it_executes_modify_query_callback(): void
    {
        TestModel::factory()->count(3)->create();
        $callbackExecuted = false;

        $models = ElasticQueryWizard::for(TestModel::class)
            ->modifyQuery(function (Builder $builder, array $rawResult) use (&$callbackExecuted) {
                $callbackExecuted = true;
                $builder->select(['id', 'name']);
            })
            ->build()
            ->execute()
            ->models();

        $this->assertTrue($callbackExecuted);
        $this->assertCount(3, $models);
        $this->assertEqualsCanonicalizing(['id', 'name'], array_keys($models->first()->getAttributes()));
    }

    /** @test */
    public function it_executes_multiple_modify_query_callbacks_in_order(): void
    {
        TestModel::factory()->count(2)->create();
        $order = [];

        $models = ElasticQueryWizard::for(TestModel::class)
            ->modifyQuery(function (Builder $builder) use (&$order) {
                $order[] = 'first';
            })
            ->modifyQuery(function (Builder $builder) use (&$order) {
                $order[] = 'second';
            })
            ->modifyQuery(function (Builder $builder) use (&$order) {
                $order[] = 'third';
            })
            ->build()
            ->execute()
            ->models();

        $this->assertEquals(['first', 'second', 'third'], $order);
    }

    /** @test */
    public function it_passes_raw_result_to_modify_query_callback_when_array_is_expected(): void
    {
        TestModel::factory()->count(2)->create();
        $receivedRawResult = null;

        ElasticQueryWizard::for(TestModel::class)
            ->modifyQuery(function (Builder $builder, array $rawResult) use (&$receivedRawResult) {
                $receivedRawResult = $rawResult;
            })
            ->build()
            ->execute()
            ->models();

        $this->assertIsArray($receivedRawResult);
        $this->assertArrayHasKey('hits', $receivedRawResult);
    }

    // === modifyModels callback tests ===

    /** @test */
    public function it_executes_modify_models_callback(): void
    {
        TestModel::factory()->count(3)->create();
        $callbackExecuted = false;

        $models = ElasticQueryWizard::for(TestModel::class)
            ->modifyModels(function (Collection $collection) use (&$callbackExecuted) {
                $callbackExecuted = true;
                return $collection;
            })
            ->build()
            ->execute()
            ->models();

        $this->assertTrue($callbackExecuted);
        $this->assertCount(3, $models);
    }

    /** @test */
    public function it_executes_multiple_modify_models_callbacks_in_order(): void
    {
        TestModel::factory()->count(2)->create();
        $order = [];

        ElasticQueryWizard::for(TestModel::class)
            ->modifyModels(function (Collection $collection) use (&$order) {
                $order[] = 'first';
                return $collection;
            })
            ->modifyModels(function (Collection $collection) use (&$order) {
                $order[] = 'second';
                return $collection;
            })
            ->build()
            ->execute()
            ->models();

        $this->assertEquals(['first', 'second'], $order);
    }

    // === Field selection tests ===

    /** @test */
    public function it_selects_only_allowed_fields(): void
    {
        TestModel::factory()->create(['name' => 'Test Name', 'category' => 'Test Category']);

        $models = $this->createElasticWizardWithFields(['testModel' => 'name'])
            ->allowedFields('name', 'category')
            ->build()
            ->execute()
            ->models();

        $firstModel = $models->first();
        $this->assertArrayHasKey('name', $firstModel->toArray());
        $this->assertArrayNotHasKey('category', $firstModel->toArray());
    }

    /** @test */
    public function it_always_includes_primary_key_in_fields(): void
    {
        TestModel::factory()->create();

        $models = $this->createElasticWizardWithFields(['testModel' => 'name'])
            ->allowedFields('name')
            ->build()
            ->execute()
            ->models();

        $firstModel = $models->first();
        $this->assertNotNull($firstModel->id);
    }

    // === Appends tests ===

    /** @test */
    public function it_appends_accessors_to_models(): void
    {
        AppendModel::factory()->create(['firstname' => 'John', 'lastname' => 'Doe']);

        $models = $this->createElasticWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->build()
            ->execute()
            ->models();

        $firstModel = $models->first();
        $this->assertEquals('John Doe', $firstModel->fullname);
    }

    // === BoolQuery access tests ===

    /** @test */
    public function it_provides_access_to_bool_query(): void
    {
        TestModel::factory()->count(3)->create();

        $wizard = ElasticQueryWizard::for(TestModel::class);
        $boolQuery = $wizard->boolQuery();

        $this->assertNotNull($boolQuery);
        $this->assertInstanceOf(\Jackardios\EsScoutDriver\Query\Compound\BoolQuery::class, $boolQuery);
    }

    // === Helper methods ===

    protected function assertRelationLoaded(Collection $collection, string $relation): void
    {
        $hasModelWithoutRelationLoaded = $collection
            ->contains(function (Model $model) use ($relation) {
                return ! $model->relationLoaded($relation);
            });

        $this->assertFalse($hasModelWithoutRelationLoaded, "The `{$relation}` relation was expected but not loaded.");
    }
}
