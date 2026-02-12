<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;

/**
 * @group elastic
 * @group fields
 * @group elastic-fields
 */
class FieldsTest extends TestCase
{
    protected TestModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = TestModel::factory()->create();
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested(): void
    {
        $model = $this
            ->createElasticWizardWithFields([])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested_but_allowed_fields_were_specified(): void
    {
        $model = $this
            ->createElasticWizardWithFields([])
            ->allowedFields('id', 'name')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_replaces_selected_columns_on_the_query(): void
    {
        $model = $this
            ->createElasticWizardWithFields(['testModel' => 'name,id'])
            ->modifyQuery(function (Builder $query) {
                $query->select(['id', 'is_visible']);
            })
            ->allowedFields(['name', 'id'])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()
            ->select("name", "id")
            ->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_can_fetch_specific_columns(): void
    {
        $model = $this
            ->createElasticWizardWithFields(['testModel' => 'name,id'])
            ->allowedFields(['name', 'id'])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()
            ->select("name", "id")
            ->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_wont_fetch_a_specific_column_if_its_not_allowed(): void
    {
        // In v3, requesting fields without allowedFields() throws InvalidFieldQuery
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createElasticWizardWithFields(['testModel' => 'random-column'])
            ->build();
    }

    /** @test */
    public function it_guards_against_not_allowed_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createElasticWizardWithFields(['testModel' => 'random-column'])
            ->allowedFields('name')
            ->build();
    }

    /** @test */
    public function it_wont_use_sketchy_field_requests(): void
    {
        // In v3, requesting fields without allowedFields() throws InvalidFieldQuery
        // This also prevents SQL injection attempts
        $this->expectException(InvalidFieldQuery::class);

        $this->createElasticWizardWithFields(['testModel' => 'id->"\')from test_models--injection'])
            ->build();
    }

    /** @test */
    public function it_does_not_hide_everything_when_invalid_fields_are_ignored(): void
    {
        Config::set('query-wizard.disable_invalid_field_query_exception', true);

        $model = $this
            ->createElasticWizardWithFields(['testModel' => 'totally_unknown_field'])
            ->allowedFields('name')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $this->assertNotEmpty($model->toArray());
        $this->assertArrayHasKey('name', $model->toArray());
    }

    // ========== Relation Fields Tests ==========

    /** @test */
    public function it_can_select_fields_for_included_relation(): void
    {
        $this->model->relatedModels()->create(['name' => 'Related']);

        $model = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id,name',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id', 'relatedModels.name')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $this->assertTrue($model->relationLoaded('relatedModels'));
        $relatedAttributes = array_keys($model->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertContains('name', $relatedAttributes);
        $this->assertNotContains('test_model_id', $relatedAttributes);
    }

    /** @test */
    public function it_can_select_fields_for_nested_included_relation(): void
    {
        $related = $this->model->relatedModels()->create(['name' => 'Related']);
        $related->nestedRelatedModels()->create(['name' => 'Nested']);

        $model = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels.nestedRelatedModels',
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels' => 'id',
                    'relatedModels.nestedRelatedModels' => 'id',
                ],
            ])
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->allowedFields('id', 'name', 'relatedModels.id', 'relatedModels.nestedRelatedModels.id')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $nestedAttributes = array_keys($model->relatedModels->first()->nestedRelatedModels->first()->toArray());
        $this->assertEquals(['id'], $nestedAttributes);
    }

    /** @test */
    public function it_can_use_wildcard_for_relation_fields(): void
    {
        $this->model->relatedModels()->create(['name' => 'Related']);

        $model = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id',
                    'relatedModels' => 'id,name',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'relatedModels.*')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $relatedAttributes = array_keys($model->relatedModels->first()->toArray());
        $this->assertContains('id', $relatedAttributes);
        $this->assertContains('name', $relatedAttributes);
    }

    /** @test */
    public function it_throws_exception_for_not_allowed_relation_field(): void
    {
        $this->model->relatedModels()->create(['name' => 'Related']);

        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'fields' => [
                    'testModel' => 'id',
                    'relatedModels' => 'secret_field',
                ],
            ])
            ->allowedIncludes('relatedModels')
            ->allowedFields('id', 'relatedModels.id', 'relatedModels.name')
            ->build();
    }
}
