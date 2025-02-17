<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\RelatedModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;

/**
 * @group elastic
 * @group fields
 * @group elastic-fields
 */
class FieldsTest extends TestCase
{
    protected TestModel $model;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = factory(TestModel::class)->create();
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
            ->setAllowedFields('id', 'name')
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
            ->addEloquentQueryCallback(function(Builder $query) {
                $query->select(['id', 'is_visible']);
            })
            ->setAllowedFields(['name', 'id'])
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
            ->setAllowedFields(['name', 'id'])
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
        $model = $this
            ->createElasticWizardWithFields(['testModel' => 'random-column'])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_guards_against_not_allowed_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createElasticWizardWithFields(['testModel' => 'random-column'])
            ->setAllowedFields('name')
            ->build();
    }

    /** @test */
    public function it_guards_against_not_allowed_fields_from_an_included_resource(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createElasticWizardWithFields(['relatedModels' => 'random_column'])
            ->setAllowedFields('relatedModels.name')
            ->build();
    }

    /** @test */
    public function it_can_fetch_only_requested_columns_from_an_included_model(): void
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $elasticWizard = $this
            ->createElasticWizardFromQuery([
                'fields' => [
                    'testModel' => 'id',
                    'relatedModels' => 'name',
                ],
                'include' => ['relatedModels'],
            ])
            ->setAllowedFields('relatedModels.name', 'id')
            ->setAllowedIncludes('relatedModels')
            ->build();

        DB::enableQueryLog();

        $elasticWizard
            ->size(1)
            ->execute()
            ->models()
            ->first()
            ->relatedModels;

        $this->assertQueryLogContains('select `id` from `test_models`');
        $this->assertQueryLogContains('select `name` from `related_models`');
    }

    /** @test */
    public function it_can_fetch_requested_columns_from_included_models_up_to_two_levels_deep(): void
    {
        RelatedModel::create([
            'test_model_id' => $this->model->id,
            'name' => 'related',
        ]);

        $model = $this
            ->createElasticWizardFromQuery([
                'fields' => [
                    'testModel' => 'id,name',
                    'relatedModels.testModel' => 'id',
                ],
                'include' => ['relatedModels.testModel'],
            ])
            ->setAllowedFields('relatedModels.testModel.id', 'id', 'name')
            ->setAllowedIncludes('relatedModels.testModel')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $this->assertArrayHasKey('name', $model);

        $this->assertEquals(['id' => $this->model->id], $model->relatedModels->first()->testModel->toArray());
    }

    /** @test */
    public function it_can_allow_specific_fields_on_an_included_model(): void
    {
        $elasticWizard = $this
            ->createElasticWizardFromQuery([
                'fields' => ['relatedModels' => 'id,name'],
                'include' => ['relatedModels'],
            ])
            ->setAllowedFields(['relatedModels.id', 'relatedModels.name'])
            ->setAllowedIncludes('relatedModels')
            ->build();

        DB::enableQueryLog();

        $elasticWizard
            ->size(1)
            ->execute()
            ->models()
            ->first()
            ->relatedModels;

        $this->assertQueryLogContains('select * from `test_models`');
        $this->assertQueryLogContains('select `id`, `name` from `related_models`');
    }

    /** @test */
    public function it_wont_use_sketchy_field_requests(): void
    {
        DB::enableQueryLog();

        $this->createElasticWizardWithFields(['testModel' => 'id->"\')from test_models--injection'])
            ->build()
            ->size(1)
            ->execute()
            ->models();

        $this->assertQueryLogDoesntContain('--injection');
    }
}
